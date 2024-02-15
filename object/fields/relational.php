<?php
abstract class RelationalField extends Field{
    var $relation_object_name;
    var $relation_object=null;
    var $relation_object_key;
    var $relational=true;
    var $select_all=false;
    
    function __construct($label, $relation_object_name, $args = array()){
        $this->relation_object_key='';
        $this->local_key='';
        parent::__construct($label, $args);
        $this->relation_object_name = $relation_object_name;
    }
    
    function get_relation_object(){
        if ($this->relation_object===null){
            try{
                $this->relation_object = $this->object->_pool->__get($this->relation_object_name);
            } catch(PoolObjectException $e){
                throw new Exception('Could not find object ['.$this->relation_object_name.'] field many2one ['.$this->name.'] from object ['.$this->object->_name.']');
            }
        }
        return $this->relation_object;
    }
} 
