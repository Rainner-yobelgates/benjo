<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    /**
     * Layanan daftar harga yang dipilih di form (lihat CreateTransaction).
     *
     * @var  array<int, int>
     */
    protected array $priceListPicks = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->priceListPicks = TransactionResource::extractPriceListPicks($data);
        unset($data['price_list_picks']);

        return $data;
    }

    protected function afterSave(): void
    {
        TransactionResource::syncPriceListServices($this->record, $this->priceListPicks);

        $this->record->refresh();
        $this->record->recalculateTotals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(route('transactions.print', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
