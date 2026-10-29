<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory  ReportFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'file_id',
        'category',
        'reason',
        'status',
        'user_ip',
        'admin_notes',
        'resolved_by',
        'resolved_at',
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
        'file_id' => 'integer',
        'resolved_by' => 'integer',
        'status' => 'string',
        'resolved_at' => 'datetime',
    ];

    /**
     * Mutator لتنظيف نص البلاغ من وسوم HTML الخبيثة لمنع ثغرات XSS
     */
    public function setReasonAttribute(string $value): void
    {
        $this->attributes['reason'] = strip_tags($value);
    }

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the user who filed the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the file that was reported.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to only include open reports.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope a query to only include resolved reports.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Scope a query to only include dismissed reports.
     */
    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISMISSED);
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
