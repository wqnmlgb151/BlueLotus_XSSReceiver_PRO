<?php
namespace App\Controller;

abstract class JsFileController
{
    abstract protected static function path(): string;

    public static function handle(string $cmd)    {
        $path = static::path();

        switch ($cmd) {
            case 'list':
                return list_js_name_and_desc($path);

            case 'add':
                if (isset($_POST['name'], $_POST['desc'], $_POST['content'])) {
                    return save_js_desc($path, $_POST['desc'], $_POST['name'])
                        && save_js_content($path, $_POST['content'], $_POST['name']);
                }
                return false;

            case 'modify':
                if (isset($_POST['old_name'], $_POST['name'], $_POST['desc'], $_POST['content'])) {
                    $result = true;
                    if ($_POST['old_name'] !== $_POST['name']) {
                        $result = delete_js($path, $_POST['old_name']);
                    }
                    if ($result) {
                        $result = save_js_desc($path, $_POST['desc'], $_POST['name'])
                            && save_js_content($path, $_POST['content'], $_POST['name']);
                    }
                    return $result;
                }
                return false;

            case 'get':
                return isset($_GET['name']) ? load_js_content($path, $_GET['name']) : false;

            case 'del':
                return isset($_GET['name']) ? delete_js($path, $_GET['name']) : false;

            case 'clear':
                return clear_js($path);

            default:
                return false;
        }
    }
}
