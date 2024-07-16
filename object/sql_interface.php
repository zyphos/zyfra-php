<?php
namespace zyfra\orm; 

class Callback{
    public $function_name;
    public $return_value;

    public function __construct($function_name, $return_value){
        $this->function_name = $function_name;
        $this->return_value = $return_value;
    }
}

class OM_SQLinterface{
    var $callbacks;
    var $object;
    var $debug;

    function __construct($object, $context){
        $this->object = $object;
        $this->callbacks = [];
        $this->context = $context;
        $this->debug = array_get($context, 'debug', false);
        $this->dry_run = array_get($context, 'dry_run', false);
    }

    function add_callback($field_object, $callback_name, $parameters=[]){
        $this->callbacks[] = [[$field_object, $callback_name], $parameters];
    }
}
