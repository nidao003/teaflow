<?php

namespace App\Classes;

class Price
{
    public static function format($yuan, $dot = 2)
    {
        return bcmul($yuan, 1, $dot);
    }
}
