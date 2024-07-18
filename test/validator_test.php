<?php
include_once '../validator.php';
include_once '../debug.php';
$validator = new zyfra_validator;

$checks = [];
$checks[] = ['int', '546'];
$checks[] = ['int', '5d46'];
$checks[] = ['float', '5.46'];
$checks[] = ['float', '5.46.'];
$checks[] = ['float', '546'];
$checks[] = ['float', '54a,6'];
$checks[] = ['string', 'This is a test'];
$checks[] = ['string', 'This is a test viagra'];
$checks[] = ['string', 'yJvjBPby JpFsGoYXsuMx'];
$checks[] = ['email', 'hi@hello.com'];
$checks[] = ['email', 'h-ihe_llo.com'];
$checks[] = ['email_net', 'hi@erfdgerthgfd.com'];
$checks[] = ['email_net', 'hi@yahoo.com'];
$checks[] = ['email_net', 'h-ihe_llo.com'];
$checks[] = ['url', 'http://www.google.be:81/#hl=en&q=lavcopts+%2B264&aq=f&aqi=&aql=&oq=&gs_rfai=&fp=d75900b3b07570fc'];
$checks[] = ['url', 'http://This is cool'];
$checks[] = ['vat', 'FR19501271332'];
$checks[] = ['vat', 'BE0454016220'];
$checks[] = ['vat', 'BE0477143395'];
//$checks[] = ['vat_net', 'FR19501271332'];
//$checks[] = ['vat_net', 'BE0454016220'];
//$checks[] = ['vat_net', 'BE0477143395'];

foreach($checks as $check){
    list($type, $data) = $check;
    $result = $validator->is_valid($data, $type, true)?'true':'false';
    zyfra_debug::printr('"'.$data.'" is valid '.$type.' : '.$result);
}
