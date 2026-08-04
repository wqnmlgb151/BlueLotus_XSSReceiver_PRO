<?php
error_reporting(0);
define('IN_XSS_PLATFORM', true);
require_once('auth.php');
require_once('dio.php');
header('Content-Type: application/json');

//CSRF防御 - Token验证 (兼容旧Referer检查)
$csrf_valid = false;
if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && isset($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrf_valid = true;
}
if (!$csrf_valid && isset($_SERVER['HTTP_REFERER'])) {
    $referer_array = parse_url($_SERVER['HTTP_REFERER']);
    if (isset($referer_array['host']) && $referer_array['host'] === $_SERVER['HTTP_HOST']) {
        $csrf_valid = true;
    }
}
if (!$csrf_valid) {
    exit('Access Denied');
}

// 路由分发: XSS记录 / JS模板 / 我的JS / 项目配置
if (isset($_GET['cmd'])) {
    echo json_encode(\App\Controller\XssRecordController::handle($_GET['cmd']));
} elseif (isset($_GET['js_template_cmd'])) {
    echo json_encode(\App\Controller\JsTemplateController::handle($_GET['js_template_cmd']));
} elseif (isset($_GET['my_js_cmd'])) {
    echo json_encode(\App\Controller\MyJsController::handle($_GET['my_js_cmd']));
} elseif (isset($_GET['project_cmd'])) {
    echo json_encode(\App\Controller\ProjectController::handle($_GET['project_cmd']));
} else {
    echo json_encode(false);
}
