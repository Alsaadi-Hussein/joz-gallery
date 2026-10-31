<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;

describe('sitemap', function () {
    afterEach(function () {
        @unlink(public_path('sitemap.xml'));
    });

    function sitemap(): string
    {
        test()->artisan('sitemap:generate')->assertSuccessful();

        return file_get_contents(public_path('sitemap.xml'));
    }

    it('lists public pages in both languages', function () {
        Category::factory()->create(['slug' => 'sofas']);
        Product::factory()->create(['slug' => 'green-sofa']);
        Page::factory()->create(['slug' => 'about']);

        expect(sitemap())
            ->toContain('<loc>'.url('/').'</loc>')
            ->toContain('<loc>'.url('/en').'</loc>')
            ->toContain('<loc>'.url('/offers').'</loc>')
            ->toContain('<loc>'.url('/category/sofas').'</loc>')
            ->toContain('<loc>'.url('/en/category/sofas').'</loc>')
            ->toContain('<loc>'.url('/product/green-sofa').'</loc>')
            ->toContain('<loc>'.url('/en/product/green-sofa').'</loc>')
            ->toContain('<loc>'.url('/page/about').'</loc>');
    });

    it('links each page to its other language', function () {
        Product::factory()->create(['slug' => 'green-sofa']);

        expect(sitemap())
            ->toContain('hreflang="ar" href="'.url('/product/green-sofa').'"')
            ->toContain('hreflang="en" href="'.url('/en/product/green-sofa').'"');
    });

    it('leaves out hidden content', function () {
        Category::factory()->create(['slug' => 'hidden-section', 'is_active' => false]);
        Product::factory()->create(['slug' => 'hidden-product', 'is_active' => false]);
        Page::factory()->create(['slug' => 'hidden-page', 'is_active' => false]);

        expect(sitemap())
            ->not->toContain('hidden-section')
            ->not->toContain('hidden-product')
            ->not->toContain('hidden-page');
    });

    it('never lists private pages', function () {
        expect(sitemap())
            ->not->toContain('/cart')
            ->not->toContain('/checkout')
            ->not->toContain('/admin');
    });
});

describe('scheduled work', function () {
    function scheduled(): array
    {
        return collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression])->all();
    }

    it('rebuilds the sitemap daily', function () {
        $sitemap = collect(scheduled())->first(fn (array $event) => str_contains($event[0], 'sitemap:generate'));

        expect($sitemap[1])->toBe('0 0 * * *');
    });

    it('works the queue every minute and then stops', function () {
        $queue = collect(scheduled())->first(fn (array $event) => str_contains($event[0], 'queue:work'));

        expect($queue[0])->toContain('--stop-when-empty')
            ->and($queue[1])->toBe('* * * * *');
    });
});

describe('robots.txt', function () {
    it('points to the sitemap on the current domain', function () {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.url('sitemap.xml'));
    });

    it('keeps private areas out in both languages', function () {
        $this->get('/robots.txt')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /checkout')
            ->assertSee('Disallow: /en/checkout')
            ->assertDontSee('Disallow: /product');
    });
});
