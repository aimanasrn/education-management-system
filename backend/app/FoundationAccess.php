<?php

namespace App;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FoundationAccess
{
    public static function canWrite(User $user, string $resource, bool $creating = false): bool
    {
        return $user->hasPermission(FoundationCatalog::get($resource)['permission'].'.manage')
            && ! ($resource === 'campuses' && $creating && ! $user->isGlobalAdmin());
    }

    public static function query(User $user, string $resource): Builder
    {
        $config = FoundationCatalog::get($resource);
        abort_unless($user->hasPermission($config['permission'].'.view'), 403);
        $query = $config['model']::query();
        if (! $user->isGlobalAdmin()) {
            $campuses = $user->campuses()->select('campuses.id');
            if ($resource === 'campuses') {
                $query->whereIn('id', $campuses);
            } elseif (in_array('campus_id', $config['fields'], true)) {
                $query->whereIn('campus_id', $campuses);
            }
            if ($resource === 'classes' && ! $user->hasPermission('classes.manage')) {
                $query->whereHas('teachers', fn (Builder $teachers) => $teachers->where('users.id', $user->id));
            }
        }

        return $query;
    }
}
