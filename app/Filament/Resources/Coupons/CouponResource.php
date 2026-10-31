<?php

namespace App\Filament\Resources\Coupons;

use App\Enums\DiscountType;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('admin.coupon.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.coupon.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.sales');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('admin.coupon.code'))
                ->helperText(__('admin.coupon.code_hint'))
                ->required()
                ->maxLength(50)
                ->alphaDash()
                ->unique(ignoreRecord: true)
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
            TextInput::make('min_total')
                ->label(__('admin.coupon.min_total'))
                ->helperText(__('admin.coupon.min_total_hint'))
                ->required()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->suffix(__('admin.common.currency')),
            TextInput::make('usage_limit')
                ->label(__('admin.coupon.usage_limit'))
                ->helperText(__('admin.coupon.usage_limit_hint'))
                ->integer()
                ->minValue(1),
            DateTimePicker::make('expires_at')
                ->label(__('admin.coupon.expires_at'))
                ->seconds(false),
            Toggle::make('is_active')
                ->label(__('admin.common.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.coupon.code'))
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('value')
                    ->label(__('admin.offer.value'))
                    ->formatStateUsing(fn (int $state, Coupon $record): string => $record->type === DiscountType::Percent ? "{$state}%" : Money::format($state)),
                TextColumn::make('min_total')
                    ->label(__('admin.coupon.min_total'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('used_count')
                    ->label(__('admin.coupon.used_count'))
                    ->formatStateUsing(fn (int $state, Coupon $record): string => $record->usage_limit ? "{$state} / {$record->usage_limit}" : (string) $state),
                TextColumn::make('expires_at')
                    ->label(__('admin.coupon.expires_at'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
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
            'index' => ManageCoupons::route('/'),
        ];
    }

    private static function isPercent(mixed $type): bool
    {
        return in_array($type, [DiscountType::Percent, DiscountType::Percent->value], true);
    }
}
