<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Support\ArabicText;
use App\Support\CatalogFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Everything that lists products for shoppers. Filtering and sorting happen in PHP
 * because they must use the final price after offers, which only PriceService
 * knows. A furniture catalog is small enough for that to stay fast.
 */
class Catalog
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly PriceService $prices,
        private readonly BestSellers $bestSellers,
        private readonly CatalogCache $cache,
    ) {}

    /**
     * @return Builder<Product>
     */
    private function query(): Builder
    {
        return Product::query()->visible()->with('variants')->latest()->orderByDesc('id');
    }

    /**
     * Top-level sections for the home page, each with its visible product count
     * including sub-sections.
     *
     * @return Collection<int, Category>
     */
    public function sections(): Collection
    {
        $counts = Product::query()->visible()->toBase()->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $parents = Category::query()->pluck('parent_id', 'id');

        return Category::query()->active()->whereNull('parent_id')->with('media')->orderBy('sort_order')->get()
            ->each(function (Category $category) use ($counts, $parents) {
                $ids = [$category->id, ...$category->descendantIds($parents)];

                $category->setAttribute('products_total', collect($ids)->sum(fn (int $id) => $counts[$id] ?? 0));
            });
    }

    /**
     * @return Collection<int, Product>
     */
    public function inCategory(Category $category): Collection
    {
        return $this->query()
            ->whereIn('category_id', [$category->id, ...$category->descendantIds()])
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function newArrivals(?int $limit = null): Collection
    {
        return $this->query()->where('is_new', true)->limit($limit)->get();
    }

    /**
     * Products discounted right now, biggest discount first.
     *
     * @return Collection<int, Product>
     */
    public function onOffer(?int $limit = null): Collection
    {
        if (! Offer::query()->active()->exists()) {
            return new Collection;
        }

        $ids = $this->cache->remember('on-offer', fn () => $this->query()->get()
            ->filter(fn (Product $product) => $this->prices->quote($product)->hasDiscount())
            ->sortByDesc(fn (Product $product) => $this->prices->quote($product)->percentOff())
            ->pluck('id')
            ->all());

        // Checked again, because an offer can end while the list is still cached.
        return $this->inOrder($limit ? array_slice($ids, 0, $limit) : $ids)
            ->filter(fn (Product $product) => $this->prices->quote($product)->hasDiscount())
            ->values();
    }

    public function nextOfferEnd(): ?Carbon
    {
        return Offer::query()->active()->orderBy('ends_at')->value('ends_at');
    }

    /**
     * Ranked by delivered sales. A new store has none, so featured products fill
     * the list until real sales take over.
     *
     * @return Collection<int, Product>
     */
    public function bestSelling(?int $limit = null): Collection
    {
        $quantities = $this->bestSellers->quantities();
        $ranked = array_keys($quantities);

        // Twice what is shown, in case some of the top sellers are hidden or sold out.
        $products = $this->query()
            ->where(fn (Builder $query) => $query->whereIn('id', $limit ? array_slice($ranked, 0, $limit * 2) : $ranked)->orWhere('is_featured', true))
            ->get()
            ->sortByDesc(fn (Product $product) => $quantities[$product->id] ?? 0)
            ->values();

        return $limit ? $products->take($limit) : $products;
    }

    /**
     * @return Collection<int, Product>
     */
    public function search(string $term, ?int $limit = null): Collection
    {
        $words = array_values(array_filter(explode(' ', ArabicText::normalize($term))));

        if ($words === []) {
            return new Collection;
        }

        $ids = array_keys(array_filter($this->searchIndex(), fn (string $text) => Str::containsAll($text, $words)));

        return $this->inOrder($limit ? array_slice($ids, 0, $limit) : $ids);
    }

    /**
     * What each visible product can be found by, newest first. Runs for every key
     * pressed in the search box, so it must not load the catalog each time.
     *
     * @return array<int, string>
     */
    private function searchIndex(): array
    {
        return $this->cache->remember('search', fn () => $this->query()->with('category')->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $this->searchText($product)])
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private function inOrder(array $ids): Collection
    {
        $positions = array_flip($ids);

        return $this->query()->whereKey($ids)->get()
            ->sortBy(fn (Product $product) => $positions[$product->id])
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        return $this->query()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function filter(Collection $products, CatalogFilters $filters): Collection
    {
        return $products->filter(function (Product $product) use ($filters) {
            $price = $this->prices->finalPrice($product);

            return ($filters->min === null || $price >= $filters->min)
                && ($filters->max === null || $price <= $filters->max)
                && ($filters->colors === [] || array_intersect($filters->colors, $this->colors($product)) !== [])
                && ($filters->materials === [] || in_array($product->localized('material'), $filters->materials, true));
        })->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function sort(Collection $products, string $sort): Collection
    {
        return match ($sort) {
            CatalogFilters::SORT_PRICE_ASC => $products->sortBy(fn (Product $product) => $this->prices->finalPrice($product))->values(),
            CatalogFilters::SORT_PRICE_DESC => $products->sortByDesc(fn (Product $product) => $this->prices->finalPrice($product))->values(),
            CatalogFilters::SORT_BEST_SELLING => $this->sortByBestSelling($products),
            default => $products,
        };
    }

    /**
     * The choices to offer in the filter panel for these products.
     *
     * @param  Collection<int, Product>  $products
     * @return array{colors: list<string>, materials: list<string>}
     */
    public function facets(Collection $products): array
    {
        return [
            'colors' => $products->flatMap(fn (Product $product) => $this->colors($product))->unique()->sort()->values()->all(),
            'materials' => $products->map(fn (Product $product) => $product->localized('material'))->filter()->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(Collection $products, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $this->forCards($products->forPage($page, self::PER_PAGE)->values()),
            $products->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * Loads what the product card shows, only for the products on screen.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function forCards(Collection $products): Collection
    {
        return $products->load(['media', 'category']);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sortByBestSelling(Collection $products): Collection
    {
        $quantities = $this->bestSellers->quantities();

        return $products->sortByDesc(fn (Product $product) => $quantities[$product->id] ?? 0)->values();
    }

    /**
     * @return list<string>
     */
    private function colors(Product $product): array
    {
        return collect([$product->localized('color')])
            ->merge($product->variants->map(fn ($variant) => $variant->localized('color')))
            ->filter()
            ->values()
            ->all();
    }

    private function searchText(Product $product): string
    {
        return ArabicText::normalize(implode(' ', [
            $product->name_ar,
            $product->name_en,
            $product->category->name_ar,
            $product->category->name_en,
        ]));
    }
}
