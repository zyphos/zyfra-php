<?php
include_once '../string.php';
include_once '../debug.php';
$a = array('  Center  ', 'left   ', '   right', array('   center    ', 'left    ', '    right'));
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
?>