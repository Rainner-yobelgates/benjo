<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use App\Models\Setting;
use Filament\Resources\Pages\Page;

class RedirectSetting extends Page
{
    protected static string $resource = SettingResource::class;

    public function mount(): void
    {
        $this->redirect(
            SettingResource::getUrl('edit', ['record' => Setting::current()]),
            navigate: true,
        );
    }
}
