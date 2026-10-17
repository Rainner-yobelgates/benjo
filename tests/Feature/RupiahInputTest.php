<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\RupiahInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Tests\TestCase;

class RupiahInputTest extends TestCase
{
    public function test_money_is_formatted_and_saved_without_changing_its_value(): void
    {
        $livewire = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public ?array $data = [];
        };
        $schema = Schema::make($livewire)->statePath('data')
            ->components([RupiahInput::make('amount')->required()]);

        foreach ([['100000.00', '100.000'], ['100000.50', '100.000,5'], ['0.00', '0'], ['1234567.89', '1.234.567,89']] as [$value, $display]) {
            $schema->fill(['amount' => $value]);
            $this->assertSame($display, $livewire->data['amount']);
            $this->assertEquals((float) $value, (float) $schema->getState()['amount']);
        }

        $livewire->data['amount'] = '250.000';
        $this->assertSame('250000', $schema->getState()['amount']);
        $livewire->data['amount'] = '250.000,75';
        $this->assertSame('250000.75', $schema->getState()['amount']);

        foreach (['-1.000', 'abc', ''] as $invalid) {
            $livewire->data['amount'] = $invalid;
            try {
                $schema->getState();
                $this->fail('Invalid money input was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.amount', $exception->errors());
            }
        }
    }
}
