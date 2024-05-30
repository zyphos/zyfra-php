<?php
include_once '../language.php';
include_once '../debug.php';

class my_language extends zyfra_language{
    protected function get_cookie(){
        return '';
    }
}

$language = new my_language();
zyfra_debug::printr($language->auto_detect());
