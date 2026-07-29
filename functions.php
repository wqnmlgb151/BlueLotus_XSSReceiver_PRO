<?php
if (!defined('IN_XSS_PLATFORM')) {
    exit('Access Denied');
}

require_once('load.php');

//nginx无getallheaders函数
if (!function_exists('getallheaders')) {
    function getallheaders() {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

//判断该记录是否keepsession
function isKeepSession($info) {
    return \App\Helper\Request::isKeepSession($info);
}

//xss过滤
function stripStr($str) {
    return \App\Helper\XssFilter::stripStr($str);
}

function stripArr($arr) {
    return \App\Helper\XssFilter::stripArr($arr);
}

//尝试base64解码
function tryBase64Decode($arr) {
    return \App\Helper\Request::tryBase64Decode($arr);
}

//判断string是否为base64编码
function isBase64Formatted($str) {
    return \App\Helper\Request::isBase64Formatted($str);
}

//加密/解密
function encrypt($info) {
    return \App\Helper\Crypto::encrypt($info);
}

function decrypt($info) {
    return \App\Helper\Crypto::decrypt($info);
}

//获得访问者真实ip
function getRealIP() {
    return \App\Helper\Request::getRealIP();
}

//基于Discuz X3.1 function_misc.php 函数已过滤，可直接输出
function convertIP($ip, $ipdatafile) {
    return \App\Helper\IPLocator::convertIP($ip, $ipdatafile);
}
