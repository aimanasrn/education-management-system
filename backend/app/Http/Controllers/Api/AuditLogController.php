<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'campus_id' => ['nullable', 'integer', 'min:1']]);
        $query = AuditLog::with('actor:id,name')->orderByDesc('id');
        if (! empty($filters['campus_id'])) {
            $query->where('campus_id', $filters['campus_id']);
        }
        $logs = $query->paginate(25)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id, 'actor' => $log->actor?->name ?? ($log->action === 'demo_seeded' ? 'Demo seeder' : 'Unavailable account'),
            'campus_id' => $log->campus_id, 'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id, 'action' => $log->action,
            'changed_fields' => $log->changed_fields, 'created_at' => $log->created_at->toIso8601String(),
        ]);

        return response()->json($logs);
    }
}
