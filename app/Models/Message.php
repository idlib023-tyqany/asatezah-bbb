<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'image_path',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'read_at' => 'datetime',
    ];

    /**
     * Mutator لتنظيف نص الرسالة لمنع ثغرات XSS
     */
    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value ? strip_tags($value) : null;
    }

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    /**
     * Get the sender of the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to order messages chronologically.
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope a query to filter unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    /**
     * Check if the message contains an attached image.
     */
    public function hasImage(): bool
    {
        return ! empty($this->image_path);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): bool
    {
        if ($this->read_at !== null) {
            return true;
        }

        return $this->update([
            'read_at' => now(),
        ]);
    }
}
