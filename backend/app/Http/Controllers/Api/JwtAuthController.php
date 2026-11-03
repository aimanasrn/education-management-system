<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\JwtAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class JwtAuthController extends Controller
{
    public function login(LoginRequest $request, JwtAccess $jwt): JsonResponse
    {
        abort_unless(is_string(config('jwt.secret')) && strlen(config('jwt.secret')) >= 32, 503, 'JWT access is not configured.');
        $guard = Auth::guard('web');
        if (! $guard->validate($request->validated()) || ! $guard->getLastAttempted()->is_active) {
            throw ValidationException::withMessages(['email' => ['The email address or password is incorrect.']]);
        }
        $response = DB::transaction(function () use ($guard, $jwt): array {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $user = User::with('roleAssignments')->findOrFail($guard->getLastAttempted()->id);
            abort_unless($user->is_active, 403);
            abort_unless(hash_equals($user->password, $guard->getLastAttempted()->password), 401);

            return ['user' => new UserResource($user), ...$jwt->issue($user)];
        });

        return response()->json($response)->header('Cache-Control', 'no-store');
    }

    public function refresh(Request $request, JwtAccess $jwt): JsonResponse
    {
        $response = DB::transaction(function () use ($request, $jwt): array {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $user = User::with('roleAssignments')->findOrFail($request->user()->id);
            abort_unless($user->is_active, 403);
            $old = PersonalAccessToken::whereKey($request->user()->currentAccessToken()->id)->lockForUpdate()->first();
            abort_unless($old && $old->name === 'ems-jwt' && $old->expires_at && $old->expires_at->isFuture(), 401);
            $issued = $jwt->issue($user, $request->attributes->get('jwt_auth_time'));
            $old->delete();

            return ['user' => new UserResource($user), ...$issued];
        });

        return response()->json($response)->header('Cache-Control', 'no-store');
    }
}
