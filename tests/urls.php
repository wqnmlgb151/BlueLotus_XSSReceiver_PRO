<?php
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/autoload.php';
use App\Helper\UrlValidator as U;
foreach ([
    ['http://localhost:8123/a', 'localhost:8123', [], true],
    ['http://localhost:8124/a', 'localhost:8123', [], false],
    ['https://localhost:8123/a', 'localhost:8123', [], false],
    ['http://other:8124/a', 'localhost:8123', ['other:8124'], true],
    ['http://other:8124/a', 'localhost:8123', ['other'], false],
    ['http://other/a', 'localhost:8123', ['other'], true],
    ['file:///etc/passwd', 'localhost', [], false],
    ['http://user@localhost/a', 'localhost', [], false],
    ["http://localhost/\r\nx", 'localhost', [], false],
] as [$url, $host, $allowed, $expected]) {
    if (U::isAllowed($url, $host, $allowed) !== $expected) { throw new RuntimeException('URL policy failed'); }
}
foreach ([
    ['next', 'http://localhost:8123/dir/next'],
    ['../next', 'http://localhost:8123/next'],
    ['.', 'http://localhost:8123/dir/'],
    ['..', 'http://localhost:8123/'],
    ['../../next', 'http://localhost:8123/next'],
    ['/next', 'http://localhost:8123/next'],
    ['?q=2', 'http://localhost:8123/dir/start?q=2'],
    ['//other:8124/x', 'http://other:8124/x'],
] as [$reference, $expected]) {
    if (U::resolve('http://localhost:8123/dir/start', $reference) !== $expected) { throw new RuntimeException('Redirect resolution failed'); }
}
echo "PASS URL scheme/host/port/credentials/CRLF and relative redirects\n";
