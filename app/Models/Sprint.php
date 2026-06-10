<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    const STATUS_PLANNING  = 'planning';
    const STATUS_ACTIVE    = 'active';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'goal',
        'status',
        'start_date',
        'end_date',
        'completed_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function totalStoryPoints(): int
    {
        return (int) $this->tickets()->sum('story_points');
    }

    public function completedStoryPoints(): int
    {
        return (int) $this->tickets()->whereIn('status', ['done', 'completed'])->sum('story_points');
    }
}
