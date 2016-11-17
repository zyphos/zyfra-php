<?php
class ContextedPool{
   public $_context = null;
   protected $_original_pool = null; 
   
   public function __construct(&$pool, $context){
       $this->_original_pool = $pool;
       $this->_context = $context;
   }
   
   public function __call($method, $args){
       if ($this->_original_pool->object_in_pool($method)){
           return new ContextedObjectModel($this->_original_pool->$method, $this, array_merge($this->_context, $args[0]));
       }
       return call_user_func_array(array($this->_original_pool, $method), $args);
   }
   
   public function __get($attribute){
       $attribute = $this->_original_pool->$attribute;
       if ($attribute instanceof ObjectModel){
           return new ContextedObjectModel($attribute, $this, $this->_context);
       }
       return $attribute;
   }
   
   public function __set($attribute, $value){
       $this->_original_pool->$attribute = $value;
   }
   
   public function __invoke($context){
       return new ContextedPool($this, $context);
   }
}

class Pool{
    protected $pool = array();
    protected static $instance;
    protected $auto_create = false;
    protected $language_object_name = 'language';
    public $_default_user_id = 1;
    public $_context = null;
    public $_model_class='ObjectModel';

    private function __construct(){
        // private = Avoid construct this object
        global $db;
        $this->db = $db;
        $this->_context = array();
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

    public function &__get($key){
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

    public function add_object($name, &$object){
        $this->pool[$name] = $object;
        $object->set_instance();
    }

    public function object_in_pool($name){
        return array_key_exists($name, $this->pool);
    }

    public function set_auto_create($flag){
        $this->auto_create = $flag;
    }

    public function get_auto_create(){
        return $this->auto_create;
    }
    
    public function get_available_objects(){
    	return array();
    }
    
    public function get_objects_in_pool(){
        return array_keys($this->pool);
    }
    
    public function instanciate_all_objects(){
        $object_names = $this->get_available_objects();
        foreach($object_names as $object_name){
            $this->$object_name;
        }
    }
    
    public function get_language_object_name(){
        return $this->language_object_name;
    }
    
    public function __invoke($context){
        return new ContextedPool($this, $context);
    }
    
    public function __call($method, $args){
        $object = $this->__get($method);
        $new_object = new ContextedObjectModel($object, $this, array_merge($this->_context, $args[0]));
        return $new_object;
    }
}

