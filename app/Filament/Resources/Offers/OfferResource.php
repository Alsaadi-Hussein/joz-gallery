<?php

namespace App\Filament\Resources\Offers;

use App\Enums\DiscountType;
use App\Enums\OfferScope;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title_ar';

    public static function getModelLabel(): string
    {
        return __('admin.offer.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.offer.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.sales');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title_ar')
                ->label(__('admin.offer.title_ar'))
                ->required()
                ->maxLength(255),
            TextInput::make('title_en')
                ->label(__('admin.offer.title_en'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr']),
            Select::make('type')
                ->label(__('admin.offer.type'))
                ->options(DiscountType::class)
                ->default(DiscountType::Percent->value)
                ->required()
                ->live(),
            TextInput::make('value')
                ->label(__('admin.offer.value'))
                ->required()
                ->integer()
                ->minValue(1)
                ->maxValue(fn (Get $get): ?int => self::isPercent($get('type')) ? 100 : null)
                ->suffix(fn (Get $get): string => self::isPercent($get('type')) ? '%' : __('admin.common.currency')),
            Select::make('scope')
                ->label(__('admin.offer.scope'))
                ->options(OfferScope::class)
                ->default(OfferScope::All->value)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('target_id', null)),
            Select::make('target_id')
                ->label(fn (Get $get): string => self::scope($get('scope')) === OfferScope::Category
                    ? __('admin.common.category')
                    : __('admin.offer.product'))
                ->options(fn (Get $get): array => self::targets(self::scope($get('scope'))))
                ->searchable()
                ->required(fn (Get $get): bool => self::scope($get('scope')) !== OfferScope::All)
                ->rule(fn (Get $get) => Rule::in(array_keys(self::targets(self::scope($get('scope'))))))
                ->visible(fn (Get $get): bool => self::scope($get('scope')) !== OfferScope::All)
                ->dehydratedWhenHidden(),
            DateTimePicker::make('starts_at')
                ->label(__('admin.offer.starts_at'))
                ->seconds(false)
                ->required(),
            DateTimePicker::make('ends_at')
                ->label(__('admin.offer.ends_at'))
                ->seconds(false)
                ->required()
                ->after('starts_at'),
            Toggle::make('is_active')
                ->label(__('admin.common.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('ends_at', 'desc')
            ->columns([
                TextColumn::make('title_ar')
                    ->label(__('admin.offer.title_ar'))
                    ->searchable(['title_ar', 'title_en']),
                TextColumn::make('value')
                    ->label(__('admin.offer.value'))
                    ->formatStateUsing(fn (int $state, Offer $record): string => $record->type === DiscountType::Percent ? "{$state}%" : Money::format($state)),
                TextColumn::make('scope')
                    ->label(__('admin.offer.scope'))
                    ->badge()
                    ->color('gray')
                    ->description(fn (Offer $record): ?string => self::targets($record->scope)[$record->target_id] ?? null),
                TextColumn::make('starts_at')
                    ->label(__('admin.offer.starts_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('admin.offer.ends_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOffers::route('/'),
        ];
    }

    private static function isPercent(mixed $type): bool
    {
        return in_array($type, [DiscountType::Percent, DiscountType::Percent->value], true);
    }

    /**
     * The form holds the scope as text while editing and as the enum once loaded.
     */
    private static function scope(mixed $scope): OfferScope
    {
        return $scope instanceof OfferScope ? $scope : (OfferScope::tryFrom((string) $scope) ?? OfferScope::All);
    }

    /**
     * What an offer with this scope can point at, as id => name.
     *
     * @return array<int, string>
     */
    private static function targets(OfferScope $scope): array
    {
        return once(fn () => match ($scope) {
            OfferScope::Product => Product::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all(),
            OfferScope::Category => Category::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all(),
            OfferScope::All => [],
        });
    }
}
