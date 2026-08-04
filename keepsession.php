<?php
define('IN_XSS_PLATFORM', true);
// keepsession URL白名单保护：仅允许同源或KEEP_SESSION_ALLOWED_HOSTS配置的额外主机
// 已内置协议限制（仅http/https）、重定向逐跳校验、CRLF注入防护
// 注意：DNS重绑定不在本校验范围内，请做好网络隔离

ignore_user_abort();
//Windows平台最好别设成0，因为windows上lock没法实现非阻塞
set_time_limit(0);

require_once('load.php');
require_once('dio.php');

if (KEEP_SESSION) {
    //利用非阻塞的flock实现单例运行
    $pid = fopen(DATA_PATH . '/check.pid', "w");
    if (!$pid)
        exit();

    if (flock($pid, LOCK_EX | LOCK_NB)) {
        $files = glob(DATA_PATH . '/*.php');
        $service = new \App\Service\XssRecordService();
        foreach ($files as $file) {
            $filename = basename($file, ".php");
            $info = $service->getRecord($filename);
            if (is_array($info) && ($info['keepsession'] ?? false) === true) {
                $url    = getLocation($info);
                $cookie = getCookie($info);

                $useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2535.0 Safari/537.36';
                if (isset($info['headers_data']['User-Agent']))
                    $useragent = $info['headers_data']['User-Agent'];

                $ip = $info['user_IP'];
                $allowedHosts = defined('KEEP_SESSION_ALLOWED_HOSTS') ? KEEP_SESSION_ALLOWED_HOSTS : [];
                $serverHost   = $_SERVER['HTTP_HOST'] ?? '';
                if ($url !== '' && $cookie !== '' && \App\Helper\UrlValidator::isAllowed($url, $serverHost, $allowedHosts)) {
                    // 逐跳手动跟随重定向，每跳重新校验host白名单（防止重定向SSRF绕过）
                    $maxHops = 3;
                    $currentUrl = $url;
                    $header = []; // 每次循环重置，防止跨记录cookie泄漏
                    // CRLF注入防护：剥离所有header值中的换行符
                    $header[] = 'User-Agent: ' . str_replace(["\r", "\n"], '', $useragent);
                    $header[] = 'Cookie: ' . str_replace(["\r", "\n"], '', $cookie);
                    $header[] = 'X-Forwarded-For: ' . str_replace(["\r", "\n"], '', $ip);

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, true);

                    for ($hop = 0; $hop <= $maxHops; $hop++) {
                        curl_setopt($ch, CURLOPT_URL, $currentUrl);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($response === false || $httpCode === false) break;

                        // 3xx重定向：提取Location并校验
                        if ($httpCode >= 300 && $httpCode < 400) {
                            preg_match('/^Location:\s*(.+)$/im', $response, $match);
                            if (empty($match[1])) break;
                            $nextUrl = trim($match[1]);
                            // 相对路径补全
                            if (strpos($nextUrl, 'http') !== 0) {
                                $parts = parse_url($currentUrl);
                                $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
                                $nextUrl = $base . ($nextUrl[0] === '/' ? '' : '/') . $nextUrl;
                            }
                            if (!\App\Helper\UrlValidator::isAllowed($nextUrl, $serverHost, $allowedHosts)) break;
                            $currentUrl = $nextUrl;
                        } else {
                            break; // 非重定向，停止
                        }
                    }
                    curl_close($ch);
                }
            }

        }

        //可加上sleep来防止keepsession被ddos
        //sleep(10);
        flock($pid, LOCK_UN);
    }

    fclose($pid);

}

function getCookie($info)
{
    $cookie = '';

    if (isset($info['decoded_get_data']['cookie']) && $info['decoded_get_data']['cookie'] != "")
        $cookie = $info['decoded_get_data']['cookie'];
    else if (isset($info['get_data']['cookie']) && $info['get_data']['cookie'] != "")
        $cookie = $info['get_data']['cookie'];
    else if (isset($info['decoded_post_data']['cookie']) && $info['decoded_post_data']['cookie'] != "")
        $cookie = $info['decoded_post_data']['cookie'];
    else if (isset($info['post_data']['cookie']) && $info['post_data']['cookie'] != "")
        $cookie = $info['post_data']['cookie'];
    else if (isset($info['decoded_cookie_data']['cookie']) && $info['decoded_cookie_data']['cookie'] != "")
        $cookie = $info['decoded_cookie_data']['cookie'];
    else if (isset($info['cookie_data']['cookie']) && $info['cookie_data']['cookie'] != "")
        $cookie = $info['cookie_data']['cookie'];

    return htmlspecialchars_decode($cookie, ENT_QUOTES);

}

function getLocation($info)
{
    $location = '';

    if (isset($info['decoded_get_data']['location']) && $info['decoded_get_data']['location'] != "")
        $location = $info['decoded_get_data']['location'];
    else if (isset($info['get_data']['location']) && $info['get_data']['location'] != "")
        $location = $info['get_data']['location'];
    else if (isset($info['decoded_post_data']['location']) && $info['decoded_post_data']['location'] != "")
        $location = $info['decoded_post_data']['location'];
    else if (isset($info['post_data']['location']) && $info['post_data']['location'] != "")
        $location = $info['post_data']['location'];
    else if (isset($info['decoded_cookie_data']['location']) && $info['decoded_cookie_data']['location'] != "")
        $location = $info['decoded_cookie_data']['location'];
    else if (isset($info['cookie_data']['location']) && $info['cookie_data']['location'] != "")
        $location = $info['cookie_data']['location'];
    else if (isset($info['headers_data']['Referer']) && $info['headers_data']['Referer'] != "")
        $location = $info['headers_data']['Referer'];

    return htmlspecialchars_decode($location, ENT_QUOTES);
}