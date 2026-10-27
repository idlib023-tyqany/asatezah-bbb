<?php

namespace App\Models;

use Database\Factories\RatingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    /** @use HasFactory  RatingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'file_id',
        'value',
        'comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'file_id' => 'integer',
        'value' => 'integer',
    ];

    /**
     * Boot function للتحديث التلقائي لمعدل تقييم الملف عند إضافة أو تغيير التقييم
     */
    protected static function boot(): void
    {
        parent::boot();

        $updateFileStats = function (Rating $rating) {
            if ($rating->file) {
                $file = $rating->file;
                $avgRating = $file->ratings()->avg('value') ?? 0;
                $ratingsCount = $file->ratings()->count();

                $file->update([
                    'avg_rating' => round($avgRating, 2),
                    'ratings_count' => $ratingsCount,
                ]);
            }
        };

        static::saved($updateFileStats);
        static::deleted($updateFileStats);
    }

    /* ================= العلاقات (Relations) ================= */

    /**
     * Get the user who rated the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the file that was rated.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /* ================= النطاقات (Scopes) ================= */

    /**
     * Scope a query to filter ratings for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter ratings within valid bounds (1 to 5).
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereBetween('value', [1, 5]);
    }
}
