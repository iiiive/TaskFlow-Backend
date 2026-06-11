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
        'logo_path',
        'primary_color',
        'custom_domain',
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

    /**
     * Total storage limit for this organization, in bytes (null = unlimited).
     */
    public function storageLimitBytes(): ?int
    {
        $gb = $this->subscriptionPlan?->storage_gb;
        return $gb !== null ? (int) $gb * 1024 * 1024 * 1024 : null;
    }

    /**
     * Bytes currently consumed by ticket attachments across this org's projects.
     */
    public function storageUsedBytes(): int
    {
        return (int) TicketAttachment::query()
            ->whereHas('ticket.workspace', fn ($q) => $q->where('organization_id', $this->id))
            ->sum('size_bytes');
    }
}
