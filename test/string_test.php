<?php
include_once '../string.php';
include_once '../debug.php';
$a = ['  Center  ', 'left   ', '   right', ['   center    ', 'left    ', '    right']];
zyfra_debug::printr('Before:');
zyfra_debug::printr('$a =');
zyfra_debug::printr($a);
zyfra_debug::printr('==========');
zyfra_debug::printr('After:');
zyfra_debug::printr('$b = zyfra_string::trim($a);');
$b = zyfra_string::trim($a);
zyfra_debug::printr('$a = ');
zyfra_debug::printr($a);
zyfra_debug::printr('$b = ');
zyfra_debug::printr($b);
class C{
    var $c1 = '  Center  ';
    var $c2 = 'left    ';
    var $c3 = '    right';
}
$c = new C;
zyfra_debug::printr('Before:');
zyfra_debug::printr('$c =');
zyfra_debug::printr($c);
zyfra_debug::printr('==========');
zyfra_debug::printr('After:');
zyfra_debug::printr('$d = zyfra_string::trim($c);');
$d = zyfra_string::trim($c);
zyfra_debug::printr('$c = ');
zyfra_debug::printr($c);
zyfra_debug::printr('$d = ');
zyfra_debug::printr($d);
