<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Base extends Model
{
    public static function updateWithFillable($id, array $data)
    {
        $instance = self::find($id);
        if ($instance) {
            $instance->fill($data);
            return $instance->save();
        }

        return false;
    }

}
