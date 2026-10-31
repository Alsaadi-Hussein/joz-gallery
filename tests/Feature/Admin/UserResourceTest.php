<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

const STRONG_PASSWORD = 'Str0ng-Pass_word!';

beforeEach(function () {
    $this->owner = owner();
    $this->actingAs($this->owner);
});

it('creates a staff user with a hashed password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'موظف',
            'email' => 'staff@example.com',
            'role' => UserRole::Staff->value,
            'password' => STRONG_PASSWORD,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'staff@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Staff)
        ->and(Hash::check(STRONG_PASSWORD, $user->password))->toBeTrue();
});

it('rejects weak passwords', function (string $password) {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'موظف',
            'email' => 'staff@example.com',
            'role' => UserRole::Staff->value,
            'password' => $password,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
})->with([
    'too short' => 'Ab1!',
    'no symbols' => 'Abcdefghij1234',
    'no numbers' => 'Abcdefghij!!!!',
    'one case' => 'abcdefghij12!!',
]);

it('keeps the password when the field is left empty', function () {
    $user = staff();
    $hash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'اسم جديد', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->password)->toBe($hash)
        ->and($user->name)->toBe('اسم جديد');
});

it('does not let owners demote themselves', function () {
    Livewire::test(EditUser::class, ['record' => $this->owner->id])
        ->assertFormFieldDisabled('role')
        ->fillForm(['role' => UserRole::Staff->value])
        ->call('save');

    expect($this->owner->refresh()->isOwner())->toBeTrue();
});

it('lets owners change the role of others', function () {
    $user = staff();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertFormFieldEnabled('role')
        ->fillForm(['role' => UserRole::Owner->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->isOwner())->toBeTrue();
});

it('does not let owners delete themselves', function () {
    expect($this->owner->can('delete', $this->owner))->toBeFalse();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->owner));
});

it('lets owners delete other users', function () {
    $user = staff();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($user));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});
