<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.user.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.store');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.user.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('admin.user.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr']),
            Select::make('role')
                ->label(__('admin.user.role'))
                ->options(UserRole::class)
                ->default(UserRole::Staff)
                ->required()
                // Owners cannot demote themselves, so the store always keeps an owner.
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
            TextInput::make('password')
                ->label(__('admin.user.password'))
                ->helperText(fn (string $operation): string => $operation === 'create'
                    ? __('admin.user.password_hint')
                    : __('admin.user.password_keep_hint'))
                ->password()
                ->revealable()
                ->rule(Password::defaults())
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.user.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin.user.email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin.user.role'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
