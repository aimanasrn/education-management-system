<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Building extends Model
{
    use HasFactory;

    protected $fillable = ['campus_id', 'code', 'name', 'status'];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}
