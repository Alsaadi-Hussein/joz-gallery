<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('governorate'))
            ->stackedOnMobile()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('orders.fields.number'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label(__('orders.fields.date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('orders.fields.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('orders.fields.phone'))
                    ->searchable(),
                TextColumn::make('governorate.name_ar')
                    ->label(__('orders.fields.governorate')),
                TextColumn::make('total')
                    ->label(__('orders.fields.total'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('orders.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('orders.fields.status'))
                    ->options(OrderStatus::class),
                SelectFilter::make('governorate_id')
                    ->label(__('orders.fields.governorate'))
                    ->relationship('governorate', 'name_ar')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('orders.fields.from')),
                        DatePicker::make('until')->label(__('orders.fields.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class)
                    ->label(__('orders.actions.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->columnMapping(false),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    OrderActions::changeStatus(),
                    OrderActions::invoice(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
