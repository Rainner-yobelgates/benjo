<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected Width|string|null $maxWidth = Width::Medium;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getCurrentOrDefaultPanel()->getRedirectUrl());

            return;
        }

        parent::mount();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('name')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'name' => $data['name'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.name' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
