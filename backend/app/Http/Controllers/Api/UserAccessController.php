<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserAccessRequest;
use App\Http\Resources\UserResource;
use App\Models\Campus;
use App\Models\User;
use App\UserAccessService;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserAccessController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = User::with('roleAssignments', 'campuses:id,name');
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term]));
        }

        return UserResource::collection($query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString());
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
        abort_if(Campus::count() > 2000, 422, 'Too many campus options.');

        return response()->json(['data' => [
            'roles' => collect(UserRole::cases())->filter(fn (UserRole $role) => $request->user()->hasAnyRole('admin') || ! in_array($role, [UserRole::Admin, UserRole::CentreAdmin], true))
                ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])->values(),
            'campuses' => Campus::orderBy('name')->get(['id', 'name'])->map(fn (Campus $campus) => ['value' => $campus->id, 'label' => $campus->name]),
        ]]);
    }

    public function store(SaveUserAccessRequest $request, UserAccessService $service): JsonResponse
    {
        return (new UserResource($service->save($request->user(), $request->validated(), null)))->response()->setStatusCode(201);
    }

    public function update(SaveUserAccessRequest $request, int $id, UserAccessService $service): UserResource
    {
        return new UserResource($service->save($request->user(), $request->validated(), $id));
    }
}
