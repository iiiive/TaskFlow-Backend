<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function states(): HasMany
    {
        return $this->hasMany(WorkflowState::class)->orderBy('position');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function canTransition(int $fromStateId, int $toStateId): bool
    {
        return $this->transitions()
            ->where('from_state_id', $fromStateId)
            ->where('to_state_id', $toStateId)
            ->exists();
    }

    public function initialState(): ?WorkflowState
    {
        return $this->states()->where('is_initial', true)->first();
    }
}
