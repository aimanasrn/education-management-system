<?php

namespace Database\Seeders;

use App\BookingRules;
use App\Models\AuditLog;
use App\Models\CampusBooking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CampusBookingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Booking demo data is restricted to local/testing environments.');
        }
        DB::transaction(function (): void {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $admin = User::where('email', 'admin@demo.ems.test')->first();
            $teacher = User::where('email', 'teacher.kl.1@demo.ems.test')->first();
            $room = Room::whereHas('campus', fn ($q) => $q->where('code', 'DEMO-KL')->where('status', 'active'))->where('status', 'active')->orderBy('id')->first();
            if (! $admin || ! $admin->isGlobalAdmin() || ! $teacher || ! $room) {
                throw new RuntimeException('Run the base DemoSeeder first.');
            }
            $day = CarbonImmutable::now('Asia/Kuala_Lumpur')->next('Monday')->format('Y-m-d');
            foreach ([['booking', 'Demo facility request', 'requested', 9, 10, $teacher], ['event', 'Demo campus open day', 'confirmed', 11, 12, $admin], ['maintenance', 'Demo scheduled maintenance', 'confirmed', 19, 20, $admin]] as $index => [$kind,$title,$status,$start,$end,$creator]) {
                $reference = 'DEMO-BKG-'.($index + 1);
                if (CampusBooking::where('reference', $reference)->exists()) {
                    continue;
                }
                $data = BookingRules::validate($admin, ['room_id' => $room->id, 'kind' => $kind, 'title' => $title, 'purpose' => 'Reserved demo example for the campus booking portal.', 'attendees' => min(10, $room->capacity), 'priority' => 'normal', 'starts_local' => $day.'T'.sprintf('%02d:00', $start), 'ends_local' => $day.'T'.sprintf('%02d:00', $end)]);
                if ($status === 'confirmed') {
                    BookingRules::conflict($data);
                }
                $b = CampusBooking::create([...$data, 'reference' => $reference, 'created_by' => $creator->id, 'reviewed_by' => $status === 'confirmed' ? $admin->id : null, 'status' => $status]);
                DB::table('campus_booking_changes')->insert(['campus_booking_id' => $b->id, 'actor_user_id' => $creator->id, 'action' => 'created', 'before' => null, 'after' => json_encode(['room_id' => $b->room_id, 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(), 'status' => $status]), 'created_at' => now()]);
                AuditLog::create(['actor_user_id' => null, 'campus_id' => $b->campus_id, 'subject_type' => 'bookings', 'subject_id' => $b->id, 'action' => 'demo_seeded', 'changed_fields' => ['demo_record']]);
            }
        });
    }
}
