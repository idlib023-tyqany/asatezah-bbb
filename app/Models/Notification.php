<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Notification extends Model
{
    /** @use HasFactory  NotificationFactory> */
    use HasFactory;

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_SPECIALIZATION = 'specialization';
    public const AUDIENCE_ROLE = 'role';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'audience',
        'specialization',
        'title',
        'body',
        'type',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
    ];

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get all user notification records.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * Get all users who received this notification through user_notifications table.
     */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            UserNotification::class,
            'notification_id', // Foreign key on user_notifications table
            'id',              // Foreign key on users table
            'id',              // Local key on notifications table
            'user_id'          // Local key on user_notifications table
        );
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to filter global notifications.
     */
    public function scopeForGeneral(Builder $query): Builder
    {
        return $query->where('audience', self::AUDIENCE_ALL);
    }

    /**
     * Scope a query to filter notifications by specialization.
     */
    public function scopeForSpecialization(Builder $query, string $specialization): Builder
    {
        return $query->where('audience', self::AUDIENCE_SPECIALIZATION)
            ->where('specialization', $specialization);
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    /**
     * Check if the notification is for all users.
     */
    public function isGeneral(): bool
    {
        return $this->audience === self::AUDIENCE_ALL;
    }
}
