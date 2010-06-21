<?php
include_once '../validator.php';
include_once '../debug.php';
$validator = new zyfra_validator;

$checks = array();
$checks[] = array('int', '546');
$checks[] = array('int', '5d46');
$checks[] = array('float', '5.46');
$checks[] = array('float', '5.46.');
$checks[] = array('float', '546');
$checks[] = array('float', '54a,6');
$checks[] = array('string', 'This is a test');
$checks[] = array('string', 'This is a test viagra');
$checks[] = array('string', 'yJvjBPby JpFsGoYXsuMx');
$checks[] = array('email', 'hi@hello.com');
$checks[] = array('email', 'h-ihe_llo.com');
$checks[] = array('email_net', 'hi@erfdgerthgfd.com');
$checks[] = array('email_net', 'hi@yahoo.com');
$checks[] = array('email_net', 'h-ihe_llo.com');
$checks[] = array('vat', 'FR19501271332');
$checks[] = array('vat', 'BE0454016220');
$checks[] = array('vat', 'BE0477143395');
//$checks[] = array('vat_net', 'FR19501271332');
//$checks[] = array('vat_net', 'BE0454016220');
//$checks[] = array('vat_net', 'BE0477143395');

foreach($checks as $check){
    list($type, $data) = $check;
    $result = $validator->is_valid($data, $type, true)?'true':'false';
    zyfra_debug::printr('"'.$data.'" is valid '.$type.' : '.$result);
}
?>
