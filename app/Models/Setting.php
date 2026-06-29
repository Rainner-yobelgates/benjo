<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'shop_name',
        'logo',
        'address',
        'phone_number',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'shop_name' => '',
        ]);
    }
}
