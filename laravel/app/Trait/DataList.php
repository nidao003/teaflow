<?php

namespace App\Trait;

use Illuminate\Http\Request;

trait DataList
{
    protected $model = '\App\Models\Article';
    protected $orderBy = [
        'id' => 'desc',
    ];
    protected $where = [
        'type_id' => 1,
        [
          'title', 'like','%{value}%',
        ],
        ['title', '{value}'],
    ];
    /**
     * 显示关联字段
     */
    protected $relation = [
         //'type.articles',
    ];
    /**
     * 分页显示
     */
    public function getDataPage(Request $request, $paginate = 20)
    {
        $model = $this->getListDataModel($request);
        $all = $model->paginate($paginate);
        $this->getLoopItem($all);
        return $all;
    }
    /**
     * 列表显示
     */
    public function getDataList(Request $request)
    {
        $model = $this->getListDataModel($request);
        $all = $model->get();
        $this->getLoopItem($all);
        return $all;
    }
    /**
     * 处理item
     */
    protected function getLoopItem(&$all)
    {
        $index = 1;
        $all->each(function ($item) use (&$index) {
            $item->index = $index++;
            $this->getItemData($item, $index);
        });
    }
    /**
     * 处理一行记录
     */
    protected function getItemData(&$item)
    {
        $relation = $this->relation;
        if ($relation) {
            foreach ($relation as $val) {
                if (strpos($val, '.') !== false) {
                    $val = explode('.', $val);
                    $a0 = $val[0];
                    $a1 = $val[1] ?? '';
                    $a2 = $val[2] ?? '';
                    $a3 = $val[3] ?? '';
                    if ($a3) {
                        $item->$a0->$a1->$a2->$a3;
                    } elseif ($a2) {
                        $item->$a0->$a1->$a2;
                    } elseif ($a1) {
                        $item->$a0->$a1;
                    } else {
                        $item->$a0;
                    }
                }
            }
        }
    }
    /**
     * model
     */
    protected function getListDataModel(Request $request, $where_name = 'where')
    {
        $model = $this->model;
        $orderBy = $this->orderBy;
        if (strpos($model, '\\') === false) {
            $model = '\App\Models\\'.$model;
        }
        $model = new $model();
        if ($orderBy) {
            foreach ($orderBy as $key => $val) {
                $model = $model->orderBy($key, $val);
            }
        }
        if ($this->$where_name) {
            foreach ($this->$where_name as $val) {
                $key = $val[0];
                $value_0 = $val[1] ?? '';
                $value_1 = $val[2] ?? '';
                $value = $request->get($key);
                if ($value) {
                    if ($value_1) {
                        $value = str_replace("{value}", $request->get($key), $value_1);
                        if ($value) {
                            $model = $model->where($key, $val[1], $value);
                        }
                    } else {
                        $model = $model->where($key, $value);
                    }
                } else {
                    if ($where_name == 'where_show' && count($this->$where_name) == 1) {
                        $model = $model->where($key, '');
                    }
                }
            }
        }
        return $model;
    }

}
