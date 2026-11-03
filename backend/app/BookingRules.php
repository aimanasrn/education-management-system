<?php

namespace App;

use App\Models\Campus;
use App\Models\CampusBooking;
use App\Models\CampusClosure;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingRules
{
    public static function validate(User $user, array $input, ?CampusBooking $existing = null): array
    {
        $data = Validator::make($input, [
            'room_id' => ['required', 'integer', 'min:1'], 'kind' => ['required', Rule::in(['booking', 'event', 'maintenance'])],
            'title' => ['required', 'string', 'max:255'], 'purpose' => ['nullable', 'string', 'max:3000'], 'attendees' => ['required', 'integer', 'min:1', 'max:10000'],
            'priority' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'starts_local' => ['required', 'date_format:Y-m-d\\TH:i'], 'ends_local' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_local'],
        ])->validate();
        $room = Room::with('campus')->findOrFail($data['room_id']);
        abort_unless($user->isGlobalAdmin() || in_array($room->campus_id, BookingAccess::campusIds($user), true), 403);
        if ($existing && $room->campus_id !== $existing->campus_id) {
            throw ValidationException::withMessages(['room_id' => ['A booking cannot move between campuses. Cancel it and make a new request.']]);
        }
        if ($room->campus->status !== 'active' || ($data['kind'] !== 'maintenance' && $room->status !== 'active')) {
            throw ValidationException::withMessages(['room_id' => ['Choose an active campus and an available room.']]);
        }
        if ($data['kind'] !== 'maintenance' && $data['attendees'] > $room->capacity) {
            throw ValidationException::withMessages(['attendees' => ['Attendance cannot exceed the room capacity.']]);
        }
        $start = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['starts_local'], 'Asia/Kuala_Lumpur');
        $end = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['ends_local'], 'Asia/Kuala_Lumpur');
        if ($start->lt(now('Asia/Kuala_Lumpur')) || $start->gt(now('Asia/Kuala_Lumpur')->addYear())) {
            throw ValidationException::withMessages(['starts_local' => ['Choose a future start within the next year.']]);
        }
        if ($data['kind'] === 'maintenance') {
            if ($start->diffInHours($end) > 168) {
                throw ValidationException::withMessages(['ends_local' => ['Maintenance blocks may last up to seven days.']]);
            }
        } elseif (! $start->isSameDay($end) || $start->diffInHours($end) > 12) {
            throw ValidationException::withMessages(['ends_local' => ['Bookings and events must fit within one day and last at most twelve hours.']]);
        }
        self::availability($room->campus, $data['kind'], $start, $end);
        unset($data['starts_local'],$data['ends_local']);

        return [...$data, 'campus_id' => $room->campus_id, 'starts_at' => $start->utc(), 'ends_at' => $end->utc()];
    }

    public static function availability(Campus $campus, string $kind, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($kind === 'maintenance') {
            return;
        }
        $hours = $campus->operating_hours[strtolower($start->format('D'))] ?? null;
        if (! $hours || ($hours['closed'] ?? true) || $start->format('H:i') < ($hours['opens'] ?? '') || $end->format('H:i') > ($hours['closes'] ?? '')) {
            throw ValidationException::withMessages(['starts_local' => ['This time is outside campus operating hours.']]);
        }
        if (CampusClosure::where('campus_id', $campus->id)->where('status', 'active')->whereDate('starts_on', '<=', $end->toDateString())->whereDate('ends_on', '>=', $start->toDateString())->exists()) {
            throw ValidationException::withMessages(['starts_local' => ['The campus is closed during this period.']]);
        }
    }

    public static function conflict(array $data, ?int $ignore = null): void
    {
        $query = CampusBooking::where('room_id', $data['room_id'])->where('status', 'confirmed')->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);
        if ($ignore) {
            $query->where('id', '!=', $ignore);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['starts_local' => ['This room is already reserved during the requested time. Choose another room or time.']]);
        }
    }
}
