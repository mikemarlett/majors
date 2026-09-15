<?php
// Sandbox mirror helper: download named files from the Modern Campus staging site (www) into one or more local dirs.
// Needs the Modern Campus workspace (McClient + credentials); see README "Server mirror".
// Usage: php bin/mirror-fetch.php <remote_dir> <dest_dir>[,<dest_dir>...] name1 name2 ...
declare(strict_types=1);
$mcRoot = '/srv/work/Modern Campus';
require $mcRoot . '/mc-client/vendor/autoload.php';
require $mcRoot . '/McClient.php';
require '/data/www/config/modern_campus_config.php';

[$self, $remoteDir, $destsArg] = $argv; $names = array_slice($argv, 3);
$dests = explode(',', $destsArg);
$mc = McClient::authenticated();
$http = $mc->http();
// What is actually there (staging listing), so we can report precisely.
$list = json_decode((string) $http->get("https://{$mc->domain()}/files/list", ['query' => ['site' => 'www', 'path' => $remoteDir], 'headers' => ['X-Auth-Token' => $mc->token()]])->getBody(), true);
$present = [];
foreach (($list['entries'] ?? $list ?? []) as $e) { if (isset($e['file_name'])) $present[$e['file_name']] = $e; }
printf("%d entries in www:%s\n", count($present), $remoteDir);
foreach ($names as $n) {
    if (!isset($present[$n])) { echo "ABSENT   $n (not in staging listing)\n"; continue; }
    $path = rtrim($remoteDir, '/') . '/' . $n;
    try {
        $r = $http->get("https://{$mc->domain()}/pages/content", ['query' => ['site' => 'www', 'path' => $path], 'headers' => ['X-Auth-Token' => $mc->token()], 'http_errors' => false]);
    } catch (Throwable $t) { echo "ERROR    $n: " . $t->getMessage() . "\n"; continue; }
    $code = $r->getStatusCode(); $body = (string) $r->getBody(); $ct = $r->getHeaderLine('Content-Type');
    $looksRight = str_ends_with($n, '.svg') ? (bool) preg_match('/<svg[\s>]/i', substr($body, 0, 4000)) : (str_ends_with($n, '.png') ? str_starts_with($body, "\x89PNG") : $code === 200);
    if ($code !== 200 || !$looksRight) { echo "FAIL     $n: HTTP $code $ct " . strlen($body) . "b\n"; continue; }
    foreach ($dests as $d) { @mkdir($d, 0775, true); file_put_contents("$d/$n", $body); }
    printf("OK       %-40s %7db %s -> %s\n", $n, strlen($body), $ct, implode(', ', $dests));
}
