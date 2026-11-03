<?php

namespace Database\Seeders;

use App\EnrolmentRules;
use App\Models\AdmissionApplication;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\StudentEnrolment;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LearningDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Learning demo data is restricted to local/testing environments.');
        }
        DB::transaction(function (): void {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $actor = User::where('email', 'admin@demo.ems.test')->first();
            $class = SchoolClass::where('code', 'DEMO-KL-3')->where('status', 'active')->with('academicTerm')->first();
            if (! $actor || ! $actor->isGlobalAdmin() || ! $class) {
                throw new RuntimeException('Run DemoSeeder first; its administrator and active KL class are required.');
            }
            foreach (range(1, 4) as $index) {
                $student = Student::where('student_number', 'DEMO-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT))->first();
                if (! $student) {
                    continue;
                }
                if (! StudentEnrolment::where('student_id', $student->id)->exists()) {
                    $record = StudentEnrolment::create(['student_id' => $student->id, 'school_class_id' => $class->id, 'starts_on' => $class->academicTerm->starts_on, 'ends_on' => $class->academicTerm->ends_on, 'status' => 'active', 'created_by' => $actor->id]);
                    if (EnrolmentRules::peak($class) > $class->capacity) {
                        throw new RuntimeException('Demo enrolments would exceed capacity. No learning demo changes were saved.');
                    }
                    $this->audit($actor, 'enrolments', $record->id, $class->campus_id);
                }
                $guardian = User::where('email', 'guardian.'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'@demo.ems.test')->first();
                if ($guardian && $guardian->hasAnyRole('guardian')) {
                    $link = StudentGuardian::firstOrCreate(['student_id' => $student->id, 'user_id' => $guardian->id], ['relationship' => 'Parent', 'emergency_contact' => true, 'status' => 'active']);
                    if ($link->wasRecentlyCreated) {
                        $this->audit($actor, 'student_links', $link->id, null);
                    }
                }
                $account = User::where('email', 'student.'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'@demo.ems.test')->first();
                if ($account && $account->hasAnyRole('student') && ! StudentAccountLink::where('student_id', $student->id)->orWhere('user_id', $account->id)->exists()) {
                    $link = StudentAccountLink::create(['student_id' => $student->id, 'user_id' => $account->id]);
                    $this->audit($actor, 'student_links', $link->id, null);
                }
            }
            foreach (['draft', 'submitted', 'under_review', 'rejected'] as $index => $status) {
                $app = AdmissionApplication::firstOrCreate(['reference' => 'DEMO-APP-'.($index + 1)], ['created_by' => $actor->id, 'campus_id' => $class->campus_id, 'programme_id' => $class->programme_id, 'full_name' => 'Demo Admission Applicant '.($index + 1), 'email' => 'admission.'.($index + 1).'@demo.ems.test', 'requested_start' => $class->academicTerm->starts_on, 'status' => $status, 'submitted_at' => $status !== 'draft' ? now() : null, 'reviewed_at' => $status === 'rejected' ? now() : null, 'reviewed_by' => $status === 'rejected' ? $actor->id : null, 'decision_note' => $status === 'rejected' ? 'Demo example: incomplete eligibility evidence.' : null]);
                if ($app->wasRecentlyCreated) {
                    $this->audit($actor, 'admissions', $app->id, $class->campus_id);
                }
            }
        });
    }

    private function audit(User $actor, string $type, int $id, ?int $campus): void
    {
        AuditLog::create(['actor_user_id' => null, 'campus_id' => $campus, 'subject_type' => $type, 'subject_id' => $id, 'action' => 'demo_seeded', 'changed_fields' => ['demo_record']]);
    }
}
