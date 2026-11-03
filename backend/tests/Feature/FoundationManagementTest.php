<?php

namespace Tests\Feature;

use App\FoundationCatalog;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Programme;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationManagementTest extends TestCase
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

    public static function resources(): array
    {
        return array_map(fn ($resource) => [$resource], array_keys(FoundationCatalog::all()));
    }

    #[DataProvider('resources')]
    public function test_all_catalogues_require_authentication_and_reject_students(string $resource): void
    {
        $this->getJson('/api/foundation/'.$resource)->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/foundation/'.$resource)->assertForbidden();
        $this->postJson('/api/foundation/'.$resource, [])->assertForbidden();
    }

    public function test_admin_can_create_update_and_search_campus_without_auditing_values(): void
    {
        $admin = User::factory()->admin()->create();
        $data = Campus::factory()->make(['code' => ' central ', 'name' => '100% Learning'])->toArray();
        $this->actingAs($admin, 'sanctum');
        $created = $this->postJson('/api/foundation/campuses', $data)->assertCreated()->assertJsonPath('data.code', 'CENTRAL');
        $id = $created->json('data.id');
        $data['name'] = 'Revised campus';
        $this->putJson('/api/foundation/campuses/'.$id, $data)->assertOk();
        $this->getJson('/api/foundation/campuses?q=Revised&sort=-name')->assertOk()->assertJsonPath('meta.total', 1);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame(['name'], $audit->changed_fields);
        $this->assertStringNotContainsString('Revised campus', $audit->toJson());
        $this->deleteJson('/api/foundation/campuses/'.$id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('campuses', ['id' => $id]);
    }

    public function test_campus_manager_cannot_read_or_write_another_campus_or_create_campuses(): void
    {
        $own = Campus::factory()->create();
        $other = Campus::factory()->create();
        $ownRoom = Room::factory()->create(['campus_id' => $own->id]);
        $otherRoom = Room::factory()->create(['campus_id' => $other->id]);
        $this->actingAs($this->role('campus_manager', [$own->id]), 'sanctum');
        $this->getJson('/api/foundation/campuses')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('access.create', false);
        $this->getJson('/api/foundation/rooms')->assertOk()->assertJsonPath('data.0.id', $ownRoom->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/foundation/rooms/'.$otherRoom->id)->assertNotFound();
        $this->putJson('/api/foundation/rooms/'.$otherRoom->id, $otherRoom->toArray())->assertNotFound();
        $this->postJson('/api/foundation/rooms', Room::factory()->make(['campus_id' => $other->id])->toArray())->assertForbidden();
        $this->postJson('/api/foundation/campuses', Campus::factory()->make()->toArray())->assertForbidden();
        $this->postJson('/api/foundation/programmes', Programme::factory()->make()->toArray())->assertForbidden();
        $options = $this->getJson('/api/foundation/rooms/options')->assertOk()->json('data.campus_id');
        $this->assertSame([$own->id], array_column($options, 'value'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_teacher_sees_only_assigned_classes_and_cannot_mutate_or_access_profiles(): void
    {
        $campus = Campus::factory()->create();
        $teacher = $this->role('teacher', [$campus->id]);
        $own = SchoolClass::factory()->create(['campus_id' => $campus->id]);
        $other = SchoolClass::factory()->create(['campus_id' => $campus->id]);
        $own->teachers()->attach($teacher);
        $this->actingAs($teacher, 'sanctum');
        $this->getJson('/api/foundation/classes')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $own->id)->assertJsonPath('access.edit', false);
        $this->getJson('/api/foundation/classes/'.$other->id)->assertNotFound();
        $this->putJson('/api/foundation/classes/'.$own->id, [])->assertForbidden();
        $this->getJson('/api/students')->assertForbidden();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonFragment(['key' => 'classes', 'label' => 'Active classes', 'value' => 1]);
        $teacher->campuses()->detach();
        $this->getJson('/api/foundation/classes')->assertJsonPath('meta.total', 0);
    }

    public function test_teacher_options_do_not_disclose_assignments_outside_the_managers_campuses(): void
    {
        $own = Campus::factory()->create();
        $other = Campus::factory()->create();
        $teacher = $this->role('teacher', [$own->id, $other->id]);
        $this->actingAs($this->role('campus_manager', [$own->id]), 'sanctum');
        $this->getJson('/api/foundation/classes/options')->assertOk()
            ->assertJsonPath('data.teacher_ids.0.value', $teacher->id)
            ->assertJsonPath('data.teacher_ids.0.campus_ids', [$own->id]);
    }

    public function test_room_building_must_match_campus_and_codes_are_unique_per_campus(): void
    {
        $campus = Campus::factory()->create();
        $building = Building::factory()->create();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $data = Room::factory()->make(['campus_id' => $campus->id, 'building_id' => $building->id])->toArray();
        $this->postJson('/api/foundation/rooms', $data)->assertUnprocessable()->assertJsonValidationErrors('building_id');
        $data['building_id'] = null;
        $this->postJson('/api/foundation/rooms', $data)->assertCreated();
        $this->postJson('/api/foundation/rooms', $data)->assertUnprocessable()->assertJsonValidationErrors('code');
        $data['campus_id'] = $building->campus_id;
        $this->postJson('/api/foundation/rooms', $data)->assertCreated();
    }

    public function test_terms_must_fit_year_and_year_cannot_exclude_existing_terms(): void
    {
        $year = AcademicYear::factory()->create();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $data = AcademicTerm::factory()->make(['academic_year_id' => $year->id, 'ends_on' => '2027-01-01'])->toArray();
        $this->postJson('/api/foundation/terms', $data)->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $data['ends_on'] = '2026-06-30';
        $this->postJson('/api/foundation/terms', $data)->assertCreated();
        $this->putJson('/api/foundation/years/'.$year->id, [...$year->toArray(), 'ends_on' => '2026-04-01'])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'ends_on' => '2026-12-31']);
    }

    public function test_classes_validate_term_subject_room_capacity_and_teacher_campus(): void
    {
        $campus = Campus::factory()->create();
        $room = Room::factory()->create(['campus_id' => $campus->id, 'capacity' => 10]);
        $teacher = $this->role('teacher', [$campus->id]);
        $year = AcademicYear::factory()->create();
        $term = AcademicTerm::factory()->create(['academic_year_id' => $year->id]);
        $programme = Programme::factory()->create();
        $data = ['campus_id' => $campus->id, 'academic_year_id' => $year->id, 'academic_term_id' => $term->id, 'programme_id' => $programme->id, 'subject_id' => null, 'room_id' => $room->id, 'code' => 'CLASS-A', 'name' => 'Class A', 'capacity' => 11, 'status' => 'active', 'teacher_ids' => [$teacher->id]];
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->postJson('/api/foundation/classes', $data)->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $data['capacity'] = 10;
        $wrongTerm = AcademicTerm::factory()->create();
        $this->postJson('/api/foundation/classes', [...$data, 'academic_term_id' => $wrongTerm->id])->assertUnprocessable()->assertJsonValidationErrors('academic_term_id');
        $subject = Subject::factory()->create();
        $this->postJson('/api/foundation/classes', [...$data, 'subject_id' => $subject->id])->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $otherTeacher = $this->role('teacher');
        $this->postJson('/api/foundation/classes', [...$data, 'teacher_ids' => [$otherTeacher->id]])->assertUnprocessable()->assertJsonValidationErrors('teacher_ids');
        $created = $this->postJson('/api/foundation/classes', $data)->assertCreated()->assertJsonPath('data.teacher_ids.0', $teacher->id);
        $this->putJson('/api/foundation/rooms/'.$room->id, [...$room->toArray(), 'capacity' => 9])->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->assertDatabaseHas('class_teachers', ['school_class_id' => $created->json('data.id'), 'user_id' => $teacher->id]);
    }

    public function test_operating_hours_and_closure_dates_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $data = Campus::factory()->make()->toArray();
        $data['operating_hours']['mon'] = ['closed' => false, 'opens' => '18:00', 'closes' => '09:00'];
        $this->postJson('/api/foundation/campuses', $data)->assertUnprocessable()->assertJsonValidationErrors('operating_hours.mon.closes');
        $data['operating_hours']['mon']['closes'] = '20:00';
        $id = $this->postJson('/api/foundation/campuses', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/foundation/closures', ['campus_id' => $id, 'name' => 'Closure', 'kind' => 'closure', 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-09', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('ends_on');
    }

    public function test_search_treats_wildcards_literally_and_unknown_resources_are_not_exposed(): void
    {
        Campus::factory()->create(['name' => '100% Campus']);
        Campus::factory()->create(['name' => 'Other campus']);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->getJson('/api/foundation/campuses?q=%25')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/foundation/users')->assertNotFound();
        $this->postJson('/api/foundation/users', [])->assertNotFound();
        $this->getJson('/api/foundation/campuses?sort=password')->assertUnprocessable();
        $this->getJson('/api/foundation/campuses/'.str_repeat('9', 40))->assertNotFound();
        $this->putJson('/api/system/users/'.str_repeat('9', 40), [])->assertNotFound();
    }

    public function test_failed_write_has_no_partial_audit_and_relationships_cannot_be_moved(): void
    {
        $building = Building::factory()->create();
        $other = Campus::factory()->create();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->putJson('/api/foundation/buildings/'.$building->id, [...$building->toArray(), 'campus_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('campus_id');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseHas('buildings', ['id' => $building->id, 'campus_id' => $building->campus_id]);
    }
}
