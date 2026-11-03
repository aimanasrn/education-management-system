<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CampusBooking;
use App\Models\CampusClosure;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\StudentEnrolment;
use App\Models\StudentGuardian;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CampusBookingSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampusBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function role(string $role, array $campuses = []): User
    {
        $u = User::factory()->create();
        $u->roleAssignments()->delete();
        $u->roleAssignments()->create(['role' => $role]);
        $u->campuses()->sync($campuses);

        return $u->fresh();
    }

    private function room(): Room
    {
        $hours = collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])->mapWithKeys(fn ($d) => [$d => ['closed' => false, 'opens' => '08:00', 'closes' => '18:00']])->all();

        return Room::factory()->create(['campus_id' => Campus::factory()->create(['operating_hours' => $hours])->id, 'capacity' => 30]);
    }

    private function input(Room $r, array $extra = []): array
    {
        return [...['room_id' => $r->id, 'kind' => 'booking', 'title' => 'Lecture hall reservation', 'purpose' => 'Private applicant meeting', 'attendees' => 10, 'priority' => 'normal', 'starts_local' => now('Asia/Kuala_Lumpur')->addDays(2)->format('Y-m-d').'T10:00', 'ends_local' => now('Asia/Kuala_Lumpur')->addDays(2)->format('Y-m-d').'T11:00'], ...$extra];
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    }

    public function test_authentication_and_campus_scope(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
        $r = $this->room();
        $other = $this->room();
        $this->actingAs($this->role('teacher', [$r->campus_id]), 'sanctum');
        $this->postJson('/api/bookings', $this->input($other))->assertForbidden();
        $this->getJson('/api/bookings/options')->assertOk()->assertJsonCount(1, 'data.room_id');
        $this->postJson('/api/bookings', $this->input($r))->assertCreated()->assertJsonPath('data.status', 'requested');
    }

    public function test_manager_booking_is_confirmed_and_conflicting_intervals_rejected_with_adjacent_allowed(): void
    {
        $this->admin();
        $r = $this->room();
        $data = $this->input($r);
        $start = $data['starts_local'];
        $this->postJson('/api/bookings', $data)->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.starts_local', $start);
        $this->postJson('/api/bookings', $data)->assertUnprocessable()->assertJsonValidationErrors('starts_local');
        $this->postJson('/api/bookings', [...$data, 'starts_local' => substr($start, 0, 10).'T11:00', 'ends_local' => substr($start, 0, 10).'T12:00'])->assertCreated();
        $this->assertDatabaseCount('campus_bookings', 2);
    }

    public function test_requests_do_not_reserve_until_manager_approval_and_approval_rechecks_conflicts(): void
    {
        $r = $this->room();
        $teacher = $this->role('teacher', [$r->campus_id]);
        $this->actingAs($teacher, 'sanctum');
        $data = $this->input($r);
        $id = $this->postJson('/api/bookings', $data)->assertCreated()->json('data.id');
        $this->postJson("/api/bookings/$id/transition", ['action' => 'approve', 'reason' => 'Self review'])->assertForbidden();
        $this->admin();
        $second = $this->postJson('/api/bookings', $data)->assertCreated()->json('data.id');
        $this->postJson("/api/bookings/$id/transition", ['action' => 'approve', 'reason' => 'Reviewed'])->assertUnprocessable();
        $this->postJson("/api/bookings/$second/transition", ['action' => 'cancel', 'reason' => 'Free the room'])->assertOk();
        $this->postJson("/api/bookings/$id/transition", ['action' => 'approve', 'reason' => 'Reviewed'])->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_private_details_are_masked_for_other_requesters_including_search_and_history(): void
    {
        $r = $this->room();
        $this->admin();
        $id = $this->postJson('/api/bookings', $this->input($r))->assertCreated()->json('data.id');
        $this->actingAs($this->role('teacher', [$r->campus_id]), 'sanctum');
        $this->getJson("/api/bookings/$id")->assertOk()->assertJsonPath('data.title', 'Reserved')->assertJsonPath('data.purpose', null)->assertJsonPath('data.created_by', null)->assertJsonCount(0, 'data.history');
        $this->getJson('/api/bookings?q=Lecture')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/bookings/$id/transition", ['action' => 'cancel', 'reason' => 'Not my booking'])->assertForbidden();
    }

    public function test_capacity_operating_hours_closures_and_past_dates(): void
    {
        $this->admin();
        $r = $this->room();
        $d = $this->input($r);
        $day = substr($d['starts_local'], 0, 10);
        $this->postJson('/api/bookings', [...$d, 'attendees' => 31])->assertUnprocessable()->assertJsonValidationErrors('attendees');
        $this->postJson('/api/bookings', [...$d, 'starts_local' => $day.'T07:00'])->assertUnprocessable();
        $this->postJson('/api/bookings', [...$d, 'starts_local' => '2020-01-01T10:00', 'ends_local' => '2020-01-01T11:00'])->assertUnprocessable();
        CampusClosure::factory()->create(['campus_id' => $r->campus_id, 'starts_on' => $day, 'ends_on' => $day, 'status' => 'active']);
        $this->postJson('/api/bookings', $d)->assertUnprocessable();
        $this->postJson('/api/bookings', [...$d, 'kind' => 'maintenance', 'starts_local' => $day.'T01:00', 'ends_local' => $day.'T03:00'])->assertCreated();
    }

    public function test_weekly_series_is_atomic_when_one_occurrence_conflicts(): void
    {
        $this->admin();
        $r = $this->room();
        $d = $this->input($r);
        $next = CarbonImmutable::parse($d['starts_local'])->addWeek()->format('Y-m-d');
        $this->postJson('/api/bookings', [...$d, 'starts_local' => $next.'T10:00', 'ends_local' => $next.'T11:00'])->assertCreated();
        $this->postJson('/api/bookings', [...$d, 'repeat_weeks' => 3])->assertUnprocessable();
        $this->assertDatabaseCount('campus_bookings', 1);
        $this->assertDatabaseCount('campus_booking_changes', 1);
        $this->postJson('/api/bookings', [...$d, 'starts_local' => substr($d['starts_local'], 0, 10).'T13:00', 'ends_local' => substr($d['ends_local'], 0, 10).'T14:00', 'repeat_weeks' => 3])->assertCreated()->assertJsonPath('created_count', 3);
        $this->assertDatabaseCount('campus_bookings', 4);
    }

    public function test_rescheduling_is_atomic_and_reasoned_history_is_preserved(): void
    {
        $this->admin();
        $r = $this->room();
        $d = $this->input($r);
        $id = $this->postJson('/api/bookings', $d)->assertCreated()->json('data.id');
        $day = substr($d['starts_local'], 0, 10);
        $this->putJson("/api/bookings/$id", [...$d, 'starts_local' => $day.'T12:00', 'ends_local' => $day.'T13:00', 'reason' => 'Event moved'])->assertOk()->assertJsonPath('data.starts_local', $day.'T12:00');
        $this->postJson("/api/bookings/$id/transition", ['action' => 'cancel', 'reason' => 'Event withdrawn'])->assertOk();
        $this->getJson("/api/bookings/$id")->assertOk()->assertJsonCount(3, 'data.history')->assertJsonPath('data.history.1.before.starts_at', CarbonImmutable::parse($day.'T10:00', 'Asia/Kuala_Lumpur')->utc()->toIso8601String());
        $this->postJson("/api/bookings/$id/transition", ['action' => 'approve', 'reason' => 'Reopen'])->assertConflict();
        $this->assertDatabaseCount('campus_bookings', 1);
    }

    public function test_linked_family_can_request_only_current_child_campus(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 10));
        $r = $this->room();
        $class = SchoolClass::factory()->create(['campus_id' => $r->campus_id]);
        $enrol = StudentEnrolment::factory()->create(['school_class_id' => $class->id]);
        $guardian = $this->role('guardian');
        StudentGuardian::factory()->create(['student_id' => $enrol->student_id, 'user_id' => $guardian->id]);
        $this->actingAs($guardian, 'sanctum')->postJson('/api/bookings', $this->input($r))->assertCreated()->assertJsonPath('data.status', 'requested');
        $this->postJson('/api/bookings', $this->input($this->room()))->assertForbidden();
    }

    public function test_room_capacity_and_closure_changes_cannot_invalidate_confirmed_bookings(): void
    {
        $this->admin();
        $r = $this->room();
        $d = $this->input($r);
        $this->postJson('/api/bookings', $d)->assertCreated();
        $this->putJson('/api/foundation/rooms/'.$r->id, [...$r->toArray(), 'capacity' => 5])->assertUnprocessable();
        $this->putJson('/api/foundation/rooms/'.$r->id, [...$r->toArray(), 'status' => 'inactive'])->assertUnprocessable();
        $day = substr($d['starts_local'], 0, 10);
        $this->postJson('/api/foundation/closures', ['campus_id' => $r->campus_id, 'name' => 'New closure', 'kind' => 'closure', 'starts_on' => $day, 'ends_on' => $day, 'status' => 'active'])->assertUnprocessable();
        $hours = $r->campus->operating_hours;
        $hours[strtolower(CarbonImmutable::parse($day)->format('D'))]['closed'] = true;
        $this->putJson('/api/foundation/campuses/'.$r->campus_id, [...$r->campus->toArray(), 'operating_hours' => $hours])->assertUnprocessable()->assertJsonValidationErrors('operating_hours');
    }

    public function test_booking_demo_seeder_preserves_decisions_and_does_not_duplicate_records(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->seed(CampusBookingSeeder::class);
        $this->assertDatabaseCount('campus_bookings', 3);
        $this->assertDatabaseCount('campus_booking_changes', 3);
        CampusBooking::where('reference', 'DEMO-BKG-1')->first()->update(['status' => 'declined']);
        $before = CampusBooking::all()->toArray();
        $this->seed(CampusBookingSeeder::class);
        $this->assertSame($before, CampusBooking::all()->toArray());
        $this->app->instance('env', 'production');
        try {
            $this->app->make(CampusBookingSeeder::class)->run();
            $this->fail('Production seeding was not rejected.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('local/testing', $error->getMessage());
        }
    }
}
