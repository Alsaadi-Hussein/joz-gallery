<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['parent_id', 'name_ar', 'name_en', 'slug', 'sort_order', 'is_active'])]
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasLocalizedFields, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Ids of every category below this one, used to keep the parent picker free of
     * loops. Pass the parent map in when calling this for several categories.
     *
     * @param  Collection<int, int|null>|null  $parents
     * @return list<int>
     */
    public function descendantIds(?Collection $parents = null): array
    {
        $parents ??= Category::query()->pluck('parent_id', 'id');
        $found = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = $parents
                ->filter(fn (?int $parentId, int $id) => in_array($parentId, $frontier, true) && ! in_array($id, $found, true))
                ->keys()
                ->all();

            array_push($found, ...$frontier);
        }

        return $found;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->nonQueued();
    }
}
