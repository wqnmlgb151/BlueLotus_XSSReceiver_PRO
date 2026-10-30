<?php
error_reporting(0);
define('IN_XSS_PLATFORM', true);
require_once('auth.php');
require_once('dio.php');
header('Content-Type: application/json');

// 所有 API 请求（包括导出）均校验会话 Token。
$csrf_valid = false;
if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && isset($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrf_valid = true;
}
if (!$csrf_valid) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid CSRF token']));
}

// PHP accepts array query parameters; these API fields are strings by contract.
foreach (['cmd', 'js_template_cmd', 'my_js_cmd', 'project_cmd', 'project', 'keyword', 'id', 'name'] as $field) {
    if (isset($_GET[$field]) && !is_string($_GET[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid parameter type']));
    }
}
foreach (['name', 'old_name', 'desc', 'content'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid parameter type']));
    }
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
