<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CatalogFilters
{
    public const SORT_NEWEST = 'newest';

    public const SORT_PRICE_ASC = 'price_asc';

    public const SORT_PRICE_DESC = 'price_desc';

    public const SORT_BEST_SELLING = 'best_selling';

    public const SORTS = [self::SORT_NEWEST, self::SORT_PRICE_ASC, self::SORT_PRICE_DESC, self::SORT_BEST_SELLING];

    /**
     * @param  list<string>  $colors
     * @param  list<string>  $materials
     */
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly array $colors = [],
        public readonly array $materials = [],
        public readonly string $sort = self::SORT_NEWEST,
    ) {}

    /**
     * Anything malformed in the query string is ignored rather than rejected, so an
     * edited link still shows a normal page.
     */
    public static function fromRequest(Request $request): self
    {
        $sort = $request->query('sort');

        return new self(
            min: self::amount($request->query('min')),
            max: self::amount($request->query('max')),
            colors: self::strings($request->query('color')),
            materials: self::strings($request->query('material')),
            sort: in_array($sort, self::SORTS, true) ? $sort : self::SORT_NEWEST,
        );
    }

    public function isActive(): bool
    {
        return $this->min !== null || $this->max !== null || $this->colors !== [] || $this->materials !== [];
    }

    private static function amount(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return array_values(array_filter(Arr::wrap($value), fn (mixed $item) => is_string($item) && $item !== ''));
    }
}
