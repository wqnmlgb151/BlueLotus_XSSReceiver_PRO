param([Parameter(Mandatory=$true)][string]$FixtureRoot)
$ErrorActionPreference = 'Stop'
if (Test-Path -LiteralPath $FixtureRoot) { throw 'Fixture directory must not already exist' }
New-Item -ItemType Directory -Path $FixtureRoot | Out-Null
$repoRoot = Split-Path $PSScriptRoot -Parent
foreach ($directory in @('app','static')) { Copy-Item -LiteralPath (Join-Path $repoRoot $directory) -Destination $FixtureRoot -Recurse }
foreach ($file in @('admin.php','index.php','api.php','auth.php','login.php','logout.php','load.php','dio.php','waf.php','rc4.php','aes.php','keepsession.php','qqwry.dat')) {
    Copy-Item -LiteralPath (Join-Path $repoRoot $file) -Destination $FixtureRoot
}
foreach ($directory in @('data','template','myjs','sessions')) { New-Item -ItemType Directory -Path (Join-Path $FixtureRoot $directory) | Out-Null }
@'
<?php
require_once __DIR__ . '/app/autoload.php';
define('PASS', \App\Helper\PasswordHasher::hash('fixture-pass'));
define('DATA_PATH', __DIR__ . '/data');
define('IPDATA_PATH', __DIR__ . '/qqwry.dat');
define('JS_TEMPLATE_PATH', __DIR__ . '/template');
define('MY_JS_PATH', __DIR__ . '/myjs');
define('ENCRYPT_ENABLE', true);
define('ENCRYPT_TYPE', 'AES');
define('ENCRYPT_PASS', 'synthetic-test-key');
define('UNIQUE_RECORD_ID_ENABLE', true);
define('ADMIN_IP_CHECK_ENABLE', true);
define('XFF_ENABLE', false);
define('MAIL_ENABLE', false);
define('KEEP_SESSION', true);
define('KEEP_SESSION_ALLOWED_HOSTS', ['127.0.0.1:8792']);
define('PROJECTS', ['default'=>['name'=>'默认项目','token'=>null], 'other'=>['name'=>'其他项目','token'=>'fixture-token']]);
'@ | Set-Content -LiteralPath (Join-Path $FixtureRoot 'config.php') -Encoding utf8NoBOM
@'
<?php
file_put_contents(__DIR__ . '/target.log', json_encode(['path'=>$_SERVER['REQUEST_URI'],'cookie'=>$_SERVER['HTTP_COOKIE']??'']) . "\n", FILE_APPEND);
if ($_SERVER['REQUEST_URI'] === '/dir/start') { header('Location: next', true, 302); }
elseif ($_SERVER['REQUEST_URI'] === '/dir/next') { header('Location: //127.0.0.1:8793/blocked', true, 302); }
else { echo 'fixture target'; }
'@ | Set-Content -LiteralPath (Join-Path $FixtureRoot 'target.php') -Encoding utf8NoBOM
Write-Output $FixtureRoot
