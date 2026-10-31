<?php

use App\Http\Middleware\SetLocale;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\Models\Export;
use Filament\Auth\Pages\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

describe('the uploads folder', function () {
    beforeEach(function () {
        $this->htaccess = file_get_contents(storage_path('app/public/.htaccess'));
    });

    it('turns off folder listings', function () {
        expect($this->htaccess)->toContain('Options -Indexes');
    });

    it('refuses to serve anything executable', function (string $file) {
        preg_match('/<FilesMatch "(.+)">/', $this->htaccess, $matches);

        expect(preg_match('/'.str_replace('(?i:', '(?:', $matches[1]).'/i', $file))->toBe(1);
    })->with(['shell.php', 'shell.PHP', 'shell.php8', 'shell.phtml', 'shell.phar', 'run.pl', 'run.py', 'run.cgi', 'run.sh']);

    it('still serves images', function (string $file) {
        preg_match('/<FilesMatch "(.+)">/', $this->htaccess, $matches);

        expect(preg_match('/'.str_replace('(?i:', '(?:', $matches[1]).'/i', $file))->toBe(0);
    })->with(['photo.jpg', 'photo.webp', 'photo.png', 'php.jpg']);

    it('is committed with the project', function () {
        expect(file_get_contents(storage_path('app/public/.gitignore')))->toContain('!.htaccess');
    });
});

describe('response headers', function () {
    it('are set on storefront pages and the admin', function (string $path) {
        $this->get($path)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    })->with(['/', '/admin/login']);

    it('force https only on a secure connection', function () {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    });
});

describe('https', function () {
    afterEach(fn () => URL::forceHttps(false));

    it('is used for every link when the site address is https', function () {
        config(['app.url' => 'https://joz.example']);

        (new AppServiceProvider(app()))->boot();

        expect(url('/offers'))->toStartWith('https://');
    });

    it('stays off while the site address is http', function () {
        expect(url('/offers'))->toStartWith('http://');
    });
});

describe('errors in production', function () {
    beforeEach(function () {
        config(['app.debug' => false]);

        Route::get('/boom', fn () => throw new RuntimeException('secret database password'));
    });

    it('never shows the details', function () {
        $this->get('/boom')
            ->assertStatus(500)
            ->assertSee(__('Server Error'))
            ->assertDontSee('secret database password')
            ->assertDontSee('RuntimeException');
    });

    it('has a page that needs no database', function () {
        $view = file_get_contents(resource_path('views/errors/minimal.blade.php'));

        expect($view)->not->toContain('@extends')->not->toContain('Setting::');
    });

    it('explains an expired page in Arabic', function () {
        Route::post('/expired', fn () => abort(419));

        $this->post('/expired')->assertStatus(419)->assertSee('انتهت صلاحية الصفحة');
    });

    it('uses English under /en', function () {
        Route::get('/en/boom', fn () => throw new RuntimeException('nope'))->middleware('locale:en');

        $this->get('/en/boom')->assertStatus(500)->assertSee('Server Error')->assertSee('dir="ltr"', false);
    });
});

describe('admin login', function () {
    beforeEach(fn () => RateLimiter::clear('livewire-rate-limiter'));

    it('stops after five wrong passwords', function () {
        $user = User::factory()->owner()->create(['password' => 'Str0ng-Pass_word!']);

        $component = Livewire::test(Login::class);

        foreach (range(1, 5) as $attempt) {
            $component->fillForm(['email' => $user->email, 'password' => 'wrong'])->call('authenticate');
        }

        $component->fillForm(['email' => $user->email, 'password' => 'Str0ng-Pass_word!'])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest();
    });
});

describe('forms and models', function () {
    it('needs a CSRF token on every shop form', function (string $view) {
        expect(file_get_contents(resource_path("views/shop/{$view}.blade.php")))->toContain('@csrf');
    })->with(['checkout', 'track']);

    it('rejects a checkout without a CSRF token', function () {
        $this->app->instance('env', 'production');
        config(['app.env' => 'production']);

        $response = $this->withMiddleware()->post('/checkout', []);

        expect($response->status())->toBe(419);
    });

    it('never lets a request set a protected field', function (string $model, string $field) {
        expect((new $model)->isFillable($field))->toBeFalse();
    })->with([
        [User::class, 'remember_token'],
        [Order::class, 'id'],
        [Product::class, 'id'],
    ]);

    it('keeps every model behind an allow-list', function () {
        $models = collect(File::files(app_path('Models')))
            ->map(fn ($file) => 'App\\Models\\'.$file->getFilenameWithoutExtension())
            ->filter(fn (string $class) => is_subclass_of($class, Model::class));

        expect($models)->not->toBeEmpty();

        $models->each(fn (string $class) => expect((new $class)->getFillable())->not->toBeEmpty());
    });
});

describe('proxies', function () {
    afterEach(fn () => TrustProxies::flushState());

    it('trusts nobody by default, so nobody can fake their address past a rate limit', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        Route::get('/ip', fn () => request()->ip());

        $this->get('/ip')->assertSee('203.0.113.5');
    });

    it('reads the real address once a proxy is configured', function () {
        config(['joz.trusted_proxies' => '*']);

        (new AppServiceProvider(app()))->boot();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);

        Route::get('/ip', fn () => request()->ip());

        $this->get('/ip')->assertSee('1.2.3.4');
    });
});

