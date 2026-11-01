<?php

namespace App\Filament\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\OrderSummary;
use App\Support\SpreadsheetText;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Downloaders\XlsxDownloader;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')->label(__('orders.fields.number')),
            ExportColumn::make('created_at')
                ->label(__('orders.fields.date'))
                ->formatStateUsing(fn (Carbon $state): string => $state->format('Y-m-d H:i')),
            ExportColumn::make('status')
                ->label(__('orders.fields.status'))
                ->formatStateUsing(fn (OrderStatus $state): string => $state->getLabel()),
            ExportColumn::make('name')->label(__('orders.fields.name')),
            ExportColumn::make('phone')->label(__('orders.fields.phone')),
            ExportColumn::make('governorate.name_ar')->label(__('orders.fields.governorate')),
            ExportColumn::make('area')->label(__('orders.fields.area')),
            ExportColumn::make('landmark')->label(__('orders.fields.landmark')),
            ExportColumn::make('notes')->label(__('orders.fields.notes')),
            ExportColumn::make('items')
                ->label(__('orders.fields.items'))
                ->state(fn (Order $record): string => OrderSummary::items($record)),
            ExportColumn::make('subtotal')->label(__('orders.fields.subtotal')),
            ExportColumn::make('discount')->label(__('orders.fields.discount')),
            ExportColumn::make('delivery_fee')->label(__('orders.fields.delivery_fee')),
            ExportColumn::make('total')->label(__('orders.fields.total')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['items', 'governorate']);
    }

    /**
     * Strangers fill in the checkout and staff open this file in Excel, where a name
     * starting with "=" would otherwise run as a formula.
     *
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        return array_map(SpreadsheetText::escape(...), parent::__invoke($record));
    }

    /**
     * @return array<ExportFormat>
     */
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    /**
     * getFormats() only hides the choice: the download link still takes ?format=csv.
     */
    public static function getDownloader(ExportFormatInterface $format): Downloader
    {
        return new XlsxDownloader;
    }

    /**
     * Exports hold customer names and phone numbers, so they must never reach the
     * public disk.
     */
    public function getFileDisk(): string
    {
        return 'local';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('orders.actions.export_done', ['count' => number_format($export->successful_rows)]);

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.__('orders.actions.export_failed', ['count' => number_format($failed)]);
        }

        return $body;
    }
}
