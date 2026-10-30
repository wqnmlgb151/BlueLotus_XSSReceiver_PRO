<?php
// Run the real receiver in child processes with temporary config and storage.
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
$root = dirname(__DIR__);
$sandbox = sys_get_temp_dir() . '/bluelotus-receiver-' . bin2hex(random_bytes(8));
mkdir($sandbox);
mkdir($sandbox . '/app');
mkdir($sandbox . '/data');
file_put_contents($sandbox . '/config.php', '<?php ' .
    "define('DATA_PATH', __DIR__ . '/data'); define('IPDATA_PATH', __DIR__ . '/missing.dat'); " .
    "define('ENCRYPT_ENABLE', false); define('XFF_ENABLE', false); define('MAIL_ENABLE', false);");
file_put_contents($sandbox . '/app/autoload.php', '<?php require ' . var_export($root . '/app/autoload.php', true) . ';');
$failed = false;
try {
    foreach (['nested' => 200, 'deep' => 400, 'project_array' => 400, 'write_failure' => 500, 'keep_arrays' => 200] as $scenario => $status) {
        $setup = '$_GET = []; $_POST = []; $_COOKIE = []; $_SERVER["REMOTE_ADDR"] = "127.0.0.1";';
        if ($scenario === 'nested') { $setup .= '$_GET = ["n" => ["q" => "<b>中文"]];'; }
        if ($scenario === 'deep') { $setup .= '$v = "x"; for ($i=0;$i<34;$i++) { $v=["n"=>$v]; } $_GET=$v;'; }
        if ($scenario === 'project_array') { $setup .= '$_GET=["proj"=>["bad"]];'; }
        if ($scenario === 'write_failure') { rmdir($sandbox . '/data'); }
        if ($scenario === 'keep_arrays') { $setup .= "define('KEEP_SESSION', false);"; }
        $harness = '<?php chdir(__DIR__); http_response_code(200); ' . $setup .
            'register_shutdown_function(function() { echo "STATUS=" . http_response_code(); }); require ' .
            var_export($root . ($scenario === 'keep_arrays' ? '/keepsession.php' : '/index.php'), true) . ';';
        if ($scenario === 'keep_arrays') {
            $harness .= 'if (getCookie(["get_data"=>["cookie"=>["x"]]]) !== "" || getLocation(["post_data"=>["location"=>["x"]]]) !== "") { http_response_code(500); }';
        }
        file_put_contents($sandbox . '/entry.php', $harness);
        $process = proc_open([PHP_BINARY, $sandbox . '/entry.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start receiver test'); }
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exit = proc_close($process);
        $ok = $exit === 0 && $output === 'STATUS=' . $status;
        if ($scenario === 'nested') {
            $files = glob($sandbox . '/data/*.php');
            $record = count($files) === 1 ? json_decode(base64_decode(substr(file_get_contents($files[0]), 15)), true) : [];
            $ok = $ok && ($record['get_data']['n']['q'] ?? '') === '&lt;b&gt;中文';
            foreach ($files as $file) { unlink($file); }
        }
        $ok = $ok && strpos($errors, '<b>中文') === false && strpos($errors, $sandbox) === false;
        echo ($ok ? 'PASS ' : 'FAIL ') . $scenario . ' receiver status ' . $status . PHP_EOL;
        $failed = $failed || !$ok;
    }
} finally {
    foreach (['entry.php', 'config.php', 'app/autoload.php'] as $file) { unlink($sandbox . '/' . $file); }
    rmdir($sandbox . '/app');
    if (is_dir($sandbox . '/data')) { rmdir($sandbox . '/data'); }
    rmdir($sandbox);
}
exit($failed ? 1 : 0);