describe('backups', function () {
    it('keeps the database and uploads only', function () {
        expect(config('backup.backup.source.files.include'))->toBe([storage_path('app/public')])
            ->and(config('backup.backup.source.databases'))->toContain('mysql');
    });

    it('keeps seven days and nothing older', function () {
        expect(config('backup.cleanup.default_strategy'))->toMatchArray([
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
        ]);
    });

    it('stores them outside the public folder', function () {
        expect(config('backup.backup.destination.disks'))->toBe(['local'])
            ->and(config('filesystems.disks.local.root'))->not->toContain('public');
    });

    it('runs every night', function () {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

        expect($commands)->toContain('backup:run')->toContain('backup:clean');
    });
});

describe('old exports', function () {
    function exportFrom(CarbonInterface $date): Export
    {
        $export = new Export(['exporter' => 'x', 'total_rows' => 1, 'file_disk' => 'local', 'file_name' => 'orders']);
        $export->user()->associate(owner());
        $export->created_at = $date;
        $export->save();

        Storage::disk('local')->put($export->getFileDirectory().'/data.csv', 'سارة,07701234567');

        return $export;
    }

    it('are deleted with their files after thirty days', function () {
        Storage::fake('local');

        $old = exportFrom(now()->subDays(31));
        $recent = exportFrom(now()->subDays(5));

        $this->artisan('exports:prune')->assertSuccessful();

        expect(Export::query()->pluck('id')->all())->toBe([$recent->id]);

        Storage::disk('local')->assertMissing($old->getFileDirectory().'/data.csv');
        Storage::disk('local')->assertExists($recent->getFileDirectory().'/data.csv');
    });
});

describe('settings left empty in .env', function () {
    afterEach(function () {
        unset($_ENV['PUBLIC_DISK_ROOT'], $_SERVER['PUBLIC_DISK_ROOT']);
    });

    it('keep images in the normal folder', function () {
        $_ENV['PUBLIC_DISK_ROOT'] = $_SERVER['PUBLIC_DISK_ROOT'] = '';

        $config = require config_path('filesystems.php');

        expect($config['disks']['public']['root'])->toBe(storage_path('app/public'));
    });

    it('can still move images when the host needs it', function () {
        $_ENV['PUBLIC_DISK_ROOT'] = $_SERVER['PUBLIC_DISK_ROOT'] = '/home/user/public_html/storage';

        $config = require config_path('filesystems.php');

        expect($config['disks']['public']['root'])->toBe('/home/user/public_html/storage');
    });

    it('never stop the backup package from booting', function () {
        $_ENV['BACKUP_NOTIFICATION_EMAIL'] = $_SERVER['BACKUP_NOTIFICATION_EMAIL'] = '';

        $config = require config_path('backup.php');

        unset($_ENV['BACKUP_NOTIFICATION_EMAIL'], $_SERVER['BACKUP_NOTIFICATION_EMAIL']);

        expect(filter_var($config['notifications']['mail']['to'], FILTER_VALIDATE_EMAIL))->not->toBeFalse();
    });
});

describe('the production settings example', function () {
    beforeEach(fn () => $this->example = file_get_contents(base_path('.env.example')));

    it('is safe by default', function () {
        expect($this->example)
            ->toContain('APP_ENV=production')
            ->toContain('APP_DEBUG=false')
            ->toContain('SESSION_SECURE_COOKIE=true')
            ->toContain('QUEUE_CONNECTION=database')
            ->toContain('QUEUE_CONVERSIONS_BY_DEFAULT=false')
            ->toContain('SEED_DEMO=false');
    });

    it('holds no secrets', function () {
        expect($this->example)->toMatch('/^APP_KEY=$/m')
            ->toMatch('/^DB_PASSWORD=$/m')
            ->toMatch('/^ADMIN_PASSWORD=$/m')
            ->toMatch('/^TELEGRAM_BOT_TOKEN=$/m')
            ->toMatch('/^MAIL_PASSWORD=$/m');
    });

    it('documents every setting the project reads', function () {
        $used = collect(File::allFiles(config_path()))
            ->whereIn('filename', ['joz.php', 'backup.php'])
            ->flatMap(fn ($file) => str($file->getContents())->matchAll('/env\(\'([A-Z_]+)\'/'))
            ->merge(['TELEGRAM_BOT_TOKEN', 'MAIL_TIMEOUT', 'PUBLIC_DISK_ROOT', 'DB_DUMP_BINARY_PATH', 'IMAGE_DRIVER'])
            ->unique();

        $used->each(fn (string $name) => expect($this->example)->toContain($name));
    });
});

it('does not list the locale middleware as trusted for the admin', function () {
    $panelMiddleware = collect(Route::getRoutes()->getByName('filament.admin.pages.dashboard')->gatherMiddleware());

    expect($panelMiddleware)->not->toContain(SetLocale::class);
});

it('keeps offers from breaking a product page when their target is gone', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['slug' => 'orphan']);
    Offer::factory()->forProduct($product)->percent(10)->create();

    $other = Product::factory()->create(['slug' => 'other']);
    $product->delete();

    $this->get('/product/other')->assertOk();

    expect($other->exists)->toBeTrue();
});
