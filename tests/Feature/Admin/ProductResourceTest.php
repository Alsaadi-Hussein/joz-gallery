<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    Storage::fake('tmp-for-tests');

    $this->actingAs(staff());
    $this->category = Category::factory()->create();
});

function productFormData(array $overrides = []): array
{
    return [
        'name_ar' => 'كنبة تجريبية',
        'name_en' => 'Test Sofa',
        'category_id' => test()->category->id,
        'slug' => 'test-sofa',
        'price' => 750_000,
        'stock' => 12,
        'gallery' => [UploadedFile::fake()->image('front.jpg', 1000, 1250)],
        ...$overrides,
    ];
}

describe('creating', function () {
    it('saves a product with its image and conversions', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData())
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'test-sofa')->firstOrFail();
        $media = $product->getFirstMedia('gallery');

        expect($product->price)->toBe(750_000)
            ->and($product->is_active)->toBeTrue()
            ->and($media)->not->toBeNull()
            ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
            ->and($media->hasGeneratedConversion('large'))->toBeTrue();

        Storage::disk('public')->assertExists($media->getPathRelativeToRoot('thumb'));
    });

    it('does not keep the uploaded file name', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData())
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Product::query()->firstOrFail()->getFirstMedia('gallery')->file_name)->not->toContain('front');
    });

    it('requires at least one image', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['gallery' => []]))
            ->call('create')
            ->assertHasFormErrors(['gallery' => 'required']);
    });

    it('rejects more than ten images', function () {
        $images = array_map(fn (int $n) => UploadedFile::fake()->image("{$n}.jpg"), range(1, 11));

        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['gallery' => $images]))
            ->call('create')
            ->assertHasFormErrors(['gallery']);
    });

    it('rejects images over 5 MB', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['gallery' => [UploadedFile::fake()->image('big.jpg')->size(5121)]]))
            ->call('create')
            ->assertHasFormErrors(['gallery']);

        expect(Product::query()->count())->toBe(0);
    });

    it('rejects files that are not jpeg, png or webp', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['gallery' => [UploadedFile::fake()->create('shell.php', 10, 'text/x-php')]]))
            ->call('create')
            ->assertHasFormErrors(['gallery']);

        expect(Product::query()->count())->toBe(0);
    });

    it('judges an upload by its contents, not its name', function () {
        // Livewire trusts a faked file's reported type in tests, so this uses a real
        // temporary file: the same detection a production upload goes through.
        Storage::disk('tmp-for-tests')->put('livewire-tmp/photo.jpg', '<?php echo "hello";');
        $file = TemporaryUploadedFile::createFromLivewire('photo.jpg');

        $validator = Validator::make(['file' => $file], ['file' => 'mimetypes:image/jpeg,image/png,image/webp']);

        expect($file->getMimeType())->toBe('text/x-php')
            ->and($validator->fails())->toBeTrue();
    });

    it('refuses a disguised file at the media library as well', function () {
        $path = tempnam(sys_get_temp_dir(), 'joz');
        file_put_contents($path, '<?php echo "hello";');

        Product::factory()->create()->addMedia($path)->toMediaCollection('gallery');
    })->throws(FileUnacceptableForCollection::class);

    it('never stores an upload under a php name', function () {
        ob_start();
        imagejpeg(imagecreatetruecolor(20, 20));
        Storage::disk('tmp-for-tests')->put('livewire-tmp/evil.php', ob_get_clean());

        $file = TemporaryUploadedFile::createFromLivewire('evil.php');

        expect($file->getMimeType())->toBe('image/jpeg')
            ->and($file->hashName())->toMatch('/^[A-Za-z0-9]{40}\.jpe?g$/');
    });

    it('rejects a duplicate slug', function () {
        Product::factory()->create(['slug' => 'test-sofa']);

        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData())
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    });

    it('rejects a duplicate sku', function () {
        Product::factory()->create(['sku' => 'JG-1']);

        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['sku' => 'JG-1']))
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique']);
    });

    it('rejects a negative or fractional price', function (mixed $price) {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData(['price' => $price]))
            ->call('create')
            ->assertHasFormErrors(['price']);
    })->with([-1, 10.5]);

    it('fills the slug from the English name', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name_en' => 'Oak Dining Table'])
            ->assertSchemaStateSet(['slug' => 'oak-dining-table']);
    });

    it('saves variants', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(productFormData())
            ->set('data.variants', [[
                'color_ar' => 'أحمر',
                'color_en' => 'Red',
                'size' => 'L',
                'price_delta' => -5_000,
                'stock' => 3,
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $variant = Product::query()->firstOrFail()->variants->sole();

        expect($variant->price_delta)->toBe(-5_000)
            ->and($variant->color_en)->toBe('Red');
    });
});

describe('editing', function () {
    it('keeps the slug when the English name changes', function () {
        $product = Product::factory()->create(['slug' => 'keep-me']);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name_en' => 'Another Name'])
            ->assertSchemaStateSet(['slug' => 'keep-me']);
    });

    it('shows the final price from the price being typed', function () {
        $product = Product::factory()->for($this->category)->create(['price' => 100_000]);
        $product->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('gallery');
        Offer::factory()->forProduct($product)->percent(10)->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertSee('90,000 د.ع')
            ->fillForm(['price' => 200_000])
            ->assertSee('180,000 د.ع');

        expect($product->refresh()->price)->toBe(100_000);
    });
});

