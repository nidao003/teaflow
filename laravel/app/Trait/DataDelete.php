<?php

namespace App\Trait;

use Illuminate\Http\Request;

trait DataDelete
{
    use DataShow;
    /**
     * 检测是否可以删除
     */
    protected function checkDelete($item)
    {
        return false;
    }
    /**
     * 删除
     */
    public function deleteData(Request $request)
    {
        $model = $this->getListDataModel($request, 'where_show');
        $item = $model->first();
        if (!$item) {
            return $this->error('数据不存在');
        }
        $this->getItemData($item);
        $check = $this->checkDelete($item);
        if ($check) {
            return $check;
        }
        $item->delete();
    }

}
