<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Campus;
use App\Models\CampusClosure;
use App\Models\Programme;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public const CREDENTIALS_FILE = 'demo-credentials.json';

    /** @var array<string, mixed> */
    private array $credentials = [];

    private bool $credentialsChanged = false;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data is restricted to local/testing environments. No data was changed.');
        }

        $this->credentialsChanged = false;
        DB::transaction(function (): void {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $this->loadCredentials();
            $campuses = $this->seedCampuses();
            $rooms = $this->seedFacilities($campuses);
            $teachers = $this->seedUsers($campuses);
            $years = $this->seedYears();
            $programmes = $this->seedProgrammes();
            $this->seedClasses($campuses, $rooms, $teachers, $years, $programmes);
            $this->seedClosures($campuses);
            $this->seedStudents();

            if ($this->credentialsChanged) {
                $this->credentials['updated_at'] = now()->toIso8601String();
                if (! Storage::disk('local')->put(self::CREDENTIALS_FILE, json_encode($this->credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'private')) {
                    throw new RuntimeException('Could not save private demo credentials. Database changes were rolled back.');
                }
            }
        });

        $this->command?->info('Demo data is ready. Existing records and passwords were preserved.');
        $this->command?->info('Initial passwords for new demo accounts: storage/app/private/'.self::CREDENTIALS_FILE);
    }

    private function loadCredentials(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::CREDENTIALS_FILE)) {
            $this->credentials = ['version' => 1, 'accounts' => []];

            return;
        }

        $data = json_decode($disk->get(self::CREDENTIALS_FILE), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['accounts'] ?? null)) {
            throw new RuntimeException('The demo credentials file is invalid. Restore it before seeding.');
        }

        $this->credentials = $data;
    }

    /** @return array<string, Campus> */
    private function seedCampuses(): array
    {
        $campuses = [];
        foreach (['KL' => 'Kuala Lumpur', 'PJ' => 'Petaling Jaya', 'SA' => 'Shah Alam'] as $key => $name) {
            $hours = [];
            foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
                $closed = $day === 'sun' || $key === 'SA';
                $hours[$day] = ['closed' => $closed, 'opens' => $closed ? null : '08:00', 'closes' => $closed ? null : ($day === 'sat' ? '13:00' : '18:00')];
            }
            $campus = Campus::firstOrCreate(['code' => 'DEMO-'.$key], [
                'name' => 'Demo '.$name.' Centre',
                'email' => strtolower($key).'.campus@demo.ems.test',
                'phone' => '+6000000000'.(count($campuses) + 1),
                'address' => 'Synthetic demonstration campus — sample address only.',
                'operating_hours' => $hours, 'status' => $key === 'SA' ? 'inactive' : 'active',
            ]);
            $this->auditCreated($campus, 'campuses', $campus->id);
            $campuses[$key] = $campus;
        }

        return $campuses;
    }

    /** @param array<string, Campus> $campuses
     * @return array<string, list<Room>>
     */
    private function seedFacilities(array $campuses): array
    {
        $rooms = [];
        foreach ($campuses as $key => $campus) {
            $rooms[$key] = [];
            foreach (['MAIN' => 'Main building', 'ANNEX' => 'Learning annex'] as $buildingCode => $name) {
                $building = Building::firstOrCreate(['campus_id' => $campus->id, 'code' => 'DEMO-'.$buildingCode], [
                    'name' => 'Demo '.$name, 'status' => $campus->status,
                ]);
                $this->auditCreated($building, 'buildings', $campus->id);
                foreach ([18, 24, 30] as $index => $capacity) {
                    $room = Room::firstOrCreate(['campus_id' => $campus->id, 'code' => 'DEMO-'.$buildingCode.'-'.($index + 1)], [
                        'building_id' => $building->id, 'name' => 'Demo '.$name.' room '.($index + 1),
                        'capacity' => $capacity,
                        'facilities' => $index === 2 ? ['Activity mats', 'Storage', 'Air conditioning'] : ['Whiteboard', 'Projector', 'Air conditioning'],
                        'status' => $buildingCode === 'ANNEX' && $index === 2 ? 'inactive' : $campus->status,
                    ]);
                    $this->auditCreated($room, 'rooms', $campus->id);
                    $rooms[$key][] = $room;
                }
            }
        }

        return $rooms;
    }

    /** @param array<string, Campus> $campuses
     * @return array<string, list<User>>
     */
    private function seedUsers(array $campuses): array
    {
        $allCampusIds = array_map(fn (Campus $campus): int => $campus->id, array_values($campuses));
        $this->seedUser('admin@demo.ems.test', 'Demo System Administrator', ['admin'], $allCampusIds);
        $this->seedUser('owner@demo.ems.test', 'Demo Centre Owner', ['centre_admin'], $allCampusIds);
        $teachers = [];
        foreach ($campuses as $key => $campus) {
            $slug = strtolower($key);
            $this->seedUser('manager.'.$slug.'@demo.ems.test', 'Demo '.$key.' Campus Manager', ['campus_manager'], [$campus->id]);
            $teachers[$key] = [];
            foreach ([1, 2] as $number) {
                $teachers[$key][] = $this->seedUser('teacher.'.$slug.'.'.$number.'@demo.ems.test', 'Demo '.$key.' Teacher '.$number, ['teacher'], [$campus->id]);
            }
        }
        $activeCampuses = [$campuses['KL']->id, $campuses['PJ']->id];
        $this->seedUser('hr@demo.ems.test', 'Demo HR Officer', ['hr_officer'], $activeCampuses);
        $this->seedUser('finance@demo.ems.test', 'Demo Finance Officer', ['finance_officer'], $activeCampuses);
        $coordinator = $this->seedUser('coordinator@demo.ems.test', 'Demo Teaching and HR Coordinator', ['teacher', 'hr_officer'], $activeCampuses);
        $teachers['KL'][] = $coordinator;
        $teachers['PJ'][] = $coordinator;
        $this->seedUser('disabled.teacher@demo.ems.test', 'Demo Disabled Teacher', ['teacher'], [$campuses['KL']->id], false);
        foreach (range(1, 6) as $number) {
            $this->seedUser(sprintf('student.%02d@demo.ems.test', $number), 'Demo Student Account '.$number, ['student'], []);
        }
        foreach (range(1, 4) as $number) {
            $this->seedUser(sprintf('guardian.%02d@demo.ems.test', $number), 'Demo Guardian Account '.$number, ['guardian'], []);
        }

        return $teachers;
    }

    /** @param list<string> $roles
     * @param  list<int>  $campusIds
     */
    private function seedUser(string $email, string $name, array $roles, array $campusIds, bool $active = true): User
    {
        if ($existing = User::where('email', $email)->first()) {
            return $existing;
        }

        $password = $this->credentials['accounts'][$email]['password'] ?? Str::password(24);
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = in_array('admin', $roles, true) ? UserRole::Admin : (in_array('teacher', $roles, true) ? UserRole::Teacher : UserRole::Student);
        $user->is_active = $active;
        $user->save();
        $user->roleAssignments()->delete();
        $user->roleAssignments()->createMany(array_map(fn (string $role): array => ['role' => $role], $roles));
        $user->campuses()->sync($campusIds);
        $this->credentials['accounts'][$email] = ['name' => $name, 'email' => $email, 'password' => $password, 'roles' => $roles, 'enabled' => $active];
        $this->credentialsChanged = true;
        $this->auditCreated($user, 'users', null, ['name', 'email', 'roles', 'campus_ids', 'is_active']);

        return $user->fresh('roleAssignments');
    }

    /** @return list<array{year: AcademicYear, terms: list<AcademicTerm>}> */
    private function seedYears(): array
    {
        $years = [];
        foreach ([2026, 2027] as $number) {
            $year = AcademicYear::firstOrCreate(['code' => 'DEMO-'.$number], [
                'name' => 'Demo Academic Year '.$number, 'starts_on' => $number.'-01-01',
                'ends_on' => $number.'-12-31', 'status' => $number === 2026 ? 'active' : 'inactive',
            ]);
            $this->auditCreated($year, 'years');
            $terms = [];
            foreach ([['01-01', '04-30'], ['05-01', '08-31'], ['09-01', '12-31']] as $index => [$start, $end]) {
                $term = AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'code' => 'DEMO-T'.($index + 1)], [
                    'name' => 'Demo Term '.($index + 1).' '.$number,
                    'starts_on' => $number.'-'.$start, 'ends_on' => $number.'-'.$end, 'status' => $year->status,
                ]);
                $this->auditCreated($term, 'terms');
                $terms[] = $term;
            }
            $years[] = ['year' => $year, 'terms' => $terms];
        }

        return $years;
    }

    /** @return list<array{programme: Programme, subjects: list<Subject>}> */
    private function seedProgrammes(): array
    {
        $programmes = [];
        foreach ([
            'EARLY' => ['Early Years', ['LANG' => 'Language and Literacy', 'NUM' => 'Early Numeracy', 'PLAY' => 'Creative Play']],
            'PRIMARY' => ['Primary Tuition', ['BM' => 'Bahasa Melayu', 'MATH' => 'Mathematics', 'SCI' => 'Science']],
            'LANG' => ['Language Enrichment', ['ENG' => 'English', 'READ' => 'Reading Skills', 'SPEAK' => 'Speaking Skills']],
        ] as $code => [$name, $subjectNames]) {
            $programme = Programme::firstOrCreate(['code' => 'DEMO-'.$code], [
                'name' => 'Demo '.$name, 'description' => 'Synthetic programme for exploring EMS setup and permissions.', 'status' => 'active',
            ]);
            $this->auditCreated($programme, 'programmes');
            $subjects = [];
            foreach ($subjectNames as $subjectCode => $subjectName) {
                $subject = Subject::firstOrCreate(['programme_id' => $programme->id, 'code' => 'DEMO-'.$subjectCode], ['name' => 'Demo '.$subjectName, 'status' => 'active']);
                $this->auditCreated($subject, 'subjects');
                $subjects[] = $subject;
            }
            $programmes[] = ['programme' => $programme, 'subjects' => $subjects];
        }

        return $programmes;
    }

    /** @param array<string, Campus> $campuses
     * @param  array<string, list<Room>>  $rooms
     * @param  array<string, list<User>>  $teachers
     * @param  list<array{year: AcademicYear, terms: list<AcademicTerm>}>  $years
     * @param  list<array{programme: Programme, subjects: list<Subject>}>  $programmes
     */
    private function seedClasses(array $campuses, array $rooms, array $teachers, array $years, array $programmes): void
    {
        foreach ($campuses as $key => $campus) {
            foreach ($years as $yearData) {
                foreach ($programmes as $index => $programmeData) {
                    $room = $rooms[$key][$index];
                    $class = SchoolClass::firstOrCreate(['campus_id' => $campus->id, 'academic_year_id' => $yearData['year']->id, 'code' => 'DEMO-'.$key.'-'.($index + 1)], [
                        'academic_term_id' => $yearData['terms'][$index]->id,
                        'programme_id' => $programmeData['programme']->id,
                        'subject_id' => $programmeData['subjects'][$index]->id,
                        'room_id' => $room->id, 'capacity' => min(16 + $index * 2, $room->capacity),
                        'name' => 'Demo '.$key.' '.$programmeData['programme']->name.' '.$yearData['year']->starts_on->format('Y'),
                        'status' => $campus->status === 'active' && $yearData['year']->status === 'active' ? 'active' : 'inactive',
                    ]);
                    if ($class->wasRecentlyCreated) {
                        $candidates = [$teachers[$key][$index % 2]];
                        if ($index === 0 && isset($teachers[$key][2])) {
                            $candidates[] = $teachers[$key][2];
                        }
                        $teacherIds = collect($candidates)->filter(fn (User $teacher): bool => $teacher->is_active && $teacher->hasAnyRole('teacher') && $teacher->campuses()->whereKey($campus->id)->exists())->pluck('id')->all();
                        $class->teachers()->sync($teacherIds);
                        $this->auditCreated($class, 'classes', $campus->id, [...$class->getFillable(), 'teacher_ids']);
                    }
                }
            }
        }
    }

    /** @param array<string, Campus> $campuses */
    private function seedClosures(array $campuses): void
    {
        foreach ($campuses as $campus) {
            foreach ([
                ['Demo term break 2026', 'holiday', '2026-03-20', '2026-03-22'],
                ['Demo staff training day 2026', 'closure', '2026-07-15', '2026-07-15'],
                ['Demo planned maintenance 2027', 'closure', '2027-01-01', '2027-01-03'],
            ] as [$name, $kind, $start, $end]) {
                $closure = CampusClosure::firstOrCreate(['campus_id' => $campus->id, 'name' => $name, 'starts_on' => $start, 'ends_on' => $end], ['kind' => $kind, 'status' => $campus->status]);
                $this->auditCreated($closure, 'closures', $campus->id);
            }
        }
    }

    private function seedStudents(): void
    {
        foreach (range(1, 60) as $number) {
            $student = Student::withTrashed()->firstOrCreate(['student_number' => sprintf('DEMO-%04d', $number)], [
                'full_name' => sprintf('Demo Learner %02d', $number),
                'email' => sprintf('learner.%02d@demo.ems.test', $number),
                'phone' => sprintf('+6000001%04d', $number),
                'enrollment_date' => sprintf('2026-%02d-05', (($number - 1) % 9) + 1),
            ]);
            if ($student->wasRecentlyCreated) {
                if ($number % 10 === 0) {
                    $student->delete();
                }
                $this->auditCreated($student, 'students', null, [...$student->getFillable(), 'deleted_at']);
            }
        }
    }

    /** @param list<string>|null $fields */
    private function auditCreated(Model $record, string $type, ?int $campusId = null, ?array $fields = null): void
    {
        if (! $record->wasRecentlyCreated) {
            return;
        }

        AuditLog::create([
            'actor_user_id' => null, 'campus_id' => $campusId,
            'subject_type' => $type, 'subject_id' => $record->getKey(),
            'action' => 'demo_seeded',
            'changed_fields' => array_values(array_diff($fields ?? $record->getFillable(), ['password', 'remember_token'])),
        ]);
    }
}
