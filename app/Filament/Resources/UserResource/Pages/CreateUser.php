<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected int|string|null $roleId = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        UserResource::syncRole($this->record, $this->roleId);
    }
}
