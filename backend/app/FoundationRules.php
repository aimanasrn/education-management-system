<?php

namespace App;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\CampusBooking;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\StudentEnrolment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FoundationRules
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validate(User $user, string $resource, array $input, ?Model $record): array
    {
        $config = FoundationCatalog::get($resource);
        $campus = $input['campus_id'] ?? ($resource === 'campuses' ? $record?->id : null);
        if (! $user->isGlobalAdmin() && $campus !== null) {
            abort_unless($user->campuses()->whereKey($campus)->exists(), 403);
        }
        $table = (new $config['model'])->getTable();
        $rules = [];
        foreach ($config['fields'] as $field) {
            $rules[$field] = match ($field) {
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'email' => ['nullable', 'email', 'max:254'],
                'phone' => ['nullable', 'string', 'max:30'],
                'address', 'description' => ['nullable', 'string', 'max:3000'],
                'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
                'kind' => ['required', Rule::in(['holiday', 'closure'])],
                'facilities' => ['present', 'array', 'max:30'],
                'teacher_ids' => ['present', 'array', 'max:50'],
                'operating_hours' => ['present', 'array:mon,tue,wed,thu,fri,sat,sun', 'size:7'],
                default => [in_array($field, ['building_id', 'subject_id', 'room_id'], true) ? 'nullable' : 'required', 'integer'],
            };
            if ($reference = FoundationCatalog::reference($field)) {
                $referenceTable = (new (FoundationCatalog::get($reference)['model']))->getTable();
                $exists = Rule::exists($referenceTable, 'id');
                if (in_array($field, ['building_id', 'room_id'], true)) {
                    $exists->where('campus_id', $campus);
                } elseif ($field === 'academic_term_id') {
                    $exists->where('academic_year_id', $input['academic_year_id'] ?? 0);
                } elseif ($field === 'subject_id') {
                    $exists->where('programme_id', $input['programme_id'] ?? 0);
                }
                $rules[$field][] = $exists;
            }
        }
        if (isset($rules['code'])) {
            $unique = Rule::unique($table, 'code')->ignore($record?->id);
            foreach (match ($resource) {
                'buildings', 'rooms' => ['campus_id'],
                'terms' => ['academic_year_id'],
                'subjects' => ['programme_id'],
                'classes' => ['campus_id', 'academic_year_id'],
                default => [],
            } as $scope) {
                $unique->where($scope, $input[$scope] ?? 0);
            }
            $rules['code'][] = $unique;
        }
        if ($resource === 'rooms') {
            $rules['facilities.*'] = ['required', 'string', 'max:100', 'distinct'];
        }
        if ($resource === 'classes') {
            $rules['teacher_ids.*'] = [
                'required', 'integer', 'distinct',
                Rule::exists('users', 'id')->where('is_active', true),
            ];
        }
        if ($resource === 'campuses') {
            foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
                $key = 'operating_hours.'.$day;
                $rules[$key] = ['required', 'array:closed,opens,closes'];
                $rules[$key.'.closed'] = ['required', 'boolean'];
                $rules[$key.'.opens'] = ['nullable', 'required_if:'.$key.'.closed,false', 'date_format:H:i'];
                $rules[$key.'.closes'] = ['nullable', 'required_if:'.$key.'.closed,false', 'date_format:H:i'];
            }
        }
        $data = Validator::make($input, $rules)->validate();
        $errors = [];
        if ($record) {
            foreach ($config['fields'] as $field) {
                if (FoundationCatalog::reference($field) && (string) $record->{$field} !== (string) ($data[$field] ?? '')) {
                    $errors[$field] = ['This relationship cannot be changed. Deactivate this record and create its replacement to preserve history.'];
                }
            }
        }
        if ($resource === 'campuses') {
            foreach ($data['operating_hours'] as $day => $hours) {
                if (! $hours['closed'] && ($hours['opens'] ?? '') >= ($hours['closes'] ?? '')) {
                    $errors['operating_hours.'.$day.'.closes'] = ['Closing time must be after opening time.'];
                }
            }
        }
        if ($resource === 'terms') {
            $year = AcademicYear::findOrFail($data['academic_year_id']);
            if ($data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString()) {
                $errors['starts_on'] = ['The term must fit within its academic year.'];
            }
            if ($record && StudentEnrolment::whereHas('schoolClass', fn ($query) => $query->where('academic_term_id', $record->id))->where(fn ($query) => $query->whereDate('starts_on', '<', $data['starts_on'])->orWhereDate('ends_on', '>', $data['ends_on']))->exists()) {
                $errors['starts_on'] = ['The term must contain all existing enrolment periods.'];
            }
        }
        if ($resource === 'years' && $record && AcademicTerm::where('academic_year_id', $record->id)->where(function ($query) use ($data): void {
            $query->where('starts_on', '<', $data['starts_on'])->orWhere('ends_on', '>', $data['ends_on']);
        })->exists()) {
            $errors['starts_on'] = ['The year must contain all existing terms.'];
        }
        if ($resource === 'classes') {
            $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;
            if ($record && EnrolmentRules::peak($record) > $data['capacity']) {
                $errors['capacity'] = ['Class capacity cannot be below existing enrolments.'];
            }
            if ($room && $data['capacity'] > $room->capacity) {
                $errors['capacity'] = ['Class capacity cannot exceed room capacity.'];
            }
            foreach ($data['teacher_ids'] as $teacherId) {
                $teacher = User::with('roleAssignments')->findOrFail($teacherId);
                if (! $teacher->hasAnyRole('teacher') || ! $teacher->campuses()->whereKey($data['campus_id'])->exists()) {
                    $errors['teacher_ids'] = ['Each teacher must have the teacher role and be assigned to this campus.'];
                }
            }
        }
        if ($resource === 'rooms' && $record && SchoolClass::where('room_id', $record->id)->where('capacity', '>', $data['capacity'])->exists()) {
            $errors['capacity'] = ['Room capacity cannot be below an existing class capacity.'];
        }
        if ($resource === 'rooms' && $record) {
            $reservations = CampusBooking::where('room_id', $record->id)->where('status', 'confirmed')->where('ends_at', '>', now())->where('kind', '!=', 'maintenance');
            if ($data['status'] === 'inactive' && (clone $reservations)->exists()) {
                $errors['status'] = ['Cancel or relocate future bookings before deactivating this room.'];
            }
            if ((clone $reservations)->where('attendees', '>', $data['capacity'])->exists()) {
                $errors['capacity'] = ['Capacity cannot be below the attendance of a future confirmed booking.'];
            }
        }
        if ($resource === 'campuses' && $record) {
            $reservations = CampusBooking::where('campus_id', $record->id)->where('status', 'confirmed')->where('ends_at', '>', now())->get();
            if ($data['status'] === 'inactive' && $reservations->isNotEmpty()) {
                $errors['status'] = ['Cancel future bookings before deactivating this campus.'];
            }
            foreach ($reservations as $booking) {
                try {
                    BookingRules::availability((clone $record)->fill($data), $booking->kind, $booking->starts_at->setTimezone('Asia/Kuala_Lumpur'), $booking->ends_at->setTimezone('Asia/Kuala_Lumpur'));
                } catch (ValidationException) {
                    $errors['operating_hours'] = ['The proposed hours would invalidate an existing confirmed booking.'];
                }
            }
        }
        if ($resource === 'closures' && $data['status'] === 'active') {
            $from = CarbonImmutable::parse($data['starts_on'], 'Asia/Kuala_Lumpur')->startOfDay()->utc();
            $to = CarbonImmutable::parse($data['ends_on'], 'Asia/Kuala_Lumpur')->addDay()->startOfDay()->utc();
            if (CampusBooking::where('campus_id', $data['campus_id'])->where('status', 'confirmed')->where('kind', '!=', 'maintenance')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->exists()) {
                $errors['starts_on'] = ['Cancel or relocate confirmed bookings before adding this closure.'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
