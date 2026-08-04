<?php
if ( !defined('IN_XSS_PLATFORM') ) {
    exit('Access Denied');
}
require_once('load.php');

//合法文件名的正则表达式
define('FILE_REGEX', '/(?!((^(con)$)|^(con)\..*|(^(prn)$)|^(prn)\..*|(^(aux)$)|^(aux)\..*|(^(nul)$)|^(nul)\..*|(^(com)[1-9]$)|^(com)[1-9]\..*|(^(lpt)[1-9]$)|^(lpt)[1-9]\..*)|^\s+|.*\s$)(^[^\/\\\:\*\?\"\<\>\|]{1,255}$)/');

// --- XSS记录读写已迁移至 app/Storage/FileStorage.php，此处仅保留 JS 文件操作和 IP 封禁列表 ---

//读取名为$filename的js文件内容
function load_js_content( $path, $filename ) {
    if ( preg_match( FILE_REGEX, $filename ) ) {
        $file = dirname(__FILE__) . '/' . $path . '/' . $filename . '.js';
        if ( !file_exists( $file ) )
            return false;
        
        $info = @file_get_contents( $file );
        if ( $info === false )
            $info = "";
        return $info;
    }
    else
        return false;
}

//删除名为$filename的js
function delete_js( $path, $filename ) {
    if ( preg_match( FILE_REGEX, $filename ) ) {
        $file = dirname(__FILE__) . '/' . $path . '/' . $filename . '.desc';
        unlink( $file );
        $file = dirname(__FILE__) . '/' . $path . '/' . $filename . '.js';
        return unlink( $file );
    }
    else
        return false;
    
}

//清空js
function clear_js( $path ) {
    $files = glob( $path . '/*.desc' );
    foreach ( $files as $file ) {
        unlink( $file );
    }
    
    $files = glob( $path . '/*.js' );
    foreach ( $files as $file ) {
        unlink( $file );
    }
    return true;
}

//保存名为$filename的js文件内容
function save_js_content( $path, $content, $filename ) {
    if( preg_match( FILE_REGEX, $filename ) ) {
        $file = dirname(__FILE__) . '/' . $path . '/' . $filename . '.js';
        
        if ( file_put_contents( $file, $content ) === false )
            return false;
        else
            return true;
    }
    else
        return false;
}

//保存名为$filename的js文件描述
function save_js_desc( $path, $desc, $filename ) {
    if( preg_match( FILE_REGEX, $filename ) ) {
        $file = dirname(__FILE__) . '/' . $path . '/' . $filename . '.desc';
        
        $desc = \App\Helper\Crypto::encrypt( $desc );
        
        if ( file_put_contents($file, $desc) === false )
            return false;
        else
            return true;
    }
    else
        return false;

}

//获取js的名字与描述列表
function list_js_name_and_desc( $path ) {
    $list  = array();
    $files = glob( $path . '/*.js' );
    arsort( $files );
    
    foreach ( $files as $file ) {
        //由于可能有中文名，故使用正则来提取文件名
        $item           = array();
        $item['js_uri'] = $file;
        
        $filename             = preg_replace( '/^.+[\\\\\\/]/', '', $file );
        $filename             = substr( $filename, 0, strlen( $filename ) - 3 );
        $item['js_name']      = $filename;
        $item['js_name_abbr'] = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
        
        $result = @file_get_contents( dirname(__FILE__) . '/' . $path . '/' . $filename . '.desc' );
        $result = $result ? $result : "";
        
        
        $result = \App\Helper\Crypto::decrypt( $result );
        
        if ( json_encode( $result ) === false )
            $result = "加密密码不符，无法获得描述";
        
        $item['js_description']      = $result;
        $item['js_description_abbr'] = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');
        
        //特别注意：只有js_name_abbr，js_description_abbr经过htmlspecialchars处理
        $list[] = $item;
        
    }
    return $list;
}

//载入封禁的ip
function loadForbiddenIPList() {
    $forbidden_IP_list_file = DATA_PATH . '/forbiddenIPList.dat';
    !file_exists( $forbidden_IP_list_file ) && @touch( $forbidden_IP_list_file );
    $str = @file_get_contents( $forbidden_IP_list_file );
    if ( $str === false )
        return array();
    
    $str = \App\Helper\Crypto::decrypt($str);
    
    if ( $str != '' ) {
        $result = json_decode( $str, true );
        if ( $result != null )
            return $result;
        else
            return array();
    }
    else
        return array();
}

//保存封禁ip
function saveForbiddenIPList( $forbiddenIPList ) {
    $forbidden_IP_list_file = DATA_PATH . '/forbiddenIPList.dat';
    $str = json_encode( $forbiddenIPList );
    $str = \App\Helper\Crypto::encrypt( $str );
    @file_put_contents( $forbidden_IP_list_file, $str );
}