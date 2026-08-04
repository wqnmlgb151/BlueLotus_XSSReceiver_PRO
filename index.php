<?php
define('IN_XSS_PLATFORM', true);
ignore_user_abort(true);
error_reporting(0);

header('Access-Control-Allow-Origin: *');
require_once('load.php');

$info = \App\Helper\Request::collectXssInfo();

// 项目识别: 通过proj参数或token参数
$project = 'default';
if (isset($_GET['proj'])) {
    $project = \App\Helper\XssFilter::stripStr($_GET['proj']);
} elseif (isset($_GET['token'])) {
    $project = \App\Config\AppConfig::getProjectByToken($_GET['token']) ?? 'default';
}
$info['project'] = $project;

(new \App\Service\XssRecordService())->saveRecord($info, $project);

//发送邮件通知
if (MAIL_ENABLE) {
    require_once("mail.php");
    @send_mail($info);
}