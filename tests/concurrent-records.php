<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
$worker = ($argv[1] ?? '') === 'worker';
$sandbox = $worker ? $argv[2] : sys_get_temp_dir() . '/bluelotus-concurrent-' . bin2hex(random_bytes(8));
if (!$worker) { mkdir($sandbox); }
define('IN_XSS_PLATFORM', true);
define('DATA_PATH', $sandbox);
define('IPDATA_PATH', dirname(__DIR__) . '/qqwry.dat');
define('ENCRYPT_ENABLE', true);
define('ENCRYPT_TYPE', 'RC4');
define('ENCRYPT_PASS', 'synthetic-test-key');
define('UNIQUE_RECORD_ID_ENABLE', true);
require dirname(__DIR__) . '/app/autoload.php';
if ($worker) {
    $deadline = microtime(true) + 10;
    while (!file_exists($sandbox . '/start')) {
        if (microtime(true) > $deadline) { exit(2); }
        usleep(1000);
    }
    $service = new \App\Service\XssRecordService();
    for ($i = 0; $i < 10; $i++) {
        if (!$service->saveRecord(['request_time' => '1700000000', 'user_IP' => '127.0.0.1', 'keepsession' => true,
            'get_data' => ['marker' => $argv[3] . '-' . $i]], ((int) $argv[3] % 2) ? 'other' : 'default')) { exit(1); }
    }
    exit(0);
}
$failed = false; $children = [];
try {
    for ($i = 0; $i < 10; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $sandbox, (string) $i], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start worker'); }
        $children[] = [$process, $pipes];
    }
    touch($sandbox . '/start');
    foreach ($children as [$process, $pipes]) {
        foreach ($pipes as $pipe) { stream_get_contents($pipe); fclose($pipe); }
        if (proc_close($process) !== 0) { throw new RuntimeException('Worker failed'); }
    }
    $service = new \App\Service\XssRecordService();
    $ids = $service->listIds(); $markers = [];
    if (count($ids) !== 100 || count(glob($sandbox . '/*.php')) !== 100) { throw new RuntimeException('Expected 100 published records'); }
    foreach ($ids as $id) {
        $_GET = ['id' => $id];
        $record = \App\Controller\XssRecordController::handle('get');
        if (!$record || $record['id'] !== $id || $record['request_time'] !== '1700000000' || !$record['keepsession']) {
            throw new RuntimeException('Record lookup or timestamp failed');
        }
        $markers[] = $record['get_data']['marker'];
    }
    if (count(array_unique($markers)) !== 100 || $service->getStats()['total'] !== 50 || $service->getStats('other')['total'] !== 50) {
        throw new RuntimeException('Missing markers or wrong project statistics');
    }
    if (glob($sandbox . '/.record-*') !== []) { throw new RuntimeException('Temporary residue'); }
    if (!$service->deleteRecord($ids[0]) || count($service->listRecords()) !== 99) { throw new RuntimeException('Delete isolation failed'); }
    echo "PASS 10 concurrent RC4 workers: 100 same-second records, 100 markers, controller reads, projects 50/50, delete leaves 99\n";
} catch (\Throwable $e) {
    echo 'FAIL ' . $e->getMessage() . PHP_EOL; $failed = true;
} finally {
    // Only files in this invocation's newly created temporary directory.
    foreach (glob($sandbox . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    foreach (glob($sandbox . '/.record-*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($sandbox);
}
exit($failed ? 1 : 0);
