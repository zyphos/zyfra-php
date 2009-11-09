<?php
    include_once '../cookie.php';
    include_once '../debug.php';
    
    
    $my_cookie = new zyfra_cookie('ZyfraTest');
    
    if(isset($my_cookie->my_var)) $my_var = $my_cookie->my_var;
    if(isset($my_cookie->my_obj)) $my_obj = $my_cookie->my_obj;
    
    $my_cookie->my_var = '12346';
        
    class my_class{
        var $u = 'hum';
        var $tr = array('4',3,4);
    }
    
    $my_cookie->my_obj = new my_class;
    
    $my_cookie->store(10);
    
    
    if(isset($my_var)){
        print 'my_var='.$my_var.'<br>';
    }
    if(isset($my_obj)){
        print 'my_obj=<br>';
        zyfra_debug::printr($my_obj);
    }
?>