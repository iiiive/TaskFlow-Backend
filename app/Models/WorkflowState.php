<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowState extends Model
{
    protected $fillable = [
        'workflow_template_id',
        'name',
        'color',
        'position',
        'is_initial',
        'is_final',
        'requires_approval',
        'required_fields',
    ];

    protected $casts = [
        'position'          => 'integer',
        'is_initial'        => 'boolean',
        'is_final'          => 'boolean',
        'requires_approval' => 'boolean',
        'required_fields'   => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_state_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_state_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'workflow_state_id');
    }
}
