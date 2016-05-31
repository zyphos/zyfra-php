<?
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

    function __construct($object, $context){
        $this->object = $object;
        $this->callbacks = array();
        $this->context = $context;
    }

    function add_callback($field_object, $callback_name, $parameters=array()){
        $this->callbacks[] = array(array($field_object, $callback_name), $parameters);
    }
}
