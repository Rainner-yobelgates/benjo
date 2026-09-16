<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @var array<int, string>
     */
    protected array $selectedPermissions = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedPermissions = RoleResource::extractSelectedPermissions($data);
        unset($data['permission_matrix']);

        return $data;
    }

    protected function afterCreate(): void
    {
        RoleResource::syncPermissions($this->record, $this->selectedPermissions);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\BackAction::make()
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
