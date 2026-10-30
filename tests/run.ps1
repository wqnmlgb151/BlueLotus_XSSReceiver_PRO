param([string]$Php = 'php')
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    & $Php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);'
    if ($LASTEXITCODE -ne 0) { throw 'PHP 8.x is required; pass -Php with its executable path' }
    foreach ($test in @('regression','receiver','crypto','urls','concurrent-records')) {
        & $Php ('tests/' + $test + '.php')
        if ($LASTEXITCODE -ne 0) { throw ($test + ' failed') }
    }
    foreach ($mode in @('off','RC4','AES')) {
        & $Php tests/record-ids.php $mode
        if ($LASTEXITCODE -ne 0) { throw ('record-ids ' + $mode + ' failed') }
    }
    node tests/panel.js
    if ($LASTEXITCODE -ne 0) { throw 'Panel tests failed' }
} finally { Pop-Location }
