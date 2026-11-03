<?php

namespace App;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class JwtAccess
{
    private function secret(): string
    {
        $secret = config('jwt.secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('JWT access is not configured.');
        }

        return $secret;
    }

    public function issue(User $user, ?int $authenticatedAt = null): array
    {
        $secret = $this->secret();
        $minutes = max(1, min(60, (int) config('jwt.ttl_minutes')));
        $issuedAt = now()->timestamp;
        $authenticatedAt ??= $issuedAt;
        $sessionExpires = $authenticatedAt + 8 * 3600;
        abort_if($sessionExpires <= $issuedAt, 401);
        $expires = Carbon::createFromTimestampUTC(min($issuedAt + $minutes * 60, $sessionExpires));
        $session = $user->createToken('ems-jwt', ['jwt:access'], $expires)->accessToken;
        $claims = ['iss' => config('jwt.issuer'), 'aud' => config('jwt.audience'), 'sub' => (string) $user->id, 'jti' => (string) $session->id, 'iat' => $issuedAt, 'nbf' => $issuedAt, 'exp' => $expires->timestamp, 'token_use' => 'access', 'auth_time' => $authenticatedAt, 'session_exp' => $sessionExpires];

        return ['token' => JWT::encode($claims, $secret, 'HS256'), 'token_type' => 'Bearer', 'expires_in' => $expires->timestamp - $issuedAt, 'expires_at' => $expires->toIso8601String()];
    }

    public function authenticate(Request $request): ?User
    {
        $raw = $request->bearerToken();
        if (! is_string($raw) || strlen($raw) > 4096 || substr_count($raw, '.') !== 2) {
            return null;
        }
        try {
            $claims = (array) JWT::decode($raw, new Key($this->secret(), 'HS256'));
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException|RuntimeException) {
            return null;
        }
        foreach (['sub', 'jti'] as $field) {
            if (! isset($claims[$field]) || ! is_string($claims[$field]) || ! preg_match('/^[1-9][0-9]{0,17}$/D', $claims[$field])) {
                return null;
            }
        }
        foreach (['iat', 'nbf', 'exp', 'auth_time', 'session_exp'] as $field) {
            if (! isset($claims[$field]) || ! is_int($claims[$field])) {
                return null;
            }
        }
        if (($claims['iss'] ?? null) !== config('jwt.issuer') || ($claims['aud'] ?? null) !== config('jwt.audience') || ($claims['token_use'] ?? null) !== 'access'
            || $claims['nbf'] !== $claims['iat'] || $claims['exp'] <= $claims['iat'] || $claims['exp'] - $claims['iat'] > 3600
            || $claims['auth_time'] > $claims['iat'] || $claims['session_exp'] !== $claims['auth_time'] + 8 * 3600 || $claims['exp'] > $claims['session_exp']) {
            return null;
        }
        $session = PersonalAccessToken::with('tokenable')->find($claims['jti']);
        if (! $session || $session->name !== 'ems-jwt' || $session->abilities !== ['jwt:access'] || ! $session->expires_at || $session->expires_at->isPast()
            || $session->expires_at->timestamp !== $claims['exp'] || (string) $session->tokenable_id !== $claims['sub'] || ! $session->tokenable instanceof User) {
            return null;
        }
        $session->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('jwt_auth_time', $claims['auth_time']);

        return $session->tokenable->withAccessToken($session);
    }
}
