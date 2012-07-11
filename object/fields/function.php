<?php
require_once 'relational.php';

class FunctionField extends Field{
    // FunctionField('Label', 'my_fx');
    // FunctionField('Label', array($my_obj, 'my_fx'));
    var $get_fx=null;
    var $set_fx=null;
    
    function __construct($label, $fx, $args = null){
        parent::__construct($label, $args);
        $this->get_fx = $fx;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        $sql_query->add_sub_query($this->object, $this->name, '!function!', $field_alias, '');
        return $parent_alias->alias.'.'.$this->object->_key;
    }
    
    function get($ids, $context){
        //should return an array of object with $o->_subid = id 
        if (is_null($this->get_fx)) return array();
        return call_user_func($this->get_fx, $ids, $context);
    }
    
    function set($ids, $value, $context){
        if (is_null($this->set_fx)) return array();
        return call_user_func($this->set_fx, $ids, $value, $context);
    }
}