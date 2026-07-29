<?php
namespace App\Helper;

class Request
{
    /**
     * 获取客户端真实IP地址
     */
    public static function getRealIP(): string
    {
        $ip = 'unknown';
        if (XFF_ENABLE) {
            $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                        'HTTP_FORWARDED_FOR', 'HTTP_VIA', 'HTTP_FROM', 'REMOTE_ADDR'];
            foreach ($headers as $v) {
                if (isset($_SERVER[$v]) && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $_SERVER[$v])) {
                    $ip = $_SERVER[$v];
                    break;
                }
            }
        } else {
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }
        return $ip;
    }

    /**
     * 获取所有HTTP请求头 (兼容nginx)
     */
    public static function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * 收集XSS请求的完整信息
     */
    public static function collectXssInfo(): array
    {
        $info = [];
        $info['user_IP']        = XssFilter::stripStr(self::getRealIP());
        $info['user_port']      = XssFilter::stripStr($_SERVER['REMOTE_PORT'] ?? 'unknown');
        $info['protocol']       = XssFilter::stripStr($_SERVER['SERVER_PROTOCOL'] ?? 'unknown');
        $info['request_method'] = XssFilter::stripStr($_SERVER['REQUEST_METHOD'] ?? 'unknown');
        $info['request_URI']    = XssFilter::stripStr($_SERVER['REQUEST_URI'] ?? 'unknown');
        $info['request_time']   = XssFilter::stripStr($_SERVER['REQUEST_TIME'] ?? (string) time());

        $headersData = self::getAllHeaders();
        $info['headers_data'] = XssFilter::stripArr($headersData);

        $info['get_data'] = XssFilter::stripArr($_GET);
        $decodedGet = self::tryBase64Decode($_GET);
        if ($decodedGet) {
            $info['decoded_get_data'] = XssFilter::stripArr($decodedGet);
        }

        $info['post_data'] = XssFilter::stripArr($_POST);
        $decodedPost = self::tryBase64Decode($_POST);
        if ($decodedPost) {
            $info['decoded_post_data'] = XssFilter::stripArr($decodedPost);
        }

        $info['cookie_data'] = XssFilter::stripArr($_COOKIE);
        $decodedCookie = self::tryBase64Decode($_COOKIE);
        if ($decodedCookie) {
            $info['decoded_cookie_data'] = XssFilter::stripArr($decodedCookie);
        }

        $info['keepsession'] = self::isKeepSession($info);

        return $info;
    }

    /**
     * 判断请求是否包含keepsession标记
     */
    public static function isKeepSession(array $info): bool
    {
        $sources = ['get_data', 'post_data', 'cookie_data'];
        foreach ($sources as $source) {
            foreach ($info[$source] as $k => $v) {
                if ($k === 'keepsession') {
                    return $v === '1';
                }
            }
        }
        return false;
    }

    /**
     * 尝试对数组值进行base64解码
     */
    public static function tryBase64Decode(array $arr)
    {
        if (count($arr) === 0) {
            return false;
        }
        $isChanged = false;
        $newArr = [];
        foreach ($arr as $k => $v) {
            $decoded = '';
            if (self::isBase64Formatted($v)) {
                $decoded = base64_decode($v);
                $isChanged = true;
            }
            $newArr[$k] = $decoded;
        }
        return $isChanged ? $newArr : false;
    }

    /**
     * 判断字符串是否为有效的base64编码
     */
    public static function isBase64Formatted(string $str): bool
    {
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $str)) {
            return false;
        }
        if ($str !== base64_encode(base64_decode($str))) {
            return false;
        }
        $decoded = base64_decode($str);
        return (bool) preg_match('/^[A-Za-z0-9\x00-\x80~!@#$%&_+-=:";\'<>,\/"\[\]\\\^\.\|\?\*\+\(\)\{\}\s]+$/', $decoded);
    }
}
