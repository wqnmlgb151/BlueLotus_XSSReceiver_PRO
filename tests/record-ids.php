<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
define('IN_XSS_PLATFORM', true);
$sandbox = sys_get_temp_dir() . '/bluelotus-ids-' . bin2hex(random_bytes(8));
mkdir($sandbox);
define('DATA_PATH', $sandbox);
define('IPDATA_PATH', $sandbox . '/missing.dat');
define('ENCRYPT_ENABLE', ($argv[1] ?? 'off') !== 'off');
define('ENCRYPT_TYPE', $argv[1] ?? 'RC4');
define('ENCRYPT_PASS', 'synthetic-test-key');
require dirname(__DIR__) . '/app/autoload.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
$failed = false;
function verify($ok, $label) {
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed = $failed || !$ok;
}
try {
    $storage = new \App\Storage\FileStorage(DATA_PATH, IPDATA_PATH);
    $old = '1700000000'; $new = $old . '-' . str_repeat('a', 32);
    $record = ['request_time' => $old, 'user_IP' => '127.0.0.1', 'project' => 'default', 'get_data' => ['q' => '中文']];
    verify($storage->save($record, $old) && $storage->save($record, $new), 'mixed fixtures');
    $hash = hash_file('sha256', DATA_PATH . '/' . $old . '.php');
    $service = new \App\Service\XssRecordService();
    verify($service->getRecord($old)['id'] === $old && $service->getRecord($new)['id'] === $new, 'IDs from filenames');
    verify($service->getRecord($new)['request_time'] === $old, 'time independent of ID');
    $ids = $service->listIds(); sort($ids, SORT_STRING);
    $expected = [$old, $new]; sort($expected, SORT_STRING);
    verify($ids === $expected, 'both IDs listed');
    verify($service->listRecords()[0]['id'] === $new, 'deterministic newest first');
    foreach ([$old, $new] as $id) {
        $_GET = ['id' => $id];
        verify(\App\Controller\XssRecordController::handle('get')['id'] === $id, 'controller lookup ' . $id);
    }
    verify(count($service->searchRecords('中文')) === 2 && $service->getStats()['total'] === 2, 'mixed search stats');
    verify(count(json_decode($service->exportJson(), true)) === 2 && strpos($service->exportCsv(), $new) !== false, 'mixed JSON and CSV IDs');
    verify(hash_file('sha256', DATA_PATH . '/' . $old . '.php') === $hash, 'legacy file not rewritten');
    verify(!$storage->create($record + ['extra' => 'collision'], $old), 'exclusive create refuses existing ID');
    verify(hash_file('sha256', DATA_PATH . '/' . $old . '.php') === $hash, 'collision preserves existing data');
    verify($storage->save($record + ['extra' => 'updated'], $old) && $storage->load($old)['extra'] === 'updated', 'atomic replacement of existing record');
    verify(!$storage->create(['bad' => "\xFF"], '1700000002'), 'encoding failure does not publish');
    verify(!file_exists(DATA_PATH . '/1700000002.php') && glob(DATA_PATH . '/.record-*') === [], 'no partial records or temporary residue');
    foreach (['../escape', $new . "\n", '1700000000-' . str_repeat('g', 32)] as $invalid) {
        verify($service->getRecord($invalid) === null && !$service->deleteRecord($invalid), 'invalid ID rejected');
    }
    $_GET = ['id' => $new];
    verify(\App\Controller\XssRecordController::handle('del') && count($service->listRecords()) === 1, 'delete only selected ID');
    verify($service->clearRecords() && $service->listIds() === [], 'clear mixed formats');
} catch (\Throwable $e) {
    verify(false, get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach (glob($sandbox . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($sandbox);
}
exit($failed ? 1 : 0);
