<?php

class Pool{
    protected $pool = array();
    protected static $instance;
    protected $auto_create = false;
    protected $language_object_name = 'language';
    public $_default_user_id = 1;

    private function __construct(){
        // private = Avoid construct this object
        global $db;
        $this->db = $db;
    }

    private function __clone()
    {
        // private = Avoid cloning object
    }

    static function get(){
        if (!isset(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    protected function get_include_object_php($object_name){
        return null;
    }

    function &__get($key){
        if (array_key_exists($key, $this->pool)) return $this->pool[$key];
        $file = $this->get_include_object_php($key);
        if ($file != null) {
            if(file_exists($file)){
                include $file;
            }
        }
        if(!class_exists($key)){
            throw new Exception("Object class [".$key."] doesn't exists");
        }
        $obj = new $key($this);
        $this->add_object($key, $obj);
        return $obj;
    }

    function add_object($name, &$object){
        $this->pool[$name] = $object;
        $object->set_instance();
    }

    function object_in_pool($name){
        return array_key_exists($name, $this->pool);
    }

    function set_auto_create($flag){
        $this->auto_create = $flag;
    }

    function get_auto_create(){
        return $this->auto_create;
    }
    
    function get_available_objects(){
    	return array();
    }
    
    public function get_language_object_name(){
        return $this->language_object_name;
    }
}
