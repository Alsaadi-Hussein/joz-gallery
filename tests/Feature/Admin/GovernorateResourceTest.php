<?php

use App\Filament\Resources\Governorates\Pages\ManageGovernorates;
use App\Models\Governorate;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(owner());
    $this->governorate = Governorate::factory()->create(['delivery_fee' => 5_000]);
});

it('edits the delivery fee inline', function () {
    Livewire::test(ManageGovernorates::class)
        ->call('updateTableColumnState', 'delivery_fee', (string) $this->governorate->id, 7_500);

    expect($this->governorate->refresh()->delivery_fee)->toBe(7_500);
});

it('rejects a negative delivery fee', function () {
    Livewire::test(ManageGovernorates::class)
        ->call('updateTableColumnState', 'delivery_fee', (string) $this->governorate->id, -1);

    expect($this->governorate->refresh()->delivery_fee)->toBe(5_000);
});

it('switches a governorate off inline', function () {
    Livewire::test(ManageGovernorates::class)
        ->call('updateTableColumnState', 'is_active', (string) $this->governorate->id, false);

    expect($this->governorate->refresh()->is_active)->toBeFalse();
});

it('edits names in a modal', function () {
    Livewire::test(ManageGovernorates::class)
        ->callAction(TestAction::make('edit')->table($this->governorate), ['name_en' => 'Baghdad Governorate']);

    expect($this->governorate->refresh()->name_en)->toBe('Baghdad Governorate');
});

it('offers no way to add or delete governorates', function () {
    Livewire::test(ManageGovernorates::class)
        ->assertActionDoesNotExist('create')
        ->assertActionDoesNotExist(TestAction::make('delete')->table($this->governorate));

    expect(owner()->can('delete', $this->governorate))->toBeFalse()
        ->and(owner()->can('create', Governorate::class))->toBeFalse();
});
