<?php

namespace App\Models;

use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    /** @use HasFactory */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'notification_id',
        'read',
        'read_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'read' => false,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'notification_id' => 'integer',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the user associated with the notification record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notification details.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('read', false);
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('read', true);
    }

    /**
     * Scope a query to filter notifications for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    /**
     * Mark the user notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->read) {
            return true;
        }

        return $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }
}
