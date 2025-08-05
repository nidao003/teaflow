<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Illuminate\Validation\Rule;
use App\Http\Middleware\AdminAuth;
use hg\apidoc\annotation as Apidoc;
use App\Models\ProductMix;

/**
 * @Apidoc\Title("平台-小料")
 */
#[Prefix('api/v1/admin/product_mix')]
#[Middleware(AdminAuth::class)]
class ProductMixController extends \App\Http\Controllers\Controller
{
    use \App\Trait\DataList;
    use \App\Trait\DataForm;
    use \App\Trait\DataShow;
    use \App\Trait\DataDelete;

    public function init()
    {
        parent::init();
        $this->model = 'ProductMix';

        // list
        $this->orderBy = [
            'sort' => 'desc',
            'id' => 'desc',
        ];
        $this->where = [
            //['title','like','%{value}%'],
        ];

        // form
        $this->createValidate = [
            'title' => 'required|unique:product_mix,title',
            'price' => 'required|numeric|min:0',
        ];
        $this->updateValidate = [
            'title' => [
                'required',
                Rule::unique('product_mix')->ignore(request()->get('id')),
            ],
            'price' => 'required|numeric|min:0',
        ];
    }
    /**
    * @Apidoc\Title("单条记录")
    * @Apidoc\Tag("用户")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/product_mix/show")
    * @Apidoc\Query("id", type="string",require=false, desc="")
    *
    */
    #[Post('show')]
    public function show(Request $request)
    {
        $data = $this->getDataShow($request);
        return $this->success('', $data);
    }
    /**
    * @Apidoc\Title("创建|修改小料")
    * @Apidoc\Tag("用户")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/product_mix/save")
    * @Apidoc\Query("id", type="string",require=false, desc="")
    * @Apidoc\Query("title", type="string",require=false, desc="小料名")
    * @Apidoc\Query("price", type="string",require=false, desc="价格")
    * @Apidoc\Query("sort", type="string",require=false, desc="排序")
    *
    */
    #[Post('save')]
    public function save(Request $request)
    {
        return $this->saveDataForm($request);
    }

    /**
    * @Apidoc\Title("列表")
    * @Apidoc\Tag("用户")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/product_mix/index")
    * @Apidoc\Query("title", type="string",require=false, desc="小料名")
    */
    #[Post('index')]
    public function index(Request $request)
    {
        $data = $this->getDataList($request);
        return $this->success('请求成功', $data);
    }
    /**
    * @Apidoc\Title("删除一条记录")
    * @Apidoc\Tag("用户")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/product_mix/delete")
    * @Apidoc\Query("id", type="string",require=false, desc="")
    *
    */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $res = $this->deleteData($request);
        if ($res) {
            return $this->error($res);
        }
        return $this->success('删除成功');
    }

    /**
     * @Apidoc\Title("排序")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product_mix/sort")
     * @Apidoc\Param("data",type="array",desc="排序数据",children={
     *     @Apidoc\Param ("id",type="string",desc="id"),
     * })
    */
    #[Post('sort')]
    public function sort(Request $request)
    {
        $data = $request->input('data');
        if (!$data) {
            return $this->error("参数错误");
        }
        $count = count($data);
        foreach ($data as $key => $value) {
            ProductMix::where('id', $value['id'])->update(['sort' => $count--]);
        }
        return $this->success("操作成功");
    }

}
