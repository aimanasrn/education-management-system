<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampusBooking extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (CampusBooking $booking): void {
            $booking->reference ??= 'BKG-'.Str::ulid();
        });
    }

    protected $fillable = ['reference', 'campus_id', 'room_id', 'created_by', 'reviewed_by', 'kind', 'title', 'purpose', 'attendees', 'priority', 'starts_at', 'ends_at', 'status', 'series_id', 'decision_reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'attendees' => 'integer'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
