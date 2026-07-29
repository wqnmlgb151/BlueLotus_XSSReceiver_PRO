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

/** 项目过滤辅助函数 */
function filterByProject(array $records, string $project): array {
    $result = [];
    foreach ($records as $record) {
        if (($record['project'] ?? 'default') === $project) {
            $result[] = $record;
        }
    }
    return $result;
}

//与xss记录相关api
if ( isset( $_GET['cmd'] ) ) {
    switch ( $_GET['cmd'] ) {
        //获取所有记录包括详细信息
        case 'list':
            echo json_encode( list_xss_record_detail() );
            break;

        //只获取时间戳（索引id）
        case 'id_list':
            echo json_encode( list_xss_record_id() );
            break;

        //根据时间戳（索引id）获得单条信息
        case 'get':
            if ( isset( $_GET['id'] ) )
                echo json_encode( load_xss_record( $_GET['id'] ) );
            else
                echo json_encode( false );
            break;

        //根据时间戳（索引id）删除单条信息
        case 'del':
            if ( isset( $_GET['id'] ) )
                echo json_encode( delete_xss_record( $_GET['id'] ) );
            else
                echo json_encode( false );
            break;

        //清空记录
        case 'clear':
            echo json_encode( clear_xss_record() );
            break;

        //搜索记录 (支持project和keyword参数)
        case 'search':
            $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
            $project = isset($_GET['project']) ? $_GET['project'] : 'default';
            $filtered = filterByProject(list_xss_record_detail(), $project);
            if ($keyword === '') {
                echo json_encode($filtered);
            } else {
                $result = [];
                foreach ($filtered as $record) {
                    if (stripos(json_encode($record), $keyword) !== false) {
                        $result[] = $record;
                    }
                }
                echo json_encode($result);
            }
            break;

        //统计信息 (支持project参数)
        case 'stats':
            $project  = isset($_GET['project']) ? $_GET['project'] : 'default';
            $filtered = filterByProject(list_xss_record_detail(), $project);
            $today      = strtotime('today');
            $week       = strtotime('-7 days');
            $todayCount = 0; $weekCount = 0; $ipMap = [];
            foreach ($filtered as $record) {
                $time = (int) ($record['request_time'] ?? 0);
                if ($time >= $today) $todayCount++;
                if ($time >= $week) $weekCount++;
                $ip = $record['user_IP'] ?? 'unknown';
                $ipMap[$ip] = ($ipMap[$ip] ?? 0) + 1;
            }
            arsort($ipMap);
            echo json_encode([
                'total'   => count($filtered),
                'today'   => $todayCount,
                'week'    => $weekCount,
                'top_ips' => array_slice($ipMap, 0, 10),
            ]);
            break;

        //导出JSON
        case 'export_json':
            $project = isset($_GET['project']) ? $_GET['project'] : 'default';
            $result  = filterByProject(list_xss_record_detail(), $project);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="xss_export.json"');
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;

        //导出CSV
        case 'export_csv':
            $project = isset($_GET['project']) ? $_GET['project'] : 'default';
            $result  = filterByProject(list_xss_record_detail(), $project);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="xss_export.csv"');
            $fp = fopen('php://output', 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($fp, ['ID', '时间', 'IP', '位置', '项目', '请求方法', 'URL', 'User-Agent', 'Referer', 'GET参数', 'POST参数', 'Cookie']);
            foreach ($result as $r) {
                fputcsv($fp, [
                    $r['request_time'] ?? '',
                    date('Y-m-d H:i:s', (int) ($r['request_time'] ?? 0)),
                    $r['user_IP'] ?? '',
                    $r['location'] ?? '',
                    $r['project'] ?? 'default',
                    $r['request_method'] ?? '',
                    $r['request_URI'] ?? '',
                    $r['headers_data']['User-Agent'] ?? '',
                    $r['headers_data']['Referer'] ?? '',
                    json_encode($r['get_data'] ?? [], JSON_UNESCAPED_UNICODE),
                    json_encode($r['post_data'] ?? [], JSON_UNESCAPED_UNICODE),
                    json_encode($r['cookie_data'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }
            fclose($fp);
            exit;

        default:
            echo json_encode( false );
    }
}
//与js模板相关api
else if ( isset( $_GET['js_template_cmd'] ) ) {
    switch ( $_GET['js_template_cmd'] ) {
        //获取所有js模板的名字与描述
        case 'list':
            echo json_encode( list_js_name_and_desc( JS_TEMPLATE_PATH ) );
            break;

        //添加js模板
        case 'add':
            if ( isset( $_POST['name'] ) && isset( $_POST['desc'] ) && isset( $_POST['content'] ) ) {
                $result = save_js_desc( JS_TEMPLATE_PATH, $_POST['desc'], $_POST['name'] )
                && save_js_content( JS_TEMPLATE_PATH, $_POST['content'], $_POST['name'] );
                echo json_encode( $result );
            }
            else
                echo json_encode( false );

            break;

        //修改js模板
        case 'modify':
            if ( isset( $_POST['old_name'] ) && isset( $_POST['name'] ) && isset( $_POST['desc'] ) && isset( $_POST['content'] ) ) {
                $result = true;
                if ( $_POST['old_name'] != $_POST['name'] )
                    $result = delete_js( JS_TEMPLATE_PATH, $_POST['old_name'] );

                if( $result ) {
                    $result = save_js_desc( JS_TEMPLATE_PATH, $_POST['desc'], $_POST['name'] )
                    && save_js_content( JS_TEMPLATE_PATH, $_POST['content'], $_POST['name'] );
                }
                echo json_encode( $result );

            }
            else
                echo json_encode( false );

            break;

        //获取某一js模板的内容
        case 'get':
            if ( isset( $_GET['name'] ) ) {
                echo json_encode( load_js_content( JS_TEMPLATE_PATH, $_GET['name'] ) );
            }
            else
                echo json_encode( false );
            break;

        //删除js模板
        case 'del':
            if ( isset( $_GET['name'] ) ) {
                echo json_encode( delete_js( JS_TEMPLATE_PATH, $_GET['name'] ) );
            }
            else
                echo json_encode( false );
            break;

        //清空js模板
        case 'clear':
            echo json_encode( clear_js( JS_TEMPLATE_PATH ) );
            break;

        default:
            echo json_encode( false );
    }
}
//与我的js相关api
else if ( isset( $_GET['my_js_cmd'] ) ) {
    switch ( $_GET['my_js_cmd'] ) {
        //获取所有我的js的名字与描述
        case 'list':
            echo json_encode( list_js_name_and_desc( MY_JS_PATH ) );
            break;

        //添加js模板
        case 'add':
            if ( isset( $_POST['name'] ) && isset( $_POST['desc'] ) && isset( $_POST['content'] ) ) {
                $result = save_js_desc( MY_JS_PATH, $_POST['desc'], $_POST['name'] )
                && save_js_content( MY_JS_PATH, $_POST['content'], $_POST['name'] );
                echo json_encode( $result );
            }
            else
                echo json_encode( false );

            break;

        //修改js模板
        case 'modify':
            if ( isset( $_POST['old_name'] ) && isset( $_POST['name'] ) && isset( $_POST['desc'] ) && isset( $_POST['content'] ) ) {
                $result = true;
                if ( $_POST['old_name'] != $_POST['name'] )
                    $result = delete_js( MY_JS_PATH, $_POST['old_name'] );
                if( $result ) {
                    $result = save_js_desc( MY_JS_PATH, $_POST['desc'], $_POST['name'] )
                    && save_js_content( MY_JS_PATH, $_POST['content'], $_POST['name'] );
                }
                echo json_encode( $result );
            }
            else
                echo json_encode( false );

            break;

        //获取某一js模板的内容
        case 'get':
            if ( isset( $_GET['name'] ) ) {
                echo json_encode( load_js_content( MY_JS_PATH, $_GET['name'] ) );
            }
            else
                echo json_encode( false );
            break;

        //删除js模板
        case 'del':
            if ( isset( $_GET['name'] ) ) {
                echo json_encode( delete_js( MY_JS_PATH, $_GET['name'] ) );
            }
            else
                echo json_encode( false );
            break;

        //清空js模板
        case 'clear':
            echo json_encode( clear_js( MY_JS_PATH ) );
            break;

        default:
            echo json_encode( false );
    }
}
//项目管理
else if ( isset( $_GET['project_cmd'] ) ) {
    switch ( $_GET['project_cmd'] ) {
        case 'list':
            echo json_encode(\App\Config\AppConfig::projects());
            break;
        default:
            echo json_encode(false);
    }
}
else
    echo json_encode( false );
