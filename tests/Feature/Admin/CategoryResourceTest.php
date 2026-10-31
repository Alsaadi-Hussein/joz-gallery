<?php

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->actingAs(staff());
});

it('creates a category with an image at the end of the order', function () {
    Category::factory()->create(['sort_order' => 4]);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name_ar' => 'الحدائق',
            'name_en' => 'Outdoor Living',
            'image' => [UploadedFile::fake()->image('garden.jpg')],
        ])
        ->assertSchemaStateSet(['slug' => 'outdoor-living'])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()->where('slug', 'outdoor-living')->firstOrFail();

    expect($category->sort_order)->toBe(5)
        ->and($category->getFirstMedia('image')->hasGeneratedConversion('thumb'))->toBeTrue();
});

it('nests a category under a parent', function () {
    $parent = Category::factory()->create();

    Livewire::test(CreateCategory::class)
        ->fillForm(['name_ar' => 'كنب', 'name_en' => 'Sofas', 'parent_id' => $parent->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'sofas')->firstOrFail()->parent->is($parent))->toBeTrue();
});

it('keeps a category and its children out of its own parent picker', function () {
    $category = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $category->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);
    $unrelated = Category::factory()->create();

    expect($category->descendantIds())->toEqualCanonicalizing([$child->id, $grandchild->id]);

    Livewire::test(EditCategory::class, ['record' => $category->id])
        ->fillForm(['parent_id' => $child->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);

    Livewire::test(EditCategory::class, ['record' => $category->id])
        ->fillForm(['parent_id' => $unrelated->id])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('saves a drag and drop order', function () {
    $first = Category::factory()->create(['sort_order' => 0]);
    $second = Category::factory()->create(['sort_order' => 1]);

    Livewire::test(ListCategories::class)
        ->call('reorderTable', [(string) $second->id, (string) $first->id]);

    expect($second->refresh()->sort_order)->toBeLessThan($first->refresh()->sort_order);
});

it('refuses to delete a category that has products', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    Livewire::test(EditCategory::class, ['record' => $category->id])
        ->assertActionHidden(DeleteAction::class);

    expect(staff()->can('delete', $category))->toBeFalse();
});

it('refuses to delete a category that has children', function () {
    $category = Category::factory()->create();
    Category::factory()->create(['parent_id' => $category->id]);

    expect(staff()->can('delete', $category))->toBeFalse();
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    Livewire::test(ListCategories::class)
        ->callAction(TestAction::make('delete')->table($category));

    expect(Category::query()->count())->toBe(0);
});

it('skips non-empty categories in a bulk delete', function () {
    $empty = Category::factory()->create();
    $used = Category::factory()->create();
    Product::factory()->for($used)->create();

    Livewire::test(ListCategories::class)
        ->selectTableRecords([$empty->id, $used->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(Category::query()->pluck('id')->all())->toBe([$used->id]);
});
