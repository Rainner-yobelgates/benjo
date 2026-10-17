<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $request->session()->forget('url.intended');

        return redirect()->to(Filament::getCurrentOrDefaultPanel()->getRedirectUrl());
    }
}
