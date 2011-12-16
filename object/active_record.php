<?php
class ActiveRecord{
    protected $object;
    protected $params;
    protected $modified_columns;

    public function __construct($object, $params = array()){
        $this->object = $object;
        $this->params = $params;
        $this->modified_columns = array();
    }

    public function __get($name){
        if (array_key_exists($name, $this->params)) return $this->params[$name];
        if (array_key_exists($name, $this->object->_columns)){
            return $this->object->_columns[$name]->get_default();
        }
        throw new Exception('Column '.$name.' not found in '.$this->object->_name);
    }

    public function __set($name, $value){
        switch($name){
            case 'id':
            case 'create_date':
            case 'write_date':
                throw new Exception('Column '.$name.' can\'t be modified');
        }
        if (array_key_exists($name, $this->object->_columns)){
            if (!array_key_exists($name, $this->params) || $this->params[$name] != $value){
                $this->modified_columns[$name] = true;
            }
            $this->params[$name] = $value;
        }
        throw new Exception('Column '.$name.' not found in '.$this->object->_name);
    }

    public function save(){
        $values = array();
        foreach($this->modified_columns as $col_name=>$t){
            $values[$col_name] = $this->params[$col_name];
        }
        if (count($values) == 0) return; //Nothing to save
        if (array_key_exists('id', $this->params)){
            $values['id'] = $this->params['id'];
            $this->object->write($values);
        }else{
            $this->object->create($values);
        }
    }
}
?>