<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource;
use App\Support\Access;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Tests\TestCase;

class RolePermissionToggleStateTest extends TestCase
{
    public function test_module_and_individual_toggles_update_the_actual_form_state(): void
    {
        $livewire = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public ?array $data = [];
        };

        $schema = RoleResource::form(Schema::make($livewire)->statePath('data'))
            ->operation('create');
        $schema->fill();

        foreach (Access::allGrouped() as $moduleKey => $module) {
            $all = $schema->getComponentByStatePath("data.permission_matrix.{$moduleKey}.all", withAbsoluteStatePath: true);
            $all->state(true)->callAfterStateUpdated();

            foreach (array_keys($module['actions']) as $action) {
                $this->assertTrue(data_get($livewire->data, "permission_matrix.{$moduleKey}.{$action}"));
            }

            $action = array_key_first($module['actions']);
            $individual = $schema->getComponentByStatePath("data.permission_matrix.{$moduleKey}.{$action}", withAbsoluteStatePath: true);
            $individual->state(false)->callAfterStateUpdated();
            $this->assertFalse(data_get($livewire->data, "permission_matrix.{$moduleKey}.all"));

            $individual->state(true)->callAfterStateUpdated();
            $this->assertTrue(data_get($livewire->data, "permission_matrix.{$moduleKey}.all"));

            $all->state(false)->callAfterStateUpdated();
            foreach (array_keys($module['actions']) as $action) {
                $this->assertFalse(data_get($livewire->data, "permission_matrix.{$moduleKey}.{$action}"));
            }
        }

        $this->assertSame([], RoleResource::extractSelectedPermissions($livewire->data));
    }
}
