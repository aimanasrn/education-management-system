<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'email', 'phone', 'address', 'operating_hours', 'status'];

    protected function casts(): array
    {
        return ['operating_hours' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campus_user');
    }
}
