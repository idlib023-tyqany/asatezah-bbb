<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    /** @use HasFactory  FileFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uploaded_by',
        'category_id',
        'material_id',
        'folder_id',
        'title',
        'description',
        'filename',
        'path',
        'thumbnail',
        'file_type',
        'mime_type',
        'file_size',
        'content_hash',
        'status',
        'rejection_reason',
        'views_count',
        'downloads_count',
        'favorites_count',
        'avg_rating',
        'ratings_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uploaded_by' => 'integer',
        'category_id' => 'integer',
        'material_id' => 'integer',
        'folder_id' => 'integer',
        'file_size' => 'integer',
        'views_count' => 'integer',
        'downloads_count' => 'integer',
        'favorites_count' => 'integer',
        'ratings_count' => 'integer',
        'avg_rating' => 'float',
    ];

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the user who uploaded the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Alias for user relation (for backwards compatibility).
     */
    public function uploader(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the category that the file belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the material that the file belongs to.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the folder that contains the file.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the favorites for the file.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get the ratings for the file.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get the comments for the file.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the reports for the file.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /* ================= النطاقات (Scopes) ================= */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeMostDownloaded(Builder $query): Builder
    {
        return $query->orderByDesc('downloads_count');
    }

    public function scopeTopRated(Builder $query): Builder
    {
        return $query->orderByDesc('avg_rating');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('filename', 'LIKE', "%{$term}%");
        });
    }

    /* ================= الدوال المساعدة (Helpers) ================= */

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
