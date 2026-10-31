<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

#[Signature('sitemap:generate')]
#[Description('Write the sitemap with every public page in both languages')]
class GenerateSitemap extends Command
{
    private const LOCALES = ['ar', 'en'];

    public function handle(): int
    {
        $sitemap = Sitemap::create();

        foreach (['home', 'offers', 'best-sellers', 'new'] as $route) {
            $this->add($sitemap, $route);
        }

        Category::query()->active()->each(
            fn (Category $category) => $this->add($sitemap, 'category', $category, $category->updated_at),
        );

        Product::query()->visible()->each(
            fn (Product $product) => $this->add($sitemap, 'product', $product, $product->updated_at),
        );

        Page::query()->where('is_active', true)->each(
            fn (Page $page) => $this->add($sitemap, 'page', $page, $page->updated_at),
        );

        // Kept in storage and served by a route, so it works wherever public/ ends up on the host.
        $sitemap->writeToDisk('local', 'sitemap.xml');

        $this->info('Sitemap written.');

        return self::SUCCESS;
    }

    /**
     * Adds one page in each language, every entry pointing at both versions.
     */
    private function add(Sitemap $sitemap, string $route, mixed $parameters = [], ?DateTimeInterface $modified = null): void
    {
        foreach (self::LOCALES as $locale) {
            $url = Url::create(shop_route($route, $parameters, $locale));

            foreach (self::LOCALES as $alternate) {
                $url->addAlternate(shop_route($route, $parameters, $alternate), $alternate);
            }

            if ($modified) {
                $url->setLastModificationDate($modified);
            }

            $sitemap->add($url);
        }
    }
}
