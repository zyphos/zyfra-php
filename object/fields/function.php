<?php
class FunctionField extends Field{
    // FunctionField('Label', 'my_fx');
    // FunctionField('Label', array($my_obj, 'my_fx'));
    var $get_fx=null;
    var $set_fx=null;
    var $parameters=null;
    var $stored=false;
    var $select_all=false;
    var $read_only=true;
    var $required_fields;

    function __construct($label, $fx, $args = null){
        $this->required_fields = array();
        parent::__construct($label, $args);
        $this->get_fx = $fx;
        if (!is_null($this->set_fx) && !isset($args['read_only'])) $this->read_only = false;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('field_alias', $context)){
            $field_alias = $context['field_alias'];
        }else{
            $field_alias = '';
        }
        $parameter = array_key_exists('parameter', $context) ? $context['parameter'] : '';
        $reqf = array();
        if (count($this->required_fields)){
            foreach($this->required_fields as $rf){
                $rf = $rf.($parameter == ''?'':'['.$parameter.']');
                $reqf[$rf] = $sql_query->field2sql($rf, $this->object, $parent_alias);
            }
            $sql_query->add_required_fields($reqf);
        }
        $sql_query->add_sub_query($this->object, $this->name, '!function!', $field_alias, [
                        'reqf'=>$reqf,
                        'param'=>$parameter]);
        return $parent_alias->alias.'.'.$this->object->_key;
    }

    function get($ids, $context, $datas, $param){
        //should return an array of object with array[id] = result
        if (is_null($this->get_fx)) return array();
        if (count($ids) == 0) return [];
        $new_context = $context; //copy
        $new_context['parameter'] = $param;
        return call_user_func($this->get_fx, $this, $ids, $new_context, $datas);
    }

    function set($ids, $value, $context){
        if (is_null($this->set_fx)) return array();
        return call_user_func($this->set_fx, $ids, $value, $context, $parameters);
    }
}
?>
