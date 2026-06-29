<?php

namespace App\Filament\Resources\CashoutResource\Pages;

use App\Filament\Resources\CashoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashouts extends ListRecords
{
    protected static string $resource = CashoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
