<?php

namespace App;

use App\Models\CampusBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BookingAccess
{
    public static function campusIds(User $user): array
    {
        $ids = $user->campuses()->pluck('campuses.id')->all();
        if ($user->hasPermission('learning.view')) {
            $today = now('Asia/Kuala_Lumpur')->toDateString();
            $extra = LearningAccess::enrolments($user)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->with('schoolClass')->get()->pluck('schoolClass.campus_id')->all();
            $ids = array_merge($ids, $extra);
        }

        return array_values(array_unique($ids));
    }

    public static function query(User $user): Builder
    {
        abort_unless($user->hasPermission('bookings.view'), 403);
        $query = CampusBooking::query();
        if (! $user->isGlobalAdmin()) {
            $query->whereIn('campus_id', self::campusIds($user));
        }
        if (! $user->hasPermission('bookings.manage')) {
            $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('status', 'confirmed'));
        }

        return $query;
    }

    public static function canManage(User $user, CampusBooking $booking): bool
    {
        return $user->hasPermission('bookings.manage') && ($user->isGlobalAdmin() || in_array($booking->campus_id, self::campusIds($user), true));
    }
}
