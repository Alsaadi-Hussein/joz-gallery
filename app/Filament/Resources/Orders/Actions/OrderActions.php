<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class OrderActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label(__('orders.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->authorize('update')
            ->visible(fn (Order $record): bool => ! $record->isCancelled())
            ->schema(fn (Order $record): array => [
                Select::make('status')
                    ->label(__('orders.fields.status'))
                    ->options(self::nextStatuses($record))
                    ->required()
                    ->live(),
                Text::make(__('orders.actions.cancel_warning'))
                    ->color('danger')
                    ->visible(fn (Get $get): bool => $get('status') === OrderStatus::Cancelled->value),
            ])
            ->successNotificationTitle(__('orders.actions.status_changed'))
            ->action(function (Order $record, array $data, Action $action): void {
                $record->changeStatus(OrderStatus::from($data['status']));
                $action->success();
            });
    }

    public static function invoice(): Action
    {
        return Action::make('invoice')
            ->label(__('orders.actions.invoice'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Order $record): string => route('filament.admin.orders.invoice', $record), shouldOpenInNewTab: true);
    }

    /**
     * @return array<string, string>
     */
    private static function nextStatuses(Order $order): array
    {
        return collect(OrderStatus::cases())
            ->reject(fn (OrderStatus $status) => $status === $order->status)
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
