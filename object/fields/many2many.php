<?php
require_once 'relational.php';

class Many2ManyField extends One2ManyField{
    var $widget='many2many';
    var $relation_object_field;
    var $stored=false;
    var $back_ref_field=null; // If set, name of the back reference (O2M) to this field in the relational object
    var $relation_table;
    
    function __construct($label, $relation_object_name, $relation_object_field, $args = array()){
        $this->relation_table = '';
        parent::__construct($label, $relation_object_name, $args);
        $this->left_right = false;
    }
    
    function set_instance($object, $name){
        parent::set_instance($object, $name);
        $robj = $this->get_relation_object();
        if ($this->back_ref_field !== null){
            if(is_array($this->back_ref_field)){
                list($br_label,$br_field) = $this->back_ref_field;
            }else{
                $br_label = $br_field = $this->back_ref_field;
            }
        }
        if ($this->relation_table == ''){
            //Auto find relation table name
            if ($this->back_ref_field !== null){
                $this->relation_table = 'm2m_'.$object->_name.'_'.$name.'_'.$robj->_name.'_'.$this->back_ref_field;
            }else{
                if ($object->_name <= $robj->_name){
                    $this->relation_table = 'm2m_'.$object->_name.'_'.$robj->_name;
                }else{
                    $this->relation_table = 'm2m_'.$robj->_name.'_'.$object->_name;
                }
            }
        }
        if ($this->back_ref_field !== null){
            $this->get_relation_object()->add_column($br_field, new Many2ManyField($br_label, $object->_name, $name, array('relation_table'=>$this->relation_table)));
        }
        if ($this->relation_object_key == '') $this->relation_object_key = $robj->_key;
        $pool = $object->_pool;
        if (!$pool->object_in_pool($this->relation_table)){
            $rel_table_object = new ObjectModel($pool, array(
                    '_name'=>$this->relation_table,
                    '_columns'=>array($object->_name.'_id'=>new Many2OneField(null, $this->object->_name),
                            $robj->_name.'_id'=>new Many2OneField(null, $this->object->_name)
                    )));
            $pool->add_object($this->relation_table, $rel_table_object);
        }
    }
    
    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        $new_fields = $fields; //copy
        array_unshift($new_fields, $robj->_name.'_id');
        return parent::get_sql($parent_alias, $new_fields, $sql_query, $context=array());
    }
}
?>