<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'category_id',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'category_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Get the path from the root folder to this folder.
     *
     * @return array<int, array{id: int, name: string, parent_id: int|null}>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;
        $visited = [];

        while ($current !== null && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            $breadcrumbs[] = [
                'id' => (int) $current->id,
                'name' => $current->name,
                'parent_id' => $current->parent_id === null ? null : (int) $current->parent_id,
            ];
            $current = $current->parent()->first(['id', 'name', 'parent_id']);
        }

        return array_reverse($breadcrumbs);
    }
}