describe('the table', function () {
    it('lists products with their final price', function () {
        $product = Product::factory()->for($this->category)->create(['price' => 100_000]);
        Offer::factory()->forProduct($product)->percent(25)->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$product])
            ->assertTableColumnStateSet('final_price', 75_000, $product);
    });

    it('searches Arabic name, English name and sku', function (string $search) {
        $match = Product::factory()->create(['name_ar' => 'مرآة ذهبية', 'name_en' => 'Golden Mirror', 'sku' => 'JG-555']);
        $other = Product::factory()->create(['name_ar' => 'كرسي', 'name_en' => 'Chair', 'sku' => 'JG-111']);

        Livewire::test(ListProducts::class)
            ->searchTable($search)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    })->with(['مرآة', 'Golden', 'JG-555']);

    it('filters by category and active state', function () {
        $wanted = Product::factory()->for($this->category)->create();
        $otherCategory = Product::factory()->create();
        $hidden = Product::factory()->for($this->category)->create(['is_active' => false]);

        Livewire::test(ListProducts::class)
            ->filterTable('category_id', $this->category->id)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$otherCategory, $hidden]);
    });

    it('filters low stock using variant stock when there are variants', function () {
        $low = Product::factory()->create(['stock' => 2]);
        $plenty = Product::factory()->create(['stock' => 50]);
        $lowVariants = Product::factory()->create(['stock' => 50]);
        ProductVariant::factory()->for($lowVariants)->count(2)->create(['stock' => 1]);
        $plentyVariants = Product::factory()->create(['stock' => 0]);
        ProductVariant::factory()->for($plentyVariants)->create(['stock' => 40]);

        Livewire::test(ListProducts::class)
            ->filterTable('low_stock')
            ->assertCanSeeTableRecords([$low, $lowVariants])
            ->assertCanNotSeeTableRecords([$plenty, $plentyVariants]);
    });

    it('shows variant stock instead of the product stock', function () {
        $product = Product::factory()->create(['stock' => 50]);
        ProductVariant::factory()->for($product)->count(2)->create(['stock' => 4]);

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('effective_stock', 8, $product);
    });

    it('toggles visibility from the table', function () {
        $product = Product::factory()->create(['is_active' => true]);

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $product->id, false);

        expect($product->refresh()->is_active)->toBeFalse();
    });
});

describe('replicating', function () {
    it('copies the product and variants but not images', function () {
        $product = Product::factory()->create(['slug' => 'walnut-table', 'sku' => 'JG-9']);
        $product->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('gallery');
        ProductVariant::factory()->for($product)->count(2)->create();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('replicate')->table($product))
            ->assertHasNoErrors();

        $copy = Product::query()->whereKeyNot($product->id)->firstOrFail();

        expect($copy->slug)->toBe('walnut-table-copy')
            ->and($copy->sku)->toBeNull()
            ->and($copy->is_active)->toBeFalse()
            ->and($copy->name_en)->toBe($product->name_en)
            ->and($copy->price)->toBe($product->price)
            ->and($copy->variants)->toHaveCount(2)
            ->and($copy->getMedia('gallery'))->toBeEmpty()
            ->and($product->fresh()->variants)->toHaveCount(2)
            ->and($product->getMedia('gallery'))->toHaveCount(1);
    });

    it('opens the copy for editing', function () {
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('replicate')->table($product))
            ->assertRedirect(ProductResource::getUrl('edit', ['record' => Product::query()->latest('id')->first()]));
    });

    it('gives each copy its own slug', function () {
        $product = Product::factory()->create(['slug' => 'lamp']);

        expect($product->duplicate()->slug)->toBe('lamp-copy')
            ->and($product->duplicate()->slug)->toBe('lamp-copy-2')
            ->and($product->duplicate()->slug)->toBe('lamp-copy-3');
    });

    it('does not pile suffixes onto a copy of a copy', function () {
        $copy = Product::factory()->create(['slug' => 'lamp'])->duplicate();

        expect($copy->duplicate()->slug)->toBe('lamp-copy-2');
    });
});

describe('bulk actions', function () {
    it('moves selected products to another category', function () {
        $products = Product::factory()->count(2)->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($products->pluck('id')->all())
            ->callAction(TestAction::make('move_to_category')->table()->bulk(), ['category_id' => $this->category->id]);

        expect(Product::query()->where('category_id', $this->category->id)->count())->toBe(2);
    });

    it('hides selected products', function () {
        $products = Product::factory()->count(2)->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($products->pluck('id')->all())
            ->callAction(TestAction::make('hide')->table()->bulk());

        expect(Product::query()->where('is_active', true)->count())->toBe(0);
    });

    it('deletes selected products', function () {
        $products = Product::factory()->count(2)->create();

        Livewire::test(ListProducts::class)
            ->selectTableRecords($products->pluck('id')->all())
            ->callAction(TestAction::make('delete')->table()->bulk());

        expect(Product::query()->count())->toBe(0);
    });
});
