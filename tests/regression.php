<?php
// CLI-only regression checks; all writes stay in a newly created temporary directory.
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
define('IN_XSS_PLATFORM', true);
$sandbox = sys_get_temp_dir() . '/bluelotus-test-' . bin2hex(random_bytes(8));
mkdir($sandbox);
define('DATA_PATH', $sandbox);
define('IPDATA_PATH', $sandbox . '/missing.dat');
define('ENCRYPT_ENABLE', false);
define('XFF_ENABLE', false);
require dirname(__DIR__) . '/app/autoload.php';

$failed = 0;
function check($condition, $message) {
    global $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) { $failed++; }
}
try {
    $input = ['nested' => ['<key>' => '<script>'], 'empty' => '', 'bad' => "\xFF"];
    $escaped = \App\Helper\XssFilter::stripArr($input);
    check($escaped['nested']['&lt;key&gt;'] === '&lt;script&gt;', 'nested input escaped');
    check($escaped['bad'] === "\xEF\xBF\xBD", 'invalid UTF-8 substituted');
    $decoded = \App\Helper\Request::tryBase64Decode(['n' => ['v' => base64_encode('hello!')], 'plain' => '!']);
    check($decoded['n']['v'] === 'hello!' && $decoded['plain'] === '', 'nested Base64 keeps legacy empty non-decoded cells');
    check(\App\Helper\Request::tryBase64Decode(['n' => ['v' => '!']]) === false, 'no Base64 returns false');
    $deep = ['v' => 'x'];
    for ($i = 0; $i < 32; $i++) { $deep = ['n' => $deep]; }
    check(is_array(\App\Helper\XssFilter::stripArr($deep)), 'depth boundary accepted');
    $deep = ['v' => 'x'];
    for ($i = 0; $i < 33; $i++) { $deep = ['n' => $deep]; }
    try { \App\Helper\XssFilter::stripArr($deep); check(false, 'depth rejected'); }
    catch (\InvalidArgumentException $e) { check(true, 'depth rejected'); }
    try { \App\Helper\Request::tryBase64Decode($deep); check(false, 'Base64 depth rejected'); }
    catch (\InvalidArgumentException $e) { check(true, 'Base64 depth rejected'); }
    try { \App\Helper\XssFilter::stripArr(['v' => new stdClass()]); check(false, 'object rejected'); }
    catch (\InvalidArgumentException $e) { check(true, 'object rejected'); }
    $_GET = ['a' => ['v' => '<b>']]; $_POST = []; $_COOKIE = [];
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    check(\App\Helper\Request::collectXssInfo()['get_data']['a']['v'] === '&lt;b&gt;', 'collection accepts nested parameters');

    $storage = new \App\Storage\FileStorage(DATA_PATH, IPDATA_PATH);
    foreach (['1700000000' => 'default', '1700000001' => 'other'] as $id => $project) {
        check($storage->save(['request_time' => (string) $id, 'user_IP' => '127.0.0.1', 'project' => $project,
            'get_data' => ['q' => '中文 Hello', 'n' => ['x' => '&lt;b&gt;']]], (string) $id), 'fixture saved');
    }
    $before = hash_file('sha256', DATA_PATH . '/1700000000.php');
    $service = new \App\Service\XssRecordService();
    check(count($service->searchRecords('中文')) === 1, 'Chinese keyword and default project');
    check(count($service->searchRecords('hello', 'other')) === 1, 'case insensitive English and other project');
    check(count($service->searchRecords('absent')) === 0, 'no match');
    check(count($service->listRecords()) === 2, 'unfiltered legacy list');
    check($service->getStats('other')['total'] === 1, 'project statistics');
    check(count(json_decode($service->exportJson('other'), true)) === 1, 'project JSON export');
    check(strpos($service->exportCsv('other'), '中文') !== false, 'CSV retains nested fixture');
    check(hash_file('sha256', DATA_PATH . '/1700000000.php') === $before, 'list search stats export do not write records');
    $_GET = ['project' => 'other'];
    check(count(\App\Controller\XssRecordController::handle('list')) === 1, 'controller project filter');
    $_GET = [];
    check(count(\App\Controller\XssRecordController::handle('list')) === 2, 'controller legacy list');
    file_put_contents(DATA_PATH . '/1700000002.php', 'broken');
    check($storage->load('1700000002') === null, 'corrupt record rejected');
    $storage->save(['request_time'=>'1700000002','user_IP'=>['bad']], '1700000002');
    check($storage->load('1700000002') === null, 'malformed record field rejected without TypeError');
    check(!$storage->save(['bad' => "\xFF"], '1700000000'), 'encoding failure reported');
    check(hash_file('sha256', DATA_PATH . '/1700000000.php') === $before, 'encoding failure preserves existing record');
    check(!$storage->save([], '../escape'), 'invalid write ID rejected');
    $missing = new \App\Storage\FileStorage(DATA_PATH . '/missing', IPDATA_PATH);
    check(!$missing->save(['request_time' => '1700000000'], '1700000000'), 'missing directory failure reported');
    mkdir(DATA_PATH . '/1700000003.php');
    check(!$storage->clearAll(), 'partial clear failure reported');
    rmdir(DATA_PATH . '/1700000003.php');
} catch (\Throwable $e) {
    check(false, get_class($e) . ': ' . $e->getMessage());
} finally {
    foreach (glob($sandbox . '/*') as $file) {
        if (is_file($file)) { unlink($file); }
    }
    rmdir($sandbox);
}
exit($failed ? 1 : 0);
