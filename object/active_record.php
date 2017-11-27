<?php
class ActiveRecord{
    protected $__object;
    protected $__context;
    protected $__data;
    protected $__params;
    protected $__modified_columns;
    protected $__mql_where;
    protected $__id;

    public function __construct($object, $params, $context = array()){
        /* $params is an array:
         * it contains predefined attribute. Ie: $params = array('uid'=>4,'name'=>'test');
         * or the key id
         * $param = 8;
         */
        $this->__object = $object;
        $this->__context = $context;
        $this->__data = null;
        if (!is_array($params)){
            $params = array($object->_key=>$params);
        }
        $this->__params = $params;
        $this->__modified_columns = array();
    }
    
    private function __get_data(){
        // TODO: prefetch all stored field (like Many2one)
        $obj = $this->__object;
        $key = $obj->_key;
        if(!is_null($this->__data) || (!array_key_exists($key, $this->__params) && !array_key_exists('mql_where', $this->__context))) return;
        $this->__id = array_key_exists($key, $this->__params)?$this->__params[$key]:0;
        $mql_fields = array_key_exists('mql_fields', $this->__context)?$this->__context['mql_fields']:'*';
        $this->__mql_where = array_key_exists('mql_where', $this->__context)?$this->__context['mql_where']:$key.'=%s';
        $result = $obj->select([$mql_fields.' WHERE '.$this->__mql_where, [$this->__id]], $this->__context);
        if (count($result)) $this->__data = $result[0];
    }
    
    private function __add_data($mql_fields){
        // TODO: Handle One2Many, Many2Many
        if(is_null($this->__data)) return;
        $obj = $this->__object;
        $result = $obj->select([$mql_fields.' WHERE '.$this->__mql_where, [$this->__id]], $this->__context);
        if (count($result)) {
            foreach ($result[0] as $key=>$value){
                $this->__data->$key = $value;
            }
        }
    }
    
    private function __return_active_field($field_name){
        if ($this->__data->$field_name instanceof ActiveRecord) return $this->__data->$field_name;
        $column = $this->__object->_columns[$field_name];
        if($column instanceof Many2OneField){
            $this->__data->$field_name = new ActiveRecord($column->relation_object, array($column->relation_object_key=>$this->__data->$field_name), $this->__context);
        }
        return $this->__data->$field_name;
    }
    
    public function __get($name){
        if (array_key_exists($name, $this->__params)) return $this->__params[$name];
        $obj = $this->__object;
        if (!array_key_exists($name, $obj->_columns)) throw new Exception('Column '.$name.' not found in '.$obj->_name);
        $this->__get_data();
        if (is_null($this->__data)) return $obj->_columns[$name]->get_default();
        if (isset($this->__data->$name)) return $this->__return_active_field($name);
        $this->__add_data($name);
        return $this->__return_active_field($name);
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
            $obj->write($values, (int)$this->__params[$key], $this->__context);
        }else{
            $obj->create($values, $this->__context);
        }
    }
}
