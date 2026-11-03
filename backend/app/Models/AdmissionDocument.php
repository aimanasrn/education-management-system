<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDocument extends Model
{
    use HasFactory;

    protected $fillable = ['admission_application_id', 'name', 'path', 'disk', 'bytes', 'mime', 'uploaded_by', 'verified_by', 'verified_at'];

    protected $attributes = ['disk' => 'local'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
