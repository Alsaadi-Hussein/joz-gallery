<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('orders.sections.customer'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->label(__('orders.fields.number'))->weight('bold')->copyable(),
                    TextEntry::make('created_at')->label(__('orders.fields.date'))->dateTime('Y-m-d H:i'),
                    TextEntry::make('status')->label(__('orders.fields.status'))->badge(),
                    TextEntry::make('name')->label(__('orders.fields.name')),
                    TextEntry::make('phone')->label(__('orders.fields.phone'))->copyable(),
                    TextEntry::make('governorate.name_ar')->label(__('orders.fields.governorate')),
                    TextEntry::make('area')->label(__('orders.fields.area')),
                    TextEntry::make('landmark')->label(__('orders.fields.landmark')),
                    TextEntry::make('notes')->label(__('orders.fields.notes'))->placeholder('—'),
                ]),
            Section::make(__('orders.sections.items'))
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('name_snapshot')->label(__('orders.fields.item')),
                            TextEntry::make('variant_snapshot')->label(__('orders.fields.variant'))->placeholder('—'),
                            self::money('unit_price', __('orders.fields.unit_price')),
                            TextEntry::make('qty')->label(__('orders.fields.qty')),
                            self::money('line_total', __('orders.fields.line_total')),
                        ]),
                ]),
            Section::make(__('orders.sections.totals'))
                ->columnSpanFull()
                ->columns(5)
                ->schema([
                    self::money('subtotal', __('orders.fields.subtotal')),
                    self::money('discount', __('orders.fields.discount')),
                    TextEntry::make('coupon.code')->label(__('orders.fields.coupon'))->placeholder('—'),
                    self::money('delivery_fee', __('orders.fields.delivery_fee')),
                    self::money('total', __('orders.fields.total'))->weight('bold'),
                ]),
        ]);
    }

    private static function money(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->formatStateUsing(fn (int $state): string => Money::format($state));
    }
}
