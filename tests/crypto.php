<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
define('IN_XSS_PLATFORM', true);
define('ENCRYPT_ENABLE', true);
define('ENCRYPT_TYPE', 'AES');
define('ENCRYPT_PASS', 'synthetic-test-key');
require dirname(__DIR__) . '/app/autoload.php';
set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
// Produced by the untouched legacy AES implementation loaded in global scope.
$fixture = 'Tf3jatHR0dFcc6a5ha7WMJeCsRaV8EMAnQsO03NGt20b';
if (\App\Helper\Crypto::decrypt($fixture) !== 'legacy AES fixture 中文') { throw new RuntimeException('Legacy AES fixture failed'); }
foreach (['', '中文', str_repeat('a', 100), "binary\x00\xFF"] as $plain) {
    if (\App\Helper\Crypto::decrypt(\App\Helper\Crypto::encrypt($plain)) !== $plain) { throw new RuntimeException('Roundtrip failed'); }
}
foreach (['!', '', base64_encode('short')] as $bad) {
    if (\App\Helper\Crypto::decrypt($bad) !== '') { throw new RuntimeException('Malformed ciphertext accepted'); }
}
echo "PASS legacy AES fixture, repeated roundtrip, empty/binary/Unicode, malformed ciphertext\n";
