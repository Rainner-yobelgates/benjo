<?php

namespace App\Filament\Forms\Components;

use App\Filament\Forms\StateCasts\RupiahStateCast;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

class RupiahInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $cast = new RupiahStateCast;

        $this->prefix('Rp')
            ->inputMode('decimal')
            ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
            ->stateCast($cast)
            ->mutateStateForValidationUsing(fn (mixed $state): mixed => $cast->get($state))
            ->rule('numeric')
            ->minValue(0);
    }
}
