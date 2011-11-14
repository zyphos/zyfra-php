<?php
//ob_start();

//flush();
print '.';
ob_flush();
flush();
    include_once '../debug.php';
    $a = array(1=>'a','c'=>4);
    //zyfra_debug::printr($a);
    //print 'j';
    //zyfra_debug::flush();
    usleep(100000);
    zyfra_debug::_print('f');
    //print 'g';
    
    //zyfra_debug::_print('g');
    for($i=0;$i<7;$i++)
    {
        //usleep(300000);
        sleep(1);
        zyfra_debug::_print($i.' ');
    }
?>