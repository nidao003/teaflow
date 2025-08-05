<?php

namespace App\Trait;

use Illuminate\Http\Request;

trait DataForm
{
    protected $createSussess = '保存成功';
    protected $updateSussess = '更新成功';
    protected $model = '\App\Models\Article';
    protected $createValidate = [
        'title' => 'required',
    ];
    protected $updateValidate = [
        'title' => 'required',
    ];
    public function saveDataForm(Request $request)
    {
        $data = $request->all();
        $id = $request->input('id');
        $model = $this->model;
        $orderBy = $this->orderBy;
        if (strpos($model, '\\') === false) {
            $model = '\App\Models\\'.$model;
        }
        try {
            if ($id) {
                $err = $this->validate($request, $this->updateValidate);
                if ($err) {
                    return $err;
                }
                $where = [
                    'id' => $id,
                ];
                $model = $model::where($where)->first();
                $model->update($data);
                return $this->success($this->updateSussess);
            } else {
                $err = $this->validate($request, $this->createValidate);
                if ($err) {
                    return $err;
                }
                $model = new $model();
                $model->fill($data);
                $model->save();
                return $this->success($this->createSussess);
            }
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

}
