<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'category_id', 'name_ar', 'name_en', 'slug', 'description_ar', 'description_en',
    'price', 'stock', 'sku', 'material_ar', 'material_en', 'color_ar', 'color_en',
    'width_cm', 'depth_cm', 'height_cm', 'is_active', 'is_new', 'is_featured',
    'meta_title', 'meta_description',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'width_cm' => 'integer',
            'depth_cm' => 'integer',
            'height_cm' => 'integer',
            'is_active' => 'boolean',
            'is_new' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Stock the store can actually sell: the variants' total when the product has
     * variants, otherwise the product's own stock.
     *
     * @return Attribute<int, never>
     */
    protected function effectiveStock(): Attribute
    {
        return Attribute::get(
            fn () => $this->variants->isNotEmpty() ? (int) $this->variants->sum('stock') : $this->stock,
        );
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeLowStock(Builder $query, int $threshold): void
    {
        $query->whereRaw(
            '(COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_variants.product_id = products.id), products.stock)) <= ?',
            [$threshold],
        );
    }

    /**
     * Copy the product and its variants, without images. The copy starts hidden
     * because it has no photos yet.
     */
    public function duplicate(): self
    {
        return DB::transaction(function () {
            $copy = $this->replicate(['sku']);
            $copy->slug = $this->uniqueCopySlug();
            $copy->is_active = false;
            $copy->save();

            $this->variants->each(fn (ProductVariant $variant) => $copy->variants()->save($variant->replicate()));

            return $copy;
        });
    }

    private function uniqueCopySlug(): string
    {
        $base = Str::of($this->slug)->replaceMatches('/-copy(-\d+)?$/', '')->append('-copy')->toString();
        $slug = $base;

        for ($n = 2; static::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Fit::Max never enlarges, so small photos are not blown up to 1600px.
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 600, 1200)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('large')
            ->fit(Fit::Max, 1600, 3200)
            ->format('webp')
            ->nonQueued();
    }
}
