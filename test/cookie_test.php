<?php
    include_once '../cookie.php';
    include_once '../debug.php';
    
    $my_cookie = new zyfra_cookie('ZyfraTest');
    
    //Make a copy of old data stored in the cookie
    if(isset($my_cookie->my_var)) $my_var = $my_cookie->my_var;
    if(isset($my_cookie->my_obj)) $my_obj = $my_cookie->my_obj;
    
    $my_cookie->my_var = '12346';
        
    class my_class{
        var $u = 'hum';
        var $tr = array('4',3,4);
    }
    
    $my_cookie->my_obj = new my_class;
    
    //You must store cookie before outputting any data.
    $my_cookie->store(10); //Store the cookie for 10 sec
    
    //Finally show the old datas that were stored in a cookie.
    print '<h2>Old data</h2>';
    if(isset($my_var)){
        print 'my_var='.$my_var.'<br>';
    }
    if(isset($my_obj)){
        print 'my_obj=<br>';
        zyfra_debug::printr($my_obj);
    }

    print '<h2>New data</h2>';
    print 'my_var='.$my_cookie->my_var.'<br>';
    print 'my_obj=<br>';
    zyfra_debug::printr($my_cookie->my_obj);
?>