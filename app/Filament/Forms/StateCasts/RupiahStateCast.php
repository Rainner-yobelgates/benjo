<?php

namespace App\Filament\Forms\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class RupiahStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return is_string($state)
            ? str_replace(',', '.', str_replace('.', '', $state))
            : $state;
    }

    public function set(mixed $state): mixed
    {
        if ($state === null || $state === '' || ! is_numeric($state)) {
            return $state;
        }

        return rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');
    }
}
