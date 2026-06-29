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

    protected function afterSave(): void
    {
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
