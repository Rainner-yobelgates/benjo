<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Cashout extends Model
{
    protected $fillable = [
        'cashout_date',
        'title',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'cashout_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('cashout_date', $year);
    }
}
