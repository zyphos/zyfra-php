<?
class OM_SQLinterface{
    var $callbacks;
    var $object;

    function __construct($object, $context){
        $this->object = $object;
        $this->callbacks = array();
        $this->context = $context;
    }

    function add_callback($field_object, $callback_name){
        $this->callbacks[] = array($field_object, $callback_name);
    }
}
?>