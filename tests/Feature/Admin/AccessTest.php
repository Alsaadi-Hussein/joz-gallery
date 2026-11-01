<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Governorates\GovernorateResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get(ProductResource::getUrl())->assertRedirect('/admin/login');
});

it('shows the login page in Arabic and right to left', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ar"', false);
});

it('lets panel users in outside local too', function () {
    $user = staff();

    expect($user)->toBeInstanceOf(FilamentUser::class)
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('lets staff open products and categories', function () {
    $this->actingAs(staff());

    $this->get(ProductResource::getUrl())->assertOk();
    $this->get(ProductResource::getUrl('create'))->assertOk();
    $this->get(CategoryResource::getUrl())->assertOk();
    $this->get(CategoryResource::getUrl('create'))->assertOk();
});

it('blocks staff from governorates and users by direct URL', function () {
    $user = staff();

    $this->actingAs($user);

    $this->get(GovernorateResource::getUrl())->assertForbidden();
    $this->get(UserResource::getUrl())->assertForbidden();
    $this->get(UserResource::getUrl('create'))->assertForbidden();
    $this->get(UserResource::getUrl('edit', ['record' => $user]))->assertForbidden();
});

it('hides owner menus from staff', function () {
    $this->actingAs(staff());

    $this->get(ProductResource::getUrl())
        ->assertSee(__('admin.product.plural'))
        ->assertDontSee(__('admin.governorate.plural'))
        ->assertDontSee(__('admin.user.plural'));
});

it('lets the owner open everything', function () {
    $this->actingAs(owner());

    $this->get('/admin')->assertOk();
    $this->get(ProductResource::getUrl())->assertOk();
    $this->get(CategoryResource::getUrl())->assertOk();
    $this->get(GovernorateResource::getUrl())->assertOk();
    $this->get(UserResource::getUrl())->assertOk();
});

it('enforces the staff allow-list in policies', function (string $model) {
    $staff = staff();
    $owner = owner();
    $record = new $model;

    expect($staff->can('viewAny', $model))->toBeFalse()
        ->and($staff->can('create', $model))->toBeFalse()
        ->and($staff->can('update', $record))->toBeFalse()
        ->and($staff->can('delete', $record))->toBeFalse()
        ->and($owner->can('viewAny', $model))->toBeTrue()
        ->and($owner->can('update', $record))->toBeTrue();
})->with([
    'offers' => Offer::class,
    'coupons' => Coupon::class,
    'banners' => Banner::class,
    'pages' => Page::class,
    'settings' => Setting::class,
    'governorates' => Governorate::class,
    'users' => User::class,
]);

it('gives staff products and categories', function (string $model) {
    $staff = staff();

    expect($staff->can('viewAny', $model))->toBeTrue()
        ->and($staff->can('create', $model))->toBeTrue()
        ->and($staff->can('update', new $model))->toBeTrue();
})->with([
    'products' => Product::class,
    'categories' => Category::class,
]);

it('lets staff work on orders but not delete them', function () {
    $staff = staff();
    $order = Order::factory()->create();

    expect($staff->can('viewAny', Order::class))->toBeTrue()
        ->and($staff->can('update', $order))->toBeTrue()
        ->and($staff->can('delete', $order))->toBeFalse()
        ->and($staff->can('deleteAny', Order::class))->toBeFalse()
        ->and(owner()->can('delete', $order))->toBeFalse()
        ->and(owner()->can('delete', Order::factory()->create(['status' => OrderStatus::Cancelled])))->toBeTrue();
});
