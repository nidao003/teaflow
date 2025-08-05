<?php

namespace App\Models;

class Model extends \Illuminate\Database\Eloquent\Model
{
    public static $cacheStatic = [];

    public static function idCache($id)
    {
        $key =  "Model:Staitc:idCache:".str_replace("\\", "_", get_called_class()).":".$id;
        if (isset(self::$cacheStatic[$key])) {
            return self::$cacheStatic[$key];
        }
        $model = self::where('id', $id)->first();
        self::$cacheStatic[$key] = $model;
        return $model;
    }

    public static function whereCache($where = [])
    {
        $id = md5(json_encode($where));
        $key =  "Model:Staitc:whereCache:".str_replace("\\", "_", get_called_class()).":".$id;
        if (isset(self::$cacheStatic[$key])) {
            return self::$cacheStatic[$key];
        }
        $model = self::where($where)->first();
        self::$cacheStatic[$key] = $model;
        return $model;
    }

    public static function whereCacheAll($where = [])
    {
        $id = md5(json_encode($where));
        $key =  "Model:Staitc:whereCacheAll:".str_replace("\\", "_", get_called_class()).":".$id;
        if (isset(self::$cacheStatic[$key])) {
            return self::$cacheStatic[$key];
        }
        $model = self::where($where)->get();
        self::$cacheStatic[$key] = $model;
        return $model;
    }

}
