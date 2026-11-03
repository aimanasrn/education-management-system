<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $guard = Auth::guard('web');

        if (! $guard->validate($request->validated())) {
            throw ValidationException::withMessages([
                'email' => ['The email address or password is incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['The email address or password is incorrect.']]);
        }
        $expiresAt = now()->addHours(8);
        $token = $user->createToken('school-web', ['*'], $expiresAt);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
