<?php

namespace App\Models;

use Database\Factories\SupportConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportConversation extends Model
{
    /** @use HasFactory */
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'admin_id',
        'subject',
        'status',
        'last_message_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'admin_id' => 'integer',
        'status' => 'string',
        'last_message_at' => 'datetime',
    ];

    /**
     * Mutator لتنظيف موضوع المحادثة من وسوم HTML الخبيثة لمنع ثغرات XSS
     */
    public function setSubjectAttribute(string $value): void
    {
        $this->attributes['subject'] = strip_tags($value);
    }

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the user who opened the support ticket.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin assigned to the conversation.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the messages for the support conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest message in the conversation.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to only include open conversations.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope a query to only include closed conversations.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Scope a query to order conversations by recent activity.
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at');
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
