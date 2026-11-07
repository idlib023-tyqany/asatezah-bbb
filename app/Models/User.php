<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // أدوار المستخدمين
    public const ROLE_ADMIN = 'admin';

    public const ROLE_STUDENT = 'student';

    public const ROLE_TEACHER = 'teacher';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'specialization',
        'display_name',
        'phone_number',
        'trainer_type',
        'is_active',
        'fcm_token', // للربط مع إشعارات التطبيق المستقبلية
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_contribute' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function avatarPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => $value ?? $attributes['avatar'] ?? null,
            set: fn (?string $value): array => ['avatar' => $value],
        );
    }

    /**
     * Mutator لتنظيف البريد الإلكتروني وتحويله لأحرف صغيرة تلقائياً
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get all files uploaded by the user.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }

    /**
     * Get all favorites saved by the user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get all ratings created by the user.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get all comments made by the user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all support conversations opened by the user.
     */
    public function supportConversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'user_id');
    }

    /**
     * Get all support messages sent by the user.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get user notification read/unread records.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to only include admin users.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * Scope a query to only include users allowed to contribute/upload.
     */
    public function scopeContributors(Builder $query): Builder
    {
        return $query->where('can_contribute', true);
    }

    /* ================= الصلاحيات والدوال المساعدة (Helpers) ================= */

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user is a teacher.
     */
    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    /**
     * Check if user can contribute/upload files.
     */
    public function canContribute(): bool
    {
        return (bool) $this->can_contribute || $this->isAdmin();
    }
}

