<?php

abstract class Field{
    public $name=null;
    public $object=null;
    public $unique=false;
    public $primary_key=false;
    public $index=false;
    public $key=false;
    public $stored=true;
    public $relational = false;
    public $needed_columns;
    public $default_value=null;
    public $widget='text';
    public $required=false;
    public $read_only=false;
    public $instanciated=false;
    public $sql_escape_fx=null;
    public $help='';
    public $handle_operator=false;
    public $not_null=false;
    public $select_all=true;
    public $model_class=null;
    public $hidden=false;
    public $label;

    function __construct($label, $args = null){
        $this->label = $label;
        if(is_array($args)){
            foreach($args as $key=>$value){
                if(property_exists($this, $key)){
                    $this->{$key} = $value;
                }else{
                    throw new UnexpectedValueException('Field do not have attribute ['.$key.'].');
                }
            }
        }
        $this->needed_columns = [];
        if ($this->not_null && is_null($this->default_value))
            throw new UnexpectedValueException('Field do not accept null values, but default value is null.');
    }

    public function is_stored(&$context){
        return $this->stored;
    }

    function sql_create($sql_create, $value, $fields, $context){
        return $this->sql_format($value);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if ($this->read_only) return;
        $sql_write->add_assign($this->name.'='.$this->sql_format($value));
    }

    function sql2php($value){
        return $value;
    }

    function _sql_format_null(){
        if ($this->not_null)
            throw new UnexpectedValueException('Null value not accepted for this field ['.$this->object->_name.'.'.$this->name.']');
        return 'null';
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        if (is_null($this->sql_escape_fx)){
            return "'".str_replace("'", "\'", $value)."'"; // Warning sql injection !!!
        }
        if (is_null($this->sql_escape_fx)){ // TODO: check why this is not used
            $this->sql_escape_fx = [$this->object->_pool->db, 'safe_var'];
        }
        return call_user_func($this->sql_escape_fx, $value);
    }

    function set_instance($object, $name){
        if ($this->instanciated) return;
        $this->instanciated=true;
        if (is_null($this->label) || $this->label == '') $this->label = $name;
        $this->name = $name;
        $this->object = $object;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=[]){
        if(array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        if ($this->name == $field_alias) $sql_query->no_alias($field_alias);
        $parent_alias->set_used();
        return $this->add_operator($parent_alias->alias.'.'.$this->name, $context);
    }

    protected function add_operator($field_sql, &$context){
        if (isset($context['operator'])){
            $operator = $context['operator'];
            if (in_array($operator, ['in','is'])) $operator = ' '.$operator.' ';
            $op_data = trim($context['op_data']);
            return $field_sql.$operator.$op_data;
        }
        return $field_sql;
    }

    function get_sql_def(){
        return '';
    }

    function get_sql_def_flags($update=false){
        if ($this->primary_key) return ' PRIMARY KEY';
        $sql_def = $this->not_null?' NOT NULL':' NULL';
        if ($this->not_null || $this->default_value != ''){
            $sql_def .= ' DEFAULT '.$this->sql_format($this->default_value);
        }
        return $sql_def;
    }

    function get_sql_extra(){
        return '';
    }

    function get($ids, $context, $datas, $param){
        return [];
    }

    function set($ids, $value, $context){
    }

    function get_default(){
        return $this->default_value;
    }

    function get_model_class(){
        if (is_null($this->model_class)){
            return $this->object->_pool->_model_class;
        }
        return $this->model_class;
    }

    //abstract function after_create_trigger(&$values);
    //abstract function after_write_trigger(&$values);
    //abstract function delete_after_trigger(&$values);
}

require_once 'fields/texts.php';
require_once 'fields/numerics.php';
require_once 'fields/time.php';
require_once 'fields/many2one.php';
require_once 'fields/one2many.php';
require_once 'fields/many2many.php';
require_once 'fields/meta.php';
require_once 'fields/function.php';
require_once 'fields/shortcut.php';
require_once 'fields/blob.php';
