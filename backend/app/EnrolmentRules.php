<?php

namespace App;

use App\Models\SchoolClass;
use App\Models\StudentEnrolment;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EnrolmentRules
{
    public static function peak(SchoolClass $class): int
    {
        $events = [];
        foreach (StudentEnrolment::where('school_class_id', $class->id)->get() as $record) {
            $start = $record->starts_on->toDateString();
            $end = $record->ends_on->addDay()->toDateString();
            $events[$start] = ($events[$start] ?? 0) + 1;
            $events[$end] = ($events[$end] ?? 0) - 1;
        }
        ksort($events);
        $count = 0;
        $peak = 0;
        foreach ($events as $delta) {
            $count += $delta;
            $peak = max($peak, $count);
        }

        return $peak;
    }

    public static function validate(User $user, array $input): array
    {
        $data = Validator::make($input, [
            'student_id' => ['required', 'integer', 'min:1'],
            'school_class_id' => ['required', 'integer', 'min:1'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();
        $student = LearningAccess::students($user)->findOrFail($data['student_id']);
        if ($student->trashed()) {
            throw ValidationException::withMessages(['student_id' => ['Restore the student before enrolment.']]);
        }
        $class = FoundationAccess::query($user, 'classes')->with(['campus', 'programme', 'academicYear', 'academicTerm', 'room'])->findOrFail($data['school_class_id']);
        foreach ([$class, $class->campus, $class->programme, $class->academicYear, $class->academicTerm, $class->room] as $record) {
            if ($record && $record->status !== 'active') {
                throw ValidationException::withMessages(['school_class_id' => ['The class and its campus, programme, year, term and room must be active.']]);
            }
        }
        if ($data['starts_on'] < $class->academicTerm->starts_on->toDateString() || $data['ends_on'] > $class->academicTerm->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => ['The enrolment dates must fit within the class term.']]);
        }
        $overlap = StudentEnrolment::where('student_id', $student->id)
            ->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])
            ->whereHas('schoolClass', fn ($query) => $query->where('programme_id', $class->programme_id))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['student_id' => ['This student already has an overlapping enrolment in this programme. Use a transfer or end the existing enrolment.']]);
        }

        return $data;
    }
}
