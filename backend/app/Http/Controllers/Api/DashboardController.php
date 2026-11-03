<?php

namespace App\Http\Controllers\Api;

use App\FoundationAccess;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $metrics = [];
        foreach (['campuses' => 'Active campuses', 'rooms' => 'Active rooms', 'classes' => 'Active classes'] as $resource => $label) {
            $permission = $resource === 'classes' ? 'classes.view' : 'campuses.view';
            if ($user->hasPermission($permission)) {
                $metrics[] = ['key' => $resource, 'label' => $label, 'value' => FoundationAccess::query($user, $resource)->where('status', 'active')->count()];
            }
        }
        if ($user->hasPermission('students.manage')) {
            $metrics[] = ['key' => 'students', 'label' => 'Active student profiles', 'value' => Student::count()];
        }
        if ($user->isGlobalAdmin() || $user->hasAnyRole('campus_manager', 'hr_officer')) {
            $staff = User::where('is_active', true)->whereHas('roleAssignments', fn ($query) => $query->whereIn('role', ['admin', 'centre_admin', 'campus_manager', 'teacher', 'hr_officer', 'finance_officer']));
            if (! $user->isGlobalAdmin()) {
                $staff->whereHas('campuses', fn ($query) => $query->whereIn('campuses.id', $user->campuses()->select('campuses.id')));
            }
            $metrics[] = ['key' => 'staff', 'label' => 'Active staff accounts', 'value' => $staff->count()];
        }

        return response()->json(['data' => ['metrics' => $metrics, 'as_of' => now()->toIso8601String(), 'scope' => $user->isGlobalAdmin() ? 'All campuses' : 'Your assigned campuses and classes']]);
    }
}
