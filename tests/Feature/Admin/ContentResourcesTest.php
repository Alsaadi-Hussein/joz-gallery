<?php

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Banner;
use App\Models\Page;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('banners', function () {
    beforeEach(fn () => $this->actingAs(owner()));

    it('creates a banner at the end of the slider', function () {
        Banner::factory()->create(['sort_order' => 3]);

        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title_ar' => 'عرض جديد',
                'title_en' => 'New offer',
                'link' => '/offers',
                'image' => [UploadedFile::fake()->image('hero.jpg', 1600, 900)],
                'image_mobile' => [UploadedFile::fake()->image('hero-m.jpg', 900, 1120)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::query()->where('title_en', 'New offer')->firstOrFail();

        expect($banner->sort_order)->toBe(4)
            ->and($banner->getFirstMedia('image')->hasGeneratedConversion('large'))->toBeTrue()
            ->and($banner->getFirstMedia('image_mobile'))->not->toBeNull();
    });

    it('requires the main image', function () {
        Livewire::test(CreateBanner::class)
            ->fillForm(['title_ar' => 'عنوان', 'title_en' => 'Title', 'image' => []])
            ->call('create')
            ->assertHasFormErrors(['image' => 'required']);
    });

    it('rejects a link that is not a path or web address', function () {
        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title_ar' => 'عنوان',
                'title_en' => 'Title',
                'link' => 'javascript:alert(1)',
                'image' => [UploadedFile::fake()->image('hero.jpg')],
            ])
            ->call('create')
            ->assertHasFormErrors(['link']);
    });

    it('saves a drag and drop order', function () {
        $first = Banner::factory()->create(['sort_order' => 0]);
        $second = Banner::factory()->create(['sort_order' => 1]);

        Livewire::test(ListBanners::class)
            ->call('reorderTable', [(string) $second->id, (string) $first->id]);

        expect($second->refresh()->sort_order)->toBeLessThan($first->refresh()->sort_order);
    });
});

describe('pages', function () {
    beforeEach(fn () => $this->actingAs(owner()));

    it('creates a page from the rich editor', function () {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title_ar' => 'الضمان',
                'title_en' => 'Warranty Terms',
                'body_ar' => '<p>ضمان لمدة سنة</p>',
                'body_en' => '<p>One year warranty</p>',
            ])
            ->assertSchemaStateSet(['slug' => 'warranty-terms'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Page::query()->where('slug', 'warranty-terms')->firstOrFail()->body_en)->toContain('One year warranty');

        $this->get('/en/page/warranty-terms')->assertOk()->assertSee('One year warranty');
    });

    it('edits page text', function () {
        $page = Page::factory()->create(['slug' => 'delivery']);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title_ar' => 'التوصيل'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($page->refresh()->title_ar)->toBe('التوصيل');
    });

    it('protects the pages the menus link to', function () {
        $core = Page::factory()->create(['slug' => 'about']);
        $custom = Page::factory()->create(['slug' => 'warranty']);

        Livewire::test(EditPage::class, ['record' => $core->id])->assertFormFieldDisabled('slug');
        Livewire::test(EditPage::class, ['record' => $custom->id])->assertFormFieldEnabled('slug');

        Livewire::test(ListPages::class)
            ->assertActionHidden(TestAction::make('delete')->table($core))
            ->callAction(TestAction::make('delete')->table($custom));

        expect(Page::query()->pluck('slug')->all())->toBe(['about']);
    });
});

describe('settings', function () {
    beforeEach(fn () => $this->seed(SettingSeeder::class));

    it('blocks staff by direct URL', function () {
        $this->actingAs(staff());

        $this->get(ManageSettings::getUrl())->assertForbidden();
    });

    it('stops staff from saving even through the component', function () {
        $this->actingAs(staff());

        Livewire::test(ManageSettings::class)->assertForbidden();
    });

    it('hides the menu item from staff', function () {
        $this->actingAs(staff());

        $this->get('/admin')->assertDontSee(__('admin.settings.title'));
    });

    it('blocks staff from banners and pages too', function () {
        $this->actingAs(staff());

        $this->get(BannerResource::getUrl())->assertForbidden();
        $this->get(PageResource::getUrl())->assertForbidden();
    });

    it('shows the owner the saved values', function () {
        $this->actingAs(owner());

        $this->get(ManageSettings::getUrl())->assertOk();

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['whatsapp_number' => '9647740243538', 'low_stock_threshold' => '5']);
    });

    it('saves changes and uses them straight away', function () {
        $this->actingAs(owner());

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'store_phone' => '07701112233',
                'whatsapp_number' => '9647701112233',
                'instagram' => 'new.gallery',
                'address' => 'بغداد، الكرادة',
                'low_stock_threshold' => '2',
                'notification_email' => 'orders@example.com',
                'telegram_chat_id' => '-100123',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect(Setting::lowStockThreshold())->toBe(2)
            ->and(Setting::get('telegram_chat_id'))->toBe('-100123');

        $this->get('/')
            ->assertSee('+964 770 111 2233')
            ->assertSee('@new.gallery')
            ->assertSee('بغداد، الكرادة');
    });

    it('rejects badly formatted values', function (string $field, string $value) {
        $this->actingAs(owner());

        Livewire::test(ManageSettings::class)
            ->fillForm([$field => $value])
            ->call('save')
            ->assertHasFormErrors([$field]);
    })->with([
        ['store_phone', '12345'],
        ['whatsapp_number', '07740243538'],
        ['instagram', '@joz gallery'],
        ['low_stock_threshold', '-1'],
        ['notification_email', 'not-an-email'],
        ['telegram_chat_id', 'abc'],
    ]);
});
