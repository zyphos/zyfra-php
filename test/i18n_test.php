<?php
include_once '../i18n.php';
include_once '../debug.php';
$a = html_entity_decode('H&eacute;t&eacute;rog&egrave;ne');
zyfra_debug::printr('Before:');
zyfra_debug::printr('$a =');
zyfra_debug::printr($a);
zyfra_debug::printr('======');
zyfra_debug::printr('After:');
zyfra_debug::printr(zyfra_i18n::remove_accent($a));
echo '<hr>';
$a = [
        'ha'=>html_entity_decode('H&eacute;t&eacute;rog&egrave;ne'),
        [html_entity_decode('T&eacute;l&eacute;'),
         html_entity_decode('&acirc;&auml;&aacute;&agrave;&ecirc;&euml;&eacute;&egrave;&icirc;&iuml;&iacute;&igrave;&Acirc;&Auml;&Aacute;&Agrave;')
        ]
    ];
zyfra_debug::printr('Before:');
zyfra_debug::printr('$a =');
zyfra_debug::printr($a);
zyfra_debug::printr('======');
zyfra_debug::printr('After:');
$b = zyfra_i18n::remove_accent($a);
zyfra_debug::printr('$a =');
zyfra_debug::printr($a);
zyfra_debug::printr('$b =');
zyfra_debug::printr($b);
