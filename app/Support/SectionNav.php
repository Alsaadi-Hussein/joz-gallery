<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * The strip of section tabs on top of a section page, and the sections before
 * and after the current one for the swipe and the "next section" link. The
 * sections run in their admin order, then "Joz Picks", and wrap around.
 */
final readonly class SectionNav
{
    public const PICKS = 'picks';

    /**
     * @param  list<array{key: string, label: string, url: string, image: ?string, picks: bool}>  $tabs
     * @param  array{key: string, label: string, url: string, image: ?string, picks: bool}|null  $previous
     * @param  array{key: string, label: string, url: string, image: ?string, picks: bool}|null  $next
     */
    private function __construct(
        public array $tabs,
        public string $current,
        public ?array $previous,
        public ?array $next,
    ) {}

    /**
     * @param  Collection<int, Category>  $sections  top-level sections, in order
     * @param  string|int  $current  a section id, or self::PICKS
     */
    public static function make(Collection $sections, string|int $current): self
    {
        $tabs = $sections->map(fn (Category $section) => [
            'key' => (string) $section->id,
            'label' => $section->localized('name'),
            'url' => shop_route('category', $section),
            'image' => $section->getFirstMediaUrl('image', 'thumb') ?: null,
            'picks' => false,
        ])->values()->all();

        $tabs[] = [
            'key' => self::PICKS,
            'label' => __('shop.nav.picks'),
            'url' => shop_route('picks'),
            'image' => asset('assets/img/brand/emblem-cream.png'),
            'picks' => true,
        ];

        $current = (string) $current;
        $index = array_search($current, array_column($tabs, 'key'), true);
        $count = count($tabs);

        // One tab means nowhere to go.
        if ($index === false || $count < 2) {
            return new self($tabs, $current, null, null);
        }

        return new self(
            $tabs,
            $current,
            $tabs[($index - 1 + $count) % $count],
            $tabs[($index + 1) % $count],
        );
    }

    /**
     * The top-level section a category belongs to, which is the tab to mark.
     */
    public static function rootOf(Category $category): Category
    {
        $root = $category;

        while ($root->parent) {
            $root = $root->parent;
        }

        return $root;
    }
}
