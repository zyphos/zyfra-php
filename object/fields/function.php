<?php
class FunctionField extends Field{
    // FunctionField('Label', 'my_fx');
    // FunctionField('Label', array($my_obj, 'my_fx'));
    var $get_fx=null;
    var $set_fx=null;
    var $stored=false;
    var $required_fields;

    function __construct($label, $fx, $args = null){
        $this->required_fields = array();
        parent::__construct($label, $args);
        $this->get_fx = $fx;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        if (count($this->required_fields)){
            $reqf = array();
            foreach($this->required_fields as $rf){
                $reqf[$rf] = $sql_query->field2sql($rf, $this->object, $parent_alias);
            }
            $sql_query->add_required_fields($reqf);
        }
        $sql_query->add_sub_query($this->object, $this->name, '!function!', $field_alias, $reqf);
        return $parent_alias->alias.'.'.$this->object->_key;
    }

    function get($ids, $context, $datas){
        //should return an array of object with array[id] = result
        if (is_null($this->get_fx)) return array();
        return call_user_func($this->get_fx, $ids, $context, $datas);
    }

    function set($ids, $value, $context){
        if (is_null($this->set_fx)) return array();
        return call_user_func($this->set_fx, $ids, $value, $context);
    }
}
?>
