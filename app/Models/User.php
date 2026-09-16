<?php

namespace App\Models;

use App\Support\Access;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'password', 'commission_percent', 'commission_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }

    /**
     * Whether this user holds the "master" role.
     *
     * Master users bypass every permission check (see Gate::before in
     * AppServiceProvider).
     */
    public function isMaster(): bool
    {
        return $this->hasRole(Access::MASTER_ROLE);
    }

    /**
     * Whether this user currently receives commission on transactions.
     */
    public function hasActiveCommission(): bool
    {
        return (bool) ($this->commission_active ?? false)
            && (float) ($this->commission_percent ?? 0) > 0;
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TransactionCommission::class);
    }

    public function participatingTransactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'transaction_participants')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'commission_percent' => 'decimal:2',
            'commission_active' => 'boolean',
        ];
    }
}
