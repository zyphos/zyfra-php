<?php

abstract class Field{
    var $name;
    var $unique=false;
    var $primary_key=false;
    var $key=false;
    var $stored=true;
    var $relational = false;
    var $needed_columns;
    var $default_value=null;
    var $widget='text';
    var $required=false;
    var $read_only=false;

    function __construct($label, $args = null){
        $this->label = $label;
        if(is_array($args)){
            foreach($args as $key=>$value){
                if(property_exists($this, $key)) $this->{$key} = $value;
            }
        }
        $this->needed_columns = array();
    }

    function sql_create($sql_create, $value, $fields, $context){
        return $this->sql_format($value);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if ($this->read_only) return;
        $sql_write->add_assign($this->name.'='.$this->sql_format($value));
    }

    function sql_format($value){
        return "'".$value."'"; //!! injection !!! mysql_real_escape_string
    }

    function set_instance($object, $name){
        if (is_null($this->label) || $this->label == '') $this->label = $name;
        $this->name = $name;
        $this->object = $object;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if(array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        if ($this->name == $field_alias) $sql_query->no_alias($field_alias);
        $parent_alias->set_used();
        return $parent_alias->alias.'.'.$this->name;
    }

    function get_sql_def(){
        return '';
    }

    function get_sql_def_flags(){
        return ($this->primary_key?' PRIMARY KEY':'');
    }

    function get_sql_extra(){
        return '';
    }

    //abstract function after_create_trigger(&$values);
    //abstract function after_write_trigger(&$values);
    //abstract function delete_after_trigger(&$values);
}

require_once('fields/texts.php');
require_once('fields/numerics.php');
require_once('fields/time.php');
require_once('fields/many2one.php');
require_once('fields/one2many.php');
require_once('fields/many2many.php');
require_once('fields/meta.php');
require_once('fields/function.php');
?>