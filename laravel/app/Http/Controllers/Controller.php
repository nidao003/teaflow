<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth as Author;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
    public $user;

    protected $seller_id;
    protected $store_id;

    public function init()
    {
        global $user;
        $user = Author::user();
        if ($user) {
            if ($user->type == 'seller') {
                $user->seller ?? '';
                $user->seller->store ?? '';
            }
            $this->user = $user;
        }
        $this->seller_id = \App\Classes\Seller::getId(false);
        $this->store_id = \App\Classes\Store::getId(false);
    }
    public function callAction($method, $parameters)
    {
        $this->init();
        return parent::callAction($method, $parameters);
    }
    public function validate($request, $rules, $messages = [], $customAttributes = [])
    {
        if (!$customAttributes) {

            if (isset($this->model) && $this->model) {
                $model = $this->model;
                if (strpos($model, '\\') === false) {
                    $model = '\App\\Models\\'. $model;
                }
                try {
                    $customAttributes = $model::$labels;
                } catch (\Throwable $th) {
                }
            }
        }
        $validator = \Validator::make($request->all(), $rules, $messages, $customAttributes);
        if ($validator->fails()) {
            $arr = $validator->errors()->toArray();
            $data = [];
            foreach ($arr as $k => $v) {
                $data[$k] = $v[0];
            }
            return $this->error($validator->errors()->first(), $data);
        } else {
            return '';
        }
    }
    public function success($message = '', $data = [])
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'type' => 'success',
        ]);
    }

    public function error($message = '', $data = [])
    {
        return  response()->json([
            'code' => 250,
            'message' => $message,
            'data' => $data,
            'type' => 'error',
        ]);
    }

}
