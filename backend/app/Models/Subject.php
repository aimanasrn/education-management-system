<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['programme_id', 'code', 'name', 'status'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
