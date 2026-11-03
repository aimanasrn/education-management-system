<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $counts = [];
        foreach (['admin', 'teacher', 'student'] as $role) {
            $counts[$role] = User::where('is_active', true)->whereHas('roleAssignments', fn ($query) => $query->where('role', $role))->count();
        }

        return response()->json([
            'data' => [
                'administrators' => $counts['admin'],
                'teachers' => $counts['teacher'],
                'students' => $counts['student'],
                'student_records' => Student::count(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
