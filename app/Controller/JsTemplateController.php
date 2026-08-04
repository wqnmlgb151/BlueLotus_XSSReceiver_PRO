<?php
namespace App\Controller;

final class JsTemplateController extends JsFileController
{
    protected static function path(): string
    {
        return JS_TEMPLATE_PATH;
    }
}
