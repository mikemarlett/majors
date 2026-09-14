<?php

/** Identity mapping, CSRF and request parsing — the pure parts of the auth layer. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Auth\CasProvider;
use Majors\Auth\Csrf;
use Majors\Support\Request;

echo "[cas attributes]\n";
$id = CasProvider::identityFromAttributes([
    'commonName' => 'Mike Marlett', 'mail' => 'Mike.Marlett@wichita.edu', 'sAMAccountName' => 'Q262T958',
    'givenName' => 'Mike', 'sn' => 'Marlett', 'UDC_IDENTIFIER' => 'q262t958',
]);
check($id->netid === 'q262t958' && $id->email === 'mike.marlett@wichita.edu', 'netid and email lower-cased');
check($id->displayName() === 'Mike Marlett', 'display name from given + sn');

$id2 = CasProvider::identityFromAttributes([], 'a123b456');
check($id2->netid === 'a123b456' && $id2->email === '', 'principal used when the attribute bag is empty');

$id3 = CasProvider::identityFromAttributes(['mail' => ['x@wichita.edu']], 'x@wichita.edu');
check($id3->netid === null && $id3->email === 'x@wichita.edu', 'array-valued attribute and non-netid principal handled');

try {
    CasProvider::identityFromAttributes([], '');
    check(false, 'empty identity throws');
} catch (RuntimeException $e) {
    check(true, 'empty identity throws');
}

echo "[csrf]\n";
@session_start();
$csrf = new Csrf();
$tok  = $csrf->token();
check(strlen($tok) === 64 && $csrf->token() === $tok, 'token stable within a session');
check($csrf->valid($tok) && !$csrf->valid('nope') && !$csrf->valid(null), 'validation');
$csrf->rotate();
check($csrf->token() !== $tok, 'rotate issues a new token');

echo "[request]\n";
$r = new Request(['degree_map_id' => '922', 'order' => 'bogus', 'x' => ' hi '], ['degree_map_id' => 'abc', 'list' => ['1', '2']], ['REQUEST_METHOD' => 'POST', 'HTTP_X_CSRF_TOKEN' => 't']);
check($r->id('degree_map_id') === null, 'POST value wins over GET and non-numeric id is null');
check($r->enum('order', ['alpha', 'college'], 'alpha') === 'alpha', 'enum falls back');
check($r->str('x') === 'hi', 'str trims');
check($r->arr('list') === ['1', '2'] && $r->arr('missing') === [], 'arr');
check($r->header('X-CSRF-Token') === 't' && $r->isPost(), 'header + method');

finish();
