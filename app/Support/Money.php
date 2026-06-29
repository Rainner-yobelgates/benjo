<?php

namespace App\Support;

class Money
{
    public static function rupiah(float | int | string | null $amount): string
    {
        return 'Rp ' . number_format((float) ($amount ?? 0), 0, ',', '.');
    }
}
