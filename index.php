<?php
define('IN_XSS_PLATFORM', true);
ignore_user_abort(true);
error_reporting(0);

header('Access-Control-Allow-Origin: *');
require_once('load.php');

try {
    $info = \App\Helper\Request::collectXssInfo();
} catch (\InvalidArgumentException $e) {
    error_log('XSSReceiver: request parameters rejected');
    http_response_code(400);
    exit();
}

// 项目识别: 通过proj参数或token参数
$project = 'default';
if (isset($_GET['proj'])) {
    if (!is_string($_GET['proj'])) { http_response_code(400); exit(); }
    $project = \App\Helper\XssFilter::stripStr($_GET['proj']);
} elseif (isset($_GET['token'])) {
    if (!is_string($_GET['token'])) { http_response_code(400); exit(); }
    $project = \App\Config\AppConfig::getProjectByToken($_GET['token']) ?? 'default';
}
$info['project'] = $project;

if (!(new \App\Service\XssRecordService())->saveRecord($info, $project)) {
    http_response_code(500);
    exit();
}

//发送邮件通知
if (MAIL_ENABLE) {
    require_once("mail.php");
    @send_mail($info);
}
