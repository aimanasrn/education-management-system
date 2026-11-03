<?php

namespace Tests\Feature;

use App\EnrolmentRules;
use App\Models\AdmissionApplication;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Programme;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\StudentEnrolment;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function role(string $role, array $campuses = []): User
    {
        $user = User::factory()->create();
        $user->roleAssignments()->delete();
        $user->roleAssignments()->create(['role' => $role]);
        $user->campuses()->sync($campuses);

        return $user->fresh();
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    }

    private function draft(): array
    {
        return ['full_name' => 'New Applicant', 'email' => 'applicant@example.test', 'phone' => '+60123456789', 'requested_start' => '2026-01-05', 'campus_id' => Campus::factory()->create()->id, 'programme_id' => Programme::factory()->create()->id];
    }

    private function place(Student $student, SchoolClass $class, string $start = '2026-01-05', string $end = '2026-06-30')
    {
        return $this->postJson('/api/learning/enrolments', ['student_id' => $student->id, 'school_class_id' => $class->id, 'starts_on' => $start, 'ends_on' => $end]);
    }

    public function test_authentication_and_role_boundaries(): void
    {
        $this->getJson('/api/admissions')->assertUnauthorized();
        $this->getJson('/api/learning/students')->assertUnauthorized();
        $this->actingAs($this->role('finance_officer'), 'sanctum');
        $this->getJson('/api/admissions')->assertForbidden();
        $this->getJson('/api/learning/students')->assertForbidden();
        $this->postJson('/api/learning/enrolments', [])->assertForbidden();
        $this->postJson('/api/admissions', $this->draft())->assertForbidden();
    }

    public function test_application_approval_is_atomic_once_only_and_does_not_enrol_implicitly(): void
    {
        $this->admin();
        $data = $this->draft();
        $id = $this->postJson('/api/admissions', [...$data, 'status' => 'approved', 'student_id' => 999])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->postJson("/api/admissions/$id/transition", ['action' => 'approve'])->assertConflict();
        $this->postJson("/api/admissions/$id/transition", ['action' => 'submit'])->assertOk();
        $this->putJson("/api/admissions/$id", $data)->assertConflict();
        $this->postJson("/api/admissions/$id/transition", ['action' => 'review'])->assertOk();
        $result = $this->postJson("/api/admissions/$id/transition", ['action' => 'approve', 'note' => 'Accepted'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertStringStartsWith('STU-', $result->json('data.student_number'));
        $this->postJson("/api/admissions/$id/transition", ['action' => 'approve'])->assertConflict();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('student_enrolments', 0);
        $audit = AuditLog::latest('id')->first()->toJson();
        $this->assertStringNotContainsString('New Applicant', $audit);
        $this->assertStringNotContainsString('Accepted', $audit);
    }

    public function test_applicants_see_only_their_own_applications_and_cannot_decide(): void
    {
        $a = $this->role('student');
        $other = AdmissionApplication::factory()->create();
        $this->actingAs($a, 'sanctum');
        $id = $this->postJson('/api/admissions', $this->draft())->assertCreated()->json('data.id');
        $this->getJson('/api/admissions')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admissions/'.$other->id)->assertNotFound();
        $this->postJson("/api/admissions/$id/transition", ['action' => 'review'])->assertForbidden();
        $this->postJson("/api/admissions/$id/transition", ['action' => 'withdraw'])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_campus_manager_isolation_in_admissions_and_existing_student_profiles(): void
    {
        $own = Campus::factory()->create();
        $other = Campus::factory()->create();
        AdmissionApplication::factory()->create(['campus_id' => $own->id]);
        $hidden = AdmissionApplication::factory()->create(['campus_id' => $other->id]);
        $this->actingAs($this->role('campus_manager', [$own->id]), 'sanctum');
        $this->getJson('/api/admissions')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admissions/'.$hidden->id)->assertNotFound();
        $this->postJson('/api/admissions', [...$this->draft(), 'campus_id' => $other->id])->assertForbidden();
        $this->getJson('/api/students')->assertForbidden();
        $this->getJson('/api/learning/students')->assertJsonPath('meta.total', 0);
    }

    public function test_rejection_requires_reason_and_withdrawal_is_not_approval(): void
    {
        $this->admin();
        $app = AdmissionApplication::factory()->create(['status' => 'under_review']);
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'reject', 'note' => 'Missing required evidence'])->assertOk();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_private_document_upload_validation_download_authorization_and_verification(): void
    {
        Storage::fake('local');
        $owner = $this->role('student');
        $app = AdmissionApplication::factory()->create(['created_by' => $owner->id]);
        $this->actingAs($owner, 'sanctum');
        $this->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertUnprocessable();
        $this->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('huge.pdf', 5121, 'application/pdf')])->assertUnprocessable();
        $doc = $this->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('record.pdf', 10, 'application/pdf')])->assertCreated()->json('data.id');
        $this->get("/api/admissions/$app->id/documents/$doc")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->role('guardian'), 'sanctum')->getJson("/api/admissions/$app->id/documents/$doc")->assertNotFound();
        $this->admin();
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'submit'])->assertOk();
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'review'])->assertOk();
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('documents');
        $this->postJson("/api/admissions/$app->id/documents/$doc/verify")->assertOk();
        $this->getJson("/api/admissions/$app->id")->assertOk()->assertJsonMissingPath('data.documents.0.path');
        $this->postJson("/api/admissions/$app->id/transition", ['action' => 'approve'])->assertOk();
        $this->postJson("/api/admissions/$app->id/documents", ['file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')])->assertConflict();
    }

    public function test_enrolment_term_dates_inactive_catalogues_and_archived_students_are_rejected(): void
    {
        $this->admin();
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create();
        $this->place($student, $class, '2025-12-31')->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $student->delete();
        $this->place($student, $class)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $student->restore();
        $class->campus->update(['status' => 'inactive']);
        $this->place($student, $class)->assertUnprocessable()->assertJsonValidationErrors('school_class_id');
    }

    public function test_duplicate_programme_placements_and_capacity_are_enforced(): void
    {
        $this->admin();
        $class = SchoolClass::factory()->create(['capacity' => 1]);
        $student = Student::factory()->create();
        $this->place($student, $class)->assertCreated();
        $this->place($student, $class)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->place(Student::factory()->create(), $class)->assertUnprocessable()->assertJsonValidationErrors('school_class_id');
        $this->assertDatabaseCount('student_enrolments', 1);
        $this->assertSame(1, AuditLog::where('subject_type', 'enrolments')->count());
    }

    public function test_capacity_uses_peak_occupancy_not_sum_of_nonoverlapping_periods(): void
    {
        $this->admin();
        $class = SchoolClass::factory()->create(['capacity' => 2]);
        $this->place(Student::factory()->create(), $class, '2026-01-01', '2026-02-28')->assertCreated();
        $this->place(Student::factory()->create(), $class, '2026-03-01', '2026-06-30')->assertCreated();
        $this->place(Student::factory()->create(), $class, '2026-01-01', '2026-06-30')->assertCreated();
        $this->assertSame(2, EnrolmentRules::peak($class));
    }

    public function test_transfer_preserves_history_and_rolls_back_if_destination_full(): void
    {
        $this->admin();
        $old = SchoolClass::factory()->create();
        $new = SchoolClass::factory()->create(['programme_id' => $old->programme_id, 'capacity' => 1]);
        $student = Student::factory()->create();
        $id = $this->place($student, $old)->assertCreated()->json('data.id');
        $full = StudentEnrolment::factory()->create(['school_class_id' => $new->id]);
        $change = ['action' => 'transfer', 'effective_on' => '2026-03-01', 'reason' => 'Campus change', 'school_class_id' => $new->id, 'ends_on' => '2026-06-30'];
        $this->postJson("/api/learning/enrolments/$id/transition", $change)->assertUnprocessable();
        $this->assertDatabaseHas('student_enrolments', ['id' => $id, 'status' => 'active']);
        $full->update(['ends_on' => '2026-02-28', 'status' => 'completed']);
        $this->postJson("/api/learning/enrolments/$id/transition", $change)->assertOk();
        $this->assertDatabaseHas('student_enrolments', ['id' => $id, 'status' => 'transferred']);
        $this->assertDatabaseHas('student_enrolments', ['student_id' => $student->id, 'school_class_id' => $new->id, 'previous_enrolment_id' => $id]);
        $this->assertSame('2026-02-28', StudentEnrolment::find($id)->ends_on->toDateString());
        $this->postJson("/api/learning/enrolments/$id/transition", $change)->assertConflict();
    }

    public function test_guardian_revocation_and_student_self_identity_are_authoritative(): void
    {
        $this->admin();
        $student = Student::factory()->create(['phone' => '+601999999']);
        $guardian = $this->role('guardian');
        $account = $this->role('student');
        $input = ['kind' => 'guardian', 'student_id' => $student->id, 'user_id' => $guardian->id, 'relationship' => 'Parent', 'emergency_contact' => true, 'status' => 'active'];
        $this->postJson('/api/learning/links', $input)->assertOk();
        $this->postJson('/api/learning/links', ['kind' => 'student', 'student_id' => $student->id, 'user_id' => $account->id])->assertOk();
        $admin = auth()->user();
        $this->actingAs($guardian, 'sanctum')->getJson('/api/learning/students/'.$student->id)->assertOk()->assertJsonPath('data.phone', '+601999999');
        $this->getJson('/api/learning/students')->assertJsonPath('meta.total', 1);
        $this->postJson('/api/learning/links', $input)->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/learning/links', [...$input, 'status' => 'inactive'])->assertOk();
        $this->actingAs($guardian, 'sanctum')->getJson('/api/learning/students/'.$student->id)->assertNotFound();
        $this->actingAs($account, 'sanctum')->getJson('/api/learning/students/'.$student->id)->assertOk();
        $this->getJson('/api/learning/students/'.Student::factory()->create()->id)->assertNotFound();
    }

    public function test_teacher_scope_and_contact_redaction_with_multiple_roles(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10));
        $class = SchoolClass::factory()->create();
        $teacher = $this->role('teacher', [$class->campus_id]);
        $teacher->roleAssignments()->create(['role' => 'guardian']);
        $teacher->unsetRelation('roleAssignments');
        $class->teachers()->attach($teacher);
        $student = Student::factory()->create(['email' => 'private@example.test']);
        StudentEnrolment::factory()->create(['student_id' => $student->id, 'school_class_id' => $class->id]);
        $hidden = StudentEnrolment::factory()->create(['student_id' => $student->id]);
        $this->actingAs($teacher, 'sanctum');
        $this->getJson('/api/learning/students/'.$student->id)->assertOk()->assertJsonPath('data.email', null)->assertJsonPath('enrolment_total', 1)->assertJsonPath('data.contacts_visible', false);
        $this->getJson('/api/learning/enrolments')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/students')->assertForbidden();
        $this->postJson('/api/learning/enrolments', [])->assertForbidden();
    }

    public function test_self_link_revocation_preserves_identity_history_and_revokes_access(): void
    {
        $admin = User::factory()->admin()->create();
        $account = $this->role('student');
        $old = Student::factory()->create();
        $new = Student::factory()->create();
        $this->actingAs($admin, 'sanctum');
        $input = ['kind' => 'student', 'student_id' => $old->id, 'user_id' => $account->id];
        $this->postJson('/api/learning/links', $input)->assertOk();
        $this->postJson('/api/learning/links', [...$input, 'student_id' => $new->id])->assertUnprocessable();
        $this->postJson('/api/learning/links', [...$input, 'status' => 'inactive'])->assertOk();
        $this->postJson('/api/learning/links', [...$input, 'student_id' => $new->id])->assertOk();
        $this->assertDatabaseCount('student_account_links', 2);
        $this->assertNotNull(StudentAccountLink::where('student_id', $old->id)->first()->revoked_at);
        $this->actingAs($account, 'sanctum');
        $this->getJson('/api/learning/students/'.$old->id)->assertNotFound();
        $this->getJson('/api/learning/students/'.$new->id)->assertOk();
    }

    public function test_removing_guardian_role_removes_personal_contact_access_for_a_teacher(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10));
        $class = SchoolClass::factory()->create();
        $teacher = $this->role('teacher', [$class->campus_id]);
        $class->teachers()->attach($teacher);
        $student = Student::factory()->create();
        StudentEnrolment::factory()->create(['student_id' => $student->id, 'school_class_id' => $class->id]);
        StudentGuardian::factory()->create(['student_id' => $student->id, 'user_id' => $teacher->id]);
        $this->actingAs($teacher, 'sanctum')->getJson('/api/learning/students/'.$student->id)->assertOk()->assertJsonPath('data.contacts_visible', false);
    }

    public function test_term_dates_cannot_exclude_existing_enrolments(): void
    {
        $this->admin();
        $class = SchoolClass::factory()->create();
        $this->place(Student::factory()->create(), $class)->assertCreated();
        $term = $class->academicTerm;
        $this->putJson('/api/foundation/terms/'.$term->id, [...$term->toArray(), 'ends_on' => '2026-05-31'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    }

    public function test_capacity_reduction_and_archiving_preserve_existing_enrolments(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10));
        $this->admin();
        $class = SchoolClass::factory()->create(['capacity' => 3]);
        $student = Student::factory()->create();
        $this->place($student, $class)->assertCreated();
        $this->place(Student::factory()->create(), $class)->assertCreated();
        $this->putJson('/api/foundation/classes/'.$class->id, [...$class->toArray(), 'capacity' => 1, 'teacher_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->postJson('/api/students/'.$student->id.'/archive')->assertConflict();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'deleted_at' => null]);
    }
}
