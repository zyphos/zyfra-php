<?php
require_once 'relational.php';

class FunctionField extends Field{
    // FunctionField('Label', array('get_values_fx', array($this, 'my_fx')));
    var $get_values_fx=null;

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        $sql_query->add_sub_query($this->object, $this->name, '!function!', $field_alias, '');
        return $parent_alias->alias.'.'.$this->object->_key;
    }
    
    function get_values($ids, $context){
        //should return an array of object with $o->_subid = id 
        if (is_null($this->get_values_fx)) return [];
        return call_user_func($this->get_values_fx, $ids, $context);
    }
}