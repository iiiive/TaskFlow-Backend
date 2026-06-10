<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'owner_email',
        'subscription_plan_id',
        'is_active',
        'onboarded_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'onboarded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueSlug($organization->name);
            }
        });
    }

    private static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function projects()
    {
        return $this->hasMany(Workspace::class, 'organization_id');
    }

    public function isAtProjectLimit(): bool
    {
        $max = $this->subscriptionPlan?->max_projects ?? PHP_INT_MAX;
        return $this->projects()->count() >= $max;
    }

    public function isAtMemberLimit(): bool
    {
        $max = $this->subscriptionPlan?->max_members ?? PHP_INT_MAX;
        return $this->users()->count() >= $max;
    }

    public function usagePercent(): int
    {
        $max = $this->subscriptionPlan?->max_members ?? 0;
        if ($max === 0) {
            return 0;
        }
        return (int) round(($this->users()->count() / $max) * 100);
    }
}
