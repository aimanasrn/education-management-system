<?php

namespace App;

use App\Models\AdmissionApplication;
use App\Models\Student;
use App\Models\StudentEnrolment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LearningAccess
{
    public static function applications(User $user): Builder
    {
        abort_unless($user->hasPermission('admissions.view'), 403);
        $query = AdmissionApplication::query();
        if ($user->hasPermission('admissions.manage')) {
            if (! $user->isGlobalAdmin()) {
                $query->whereIn('campus_id', $user->campuses()->select('campuses.id'));
            }
        } else {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public static function students(User $user): Builder
    {
        abort_unless($user->hasPermission('learning.view'), 403);
        $query = Student::withTrashed();
        if ($user->isGlobalAdmin()) {
            return $query;
        }
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return $query->where(function (Builder $scope) use ($user, $today): void {
            $scope->whereRaw('1 = 0');
            if ($user->hasPermission('enrolments.manage') || $user->hasAnyRole('teacher')) {
                $classes = FoundationAccess::query($user, 'classes')->select('id');
                $scope->orWhereIn('id', StudentEnrolment::whereIn('school_class_id', $classes)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->select('student_id'));
            }
            if ($user->hasPermission('admissions.manage')) {
                $scope->orWhereIn('id', AdmissionApplication::whereIn('campus_id', $user->campuses()->select('campuses.id'))->where('status', 'approved')->whereNotIn('student_id', StudentEnrolment::where('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->select('student_id'))->select('student_id'));
            }
            if ($user->hasAnyRole('student')) {
                $scope->orWhereIn('id', $user->studentAccountLinks()->select('student_id'));
            }
            if ($user->hasAnyRole('guardian')) {
                $scope->orWhereIn('id', $user->guardianLinks()->where('status', 'active')->select('student_id'));
            }
        });
    }

    public static function enrolments(User $user): Builder
    {
        $students = self::students($user)->select('students.id');
        $query = StudentEnrolment::whereIn('student_id', $students);
        if (! $user->isGlobalAdmin()) {
            $query->where(function (Builder $scope) use ($user): void {
                $scope->whereRaw('1 = 0');
                if ($user->hasPermission('classes.view')) {
                    $scope->orWhereIn('school_class_id', FoundationAccess::query($user, 'classes')->select('id'));
                }
                if ($user->hasAnyRole('student')) {
                    $scope->orWhereIn('student_id', $user->studentAccountLinks()->select('student_id'));
                }
                if ($user->hasAnyRole('guardian')) {
                    $scope->orWhereIn('student_id', $user->guardianLinks()->where('status', 'active')->select('student_id'));
                }
            });
        }

        return $query;
    }

    public static function canContact(User $user, Student $student): bool
    {
        return $user->hasPermission('enrolments.manage')
            || ($user->hasAnyRole('student') && $user->studentAccountLinks()->where('student_id', $student->id)->exists())
            || ($user->hasAnyRole('guardian') && $user->guardianLinks()->where('student_id', $student->id)->where('status', 'active')->exists());
    }
}
