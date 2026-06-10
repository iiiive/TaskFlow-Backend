<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'color',
    ];

    public function project()
    {
        return $this->belongsTo(Workspace::class, 'project_id');
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'issue_labels');
    }
}
