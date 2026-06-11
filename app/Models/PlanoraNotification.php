<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoraNotification extends Model
{
    protected $table = 'planora_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public static function createForUser(int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $data = []): self
    {
        return static::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $actionUrl,
            'data'       => $data ?: null,
        ]);
    }
}
