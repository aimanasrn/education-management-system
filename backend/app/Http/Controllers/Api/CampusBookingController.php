<?php

namespace App\Http\Controllers\Api;

use App\BookingAccess;
use App\BookingRules;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CampusBooking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CampusBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'campus_id' => ['nullable', 'integer'], 'room_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['all', 'requested', 'confirmed', 'declined', 'cancelled', 'completed'])], 'booking_type' => ['nullable', Rule::in(['all', 'booking', 'event', 'maintenance'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $user = $request->user();
        $query = BookingAccess::query($user)->with(['room', 'campus', 'creator']);
        foreach (['campus_id', 'room_id'] as $key) {
            if (! empty($f[$key])) {
                $query->where($key, $f[$key]);
            }
        }
        if (($f['status'] ?? 'all') !== 'all') {
            $query->where('status', $f['status']);
        }
        if (($f['booking_type'] ?? 'all') !== 'all') {
            $query->where('kind', $f['booking_type']);
        }
        if (! empty($f['from'])) {
            $query->where('ends_at', '>', CarbonImmutable::parse($f['from'], 'Asia/Kuala_Lumpur')->startOfDay()->utc());
        }
        if (! empty($f['to'])) {
            $query->where('starts_at', '<', CarbonImmutable::parse($f['to'], 'Asia/Kuala_Lumpur')->addDay()->startOfDay()->utc());
        }
        if (! empty($f['q'])) {
            if (! $user->hasPermission('bookings.manage')) {
                $query->where('created_by', $user->id);
            }
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($f['q']))).'%';
            $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern]);
        }
        $result = $query->orderBy('starts_at')->orderBy('id')->paginate(25);

        return response()->json(['data' => $result->getCollection()->map(fn ($b) => $this->payload($user, $b)), 'meta' => ['total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()]]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('bookings.request'), 403);
        $rooms = Room::with('campus')->whereHas('campus', fn ($q) => $q->where('status', 'active'));
        if (! $user->isGlobalAdmin()) {
            $rooms->whereIn('campus_id', BookingAccess::campusIds($user));
        }
        abort_if((clone $rooms)->count() > 2000, 422, 'Too many room options. Contact your administrator.');

        return response()->json(['data' => ['room_id' => $rooms->orderBy('name')->get()->map(fn ($r) => ['value' => $r->id, 'label' => $r->name.' — '.$r->campus->name.' ('.$r->code.')'.($r->status === 'inactive' ? ' · Inactive' : ''), 'campus_id' => $r->campus_id, 'capacity' => $r->capacity])], 'access' => ['manage' => $user->hasPermission('bookings.manage')]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $b = BookingAccess::query($request->user())->with(['room', 'campus', 'creator'])->findOrFail($id);
        $payload = $this->payload($request->user(), $b);
        $payload['history'] = $payload['access']['private'] ? DB::table('campus_booking_changes')->where('campus_booking_id', $id)->orderBy('id')->get(['id', 'action', 'reason', 'before', 'after', 'created_at'])->map(fn ($change) => ['id' => $change->id, 'action' => $change->action, 'reason' => $change->reason, 'before' => json_decode($change->before, true), 'after' => json_decode($change->after, true), 'created_at' => $change->created_at]) : [];

        return response()->json(['data' => $payload]);
    }

    public function store(Request $request): JsonResponse
    {
        $repeat = $request->validate(['repeat_weeks' => ['sometimes', 'integer', 'min:1', 'max:12']])['repeat_weeks'] ?? 1;
        $created = DB::transaction(function () use ($request, $repeat) {
            $actor = $this->actor($request);
            abort_unless($actor->hasPermission('bookings.request'), 403);
            $input = $request->all();
            $records = [];
            $series = $repeat > 1 ? (string) Str::uuid() : null;
            for ($i = 0; $i < $repeat; $i++) {
                $data = BookingRules::validate($actor, $input);
                if ($actor->hasPermission('bookings.manage')) {
                    BookingRules::conflict($data);
                }
                $b = CampusBooking::create([...$data, 'status' => $actor->hasPermission('bookings.manage') ? 'confirmed' : 'requested', 'created_by' => $actor->id, 'reviewed_by' => $actor->hasPermission('bookings.manage') ? $actor->id : null, 'series_id' => $series]);
                $this->record($actor, $b, 'created', null, null);
                $records[] = $b;
                $input['starts_local'] = $data['starts_at']->setTimezone('Asia/Kuala_Lumpur')->addWeek()->format('Y-m-d\\TH:i');
                $input['ends_local'] = $data['ends_at']->setTimezone('Asia/Kuala_Lumpur')->addWeek()->format('Y-m-d\\TH:i');
            }

            return $records;
        });

        return response()->json(['data' => $this->payload($request->user(), $created[0]->load(['room', 'campus', 'creator'])), 'created_count' => count($created)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $b = DB::transaction(function () use ($request, $id, $reason) {
            $actor = $this->actor($request);
            $b = BookingAccess::query($actor)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($b->status, ['requested', 'confirmed'], true) && ($actor->hasPermission('bookings.manage') || ($b->created_by === $actor->id && $b->status === 'requested')), 403);
            abort_if($b->starts_at->lte(now()), 409, 'Past or started bookings cannot be rescheduled.');
            $data = BookingRules::validate($actor, $request->all(), $b);
            if ($b->status === 'confirmed') {
                BookingRules::conflict($data, $b->id);
            }
            $before = $this->snapshot($b);
            $b->fill($data);
            $b->decision_reason = $reason;
            $b->save();
            $this->record($actor, $b, 'rescheduled', $before, $reason);

            return $b;
        });

        return response()->json(['data' => $this->payload($request->user(), $b->load(['room', 'campus', 'creator']))]);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'decline', 'cancel', 'complete'])], 'reason' => ['required', 'string', 'max:1000']]);
        $b = DB::transaction(function () use ($request, $id, $data) {
            $actor = $this->actor($request);
            $b = BookingAccess::query($actor)->lockForUpdate()->findOrFail($id);
            $manager = BookingAccess::canManage($actor, $b);
            abort_unless($manager || ($b->created_by === $actor->id && $data['action'] === 'cancel'), 403);
            $allowed = ['approve' => ['requested'], 'decline' => ['requested'], 'cancel' => ['requested', 'confirmed'], 'complete' => ['confirmed']];
            abort_unless(in_array($b->status, $allowed[$data['action']], true), 409, 'This action is unavailable for the current status.');
            if ($data['action'] === 'complete') {
                abort_if($b->ends_at->gt(now()), 409, 'Complete a reservation only after its end time.');
            }
            if ($data['action'] === 'approve') {
                $checked = BookingRules::validate($actor, ['room_id' => $b->room_id, 'kind' => $b->kind, 'title' => $b->title, 'purpose' => $b->purpose, 'attendees' => $b->attendees, 'priority' => $b->priority, 'starts_local' => $b->starts_at->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i'), 'ends_local' => $b->ends_at->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i')], $b);
                BookingRules::conflict($checked, $b->id);
            }
            $before = $this->snapshot($b);
            $b->fill(['status' => ['approve' => 'confirmed', 'decline' => 'declined', 'cancel' => 'cancelled', 'complete' => 'completed'][$data['action']], 'decision_reason' => $data['reason']]);
            if ($manager) {
                $b->reviewed_by = $actor->id;
            }
            $b->save();
            $this->record($actor, $b, $data['action'], $before, $data['reason']);

            return $b;
        });

        return response()->json(['data' => $this->payload($request->user(), $b->load(['room', 'campus', 'creator']))]);
    }

    private function actor(Request $request): User
    {
        DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
        $user = User::with('roleAssignments')->findOrFail($request->user()->id);
        abort_unless($user->is_active, 403);

        return $user;
    }

    private function snapshot(CampusBooking $b): array
    {
        return ['room_id' => $b->room_id, 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(), 'status' => $b->status];
    }

    private function record(User $actor, CampusBooking $b, string $action, ?array $before, ?string $reason): void
    {
        DB::table('campus_booking_changes')->insert(['campus_booking_id' => $b->id, 'actor_user_id' => $actor->id, 'action' => $action, 'reason' => $reason, 'before' => $before ? json_encode($before) : null, 'after' => json_encode($this->snapshot($b)), 'created_at' => now()]);
        AuditLog::create(['actor_user_id' => $actor->id, 'campus_id' => $b->campus_id, 'subject_type' => 'bookings', 'subject_id' => $b->id, 'action' => $action === 'created' ? 'created' : 'updated', 'changed_fields' => $action === 'created' ? ['room_id', 'title', 'kind', 'starts_at', 'ends_at', 'status'] : ['room_id', 'starts_at', 'ends_at', 'status', 'decision_reason']]);
    }

    private function payload(User $user, CampusBooking $b): array
    {
        $manager = $user->hasPermission('bookings.manage');
        $private = $manager || $b->created_by === $user->id;

        return ['id' => $b->id, 'campus_id' => $b->campus_id, 'room_id' => $b->room_id, 'room' => $b->room->name, 'campus' => $b->campus->name, 'kind' => $private ? $b->kind : 'booking', 'title' => $private ? $b->title : 'Reserved', 'purpose' => $private ? $b->purpose : null, 'attendees' => $private ? $b->attendees : null, 'priority' => $private ? $b->priority : null, 'created_by' => $private ? $b->creator->name : null, 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(), 'starts_local' => $b->starts_at->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i'), 'ends_local' => $b->ends_at->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\\TH:i'), 'status' => $b->status, 'series_id' => $private ? $b->series_id : null, 'decision_reason' => $private ? $b->decision_reason : null, 'access' => ['private' => $private, 'edit' => in_array($b->status, ['requested', 'confirmed'], true) && $b->starts_at->gt(now()) && ($manager || ($b->created_by === $user->id && $b->status === 'requested')), 'review' => $manager && $b->status === 'requested', 'cancel' => $private && in_array($b->status, ['requested', 'confirmed'], true), 'complete' => $manager && $b->status === 'confirmed' && $b->ends_at->lte(now())]];
    }
}
