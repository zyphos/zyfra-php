<?php
class ActiveRecord{
    protected $__object;
    protected $__context;
    protected $__data;
    protected $__params;
    protected $__modified_columns;

    public function __construct($object, $params = array(), $context = array()){
        $this->__object = $object;
        $this->__context = $context;
        $this->__data = null;
        $this->__params = $params;
        $this->__modified_columns = array();
    }
    
    private function __get_data(){
        $obj = $this->__object;
        $key = $obj->_key;
        if(!is_null($this->__data) || (!array_key_exists($key, $this->__params) && !array_key_exists('mql_where', $this->__params))) return;
        $id = array_key_exists($key, $this->__params)?$this->__params[$key]:0;
        $mql_fields = array_key_exists('mql_fields', $this->__context)?$this->__context['mql_fields']:'*';
        $mql_where = array_key_exists('mql_where', $this->__context)?$this->__context['mql_where']:$key.'=%s';
        $result = $obj->select($mql_fields.' WHERE '.$mql_where, $this->__context, array($id));
        if (count($result)) $this->__data = $result[0];
    }

    public function __get($name){
        if (array_key_exists($name, $this->__params)) return $this->__params[$name];
        $obj = $this->__object;
        if (array_key_exists($name, $obj->_columns)){
            $this->__get_data();
            if (is_null($this->__data)){
                return $obj->_columns[$name]->get_default();
            }else{
                return $this->__data->{$name};
            }
        }
        throw new Exception('Column '.$name.' not found in '.$obj->_name);
    }

    public function __set($name, $value){
        switch($name){
            case $obj->_key:
            case 'create_date':
            case 'write_date':
                throw new Exception('Column '.$name.' can\'t be modified');
        }
        if (array_key_exists($name, $this->__object->_columns)){
            if (!array_key_exists($name, $this->__params) || $this->__params[$name] != $value){
                $this->__modified_columns[$name] = true;
            }
            $this->__params[$name] = $value;
        }
        throw new Exception('Column '.$name.' not found in '.$this->__object->_name);
    }

    public function save(){
        $obj = $this->object;
        $key = $this->__object->_key;
        $values = array();
        foreach($this->__modified_columns as $col_name=>$t){
            $values[$col_name] = $this->__params[$col_name];
        }
        if (count($values) == 0) return; //Nothing to save
        if (array_key_exists($key, $this->__params)){
            $values[$key] = $this->__params[$key];
            $obj->write($values);
        }else{
            $obj->create($values);
        }
    }
}
?>