<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
define('IN_XSS_PLATFORM', true);
$sandbox = sys_get_temp_dir() . '/bluelotus-bench-' . bin2hex(random_bytes(8));
mkdir($sandbox);
define('DATA_PATH', $sandbox);
define('IPDATA_PATH', dirname(__DIR__) . '/qqwry.dat');
define('ENCRYPT_ENABLE', true); define('ENCRYPT_TYPE', 'RC4'); define('ENCRYPT_PASS', 'benchmark-synthetic');
require dirname(__DIR__) . '/app/autoload.php';
$results = ['php'=>PHP_VERSION, 'os'=>PHP_OS_FAMILY, 'encryption'=>'RC4', 'sample'=>'single warm run per operation, synthetic records; not p95', 'datasets'=>[]];
try {
    foreach ([1000, 10000] as $size) {
        for ($i = ($size === 1000 ? 0 : 1000); $i < $size; $i++) {
            $time = (string) (1700000000 + $i);
            $record = ['request_time'=>$time,'user_IP'=>'127.0.0.1','location'=>'fixture','project'=>'default','get_data'=>['q'=>'中文 benchmark '. $i]];
            $content = '<?php exit();?>' . \App\Helper\Crypto::encrypt(json_encode($record));
            if (file_put_contents($sandbox . '/' . $time . '.php', $content) !== strlen($content)) { throw new RuntimeException('Fixture write failed'); }
        }
        $service = new \App\Service\XssRecordService(); $row=['records'=>$size];
        foreach (['list'=>fn()=>$service->listRecords(), 'search'=>fn()=>$service->searchRecords('中文'), 'stats'=>fn()=>$service->getStats(), 'export'=>fn()=>$service->exportJson()] as $operation=>$call) {
            if (function_exists('memory_reset_peak_usage')) { memory_reset_peak_usage(); }
            $start=hrtime(true); $result=$call();
            $row[$operation]=['milliseconds'=>round((hrtime(true)-$start)/1e6,2),'peak_mib'=>round(memory_get_peak_usage(true)/1048576,2)];
            unset($result);
        }
        $results['datasets'][]=$row;
    }
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    foreach (glob($sandbox . '/*.php') as $file) { unlink($file); }
    rmdir($sandbox);
}
