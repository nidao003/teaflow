<?php

namespace App\Trait;

use Illuminate\Http\Request;

trait DataShow
{
    use DataList;
    protected $where_show = [
        ['id' ,'{value}']
    ];
    /**
     * 检测是否可以显示
     */
    protected function checkShow($item)
    {
        return false;
    }
    /**
     * 显示
     */
    public function getDataShow(Request $request)
    {
        $model = $this->getListDataModel($request, 'where_show');
        $item = $model->first();
        if (!$item) {
            return $this->error('数据不存在');
        }
        $check = $this->checkShow($item);
        if ($check) {
            return $check;
        }
        $this->getItemData($item);
        return $item;
    }

}
