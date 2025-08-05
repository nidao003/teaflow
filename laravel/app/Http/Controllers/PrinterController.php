<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use App\Classes\WeixinApplet;
use App\Models\User;

/**
 * @Apidoc\Title("打印回调")
 */
class PrinterController extends Controller
{
    use \App\Classes\TraitPrinterFeie;
    /**
    * @Apidoc\Title("回调")
    * @Apidoc\Tag("打印")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api_printer_callback")
    */
    #[Post('api_printer_callback')]
    public function back(Request $request)
    {
        $all = $request->all();
        return $this->doBack($all);
    }


}
