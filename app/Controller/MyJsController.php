<?php
namespace App\Controller;

final class MyJsController extends JsFileController
{
    protected static function path(): string
    {
        return MY_JS_PATH;
    }
}
