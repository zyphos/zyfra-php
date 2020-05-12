<?
require_once 'relational.php';

class One2ManyField extends RelationalField{
    var $widget='one2many';
    var $relation_object_field;
    var $stored=false;
    var $local_key;

    function __construct($label, $relation_object_name, $relation_object_field, $args = array()){
        $this->local_key = '';
        parent::__construct($label, $relation_object_name, $args);
        $this->relation_object_field = $relation_object_field;
    }
    
    function set_instance($object, $name){
        parent::set_instance($object, $name);
        if ($this->local_key == '') $this->local_key = $object->_key;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if ($sql_query->debug > 1) echo 'O2M['.$this->name.'] fields: '.print_r($fields, true).'<br>';
        if (array_key_exists('parameter', $context)){
            $parameter = $context['parameter'];
        }else{
            $parameter = '';
        }
        $is_where = isset($context['is_where']) && $context['is_where'];
        
        if ($is_where && count($fields) == 0){
            $fields[] = $this->relation_object_key;
        }
        $key_field = $parent_alias->alias.'.'.$this->local_key;
        $robject = $this->get_relation_object();
        $sql = 'LEFT JOIN '.$robject->_table.' AS %ta% ON %ta%.'.$this->relation_object_field.'='.$key_field;
        $field_link = $parent_alias->alias.'.'.$this->name.$parameter;
        $ta = $sql_query->get_table_alias($field_link, $sql, $parent_alias);

        if (count($fields)==0){
            $ta->set_used();
            $sql_query->group_by[] = $key_field;
            if ($parameter != ''){
                $mql_where = new MqlWhere($sql_query);
                $sql_where = $mql_where->parse($parameter, $robject, $ta);
                $sql_query->table_alias[$field_link]->sql .= ' AND('.$sql_where.')';
            }
            return 'count('.$key_field.')';
        }else{
            $field_name = array_shift($fields);
            if($field_name[0] == '('){
                if (array_key_exists('field_alias', $context)){
                    $field_alias = $context['field_alias'];
                }else{
                    $field_alias = '';
                }
                $sub_mql = substr($field_name, 1, -1);
                if ($sql_query->debug > 1) echo 'O2M subquery['.$this->name.'] robj_field: '.$this->relation_object_field.' sub_mql: '.print_r($sub_mql, true).'<br>';
                $sql_query->add_sub_query($robject, $this->relation_object_field, $sub_mql, $field_alias, $parameter);
                $parent_alias->set_used();
                return $key_field;
            }else{
                if ($parameter != ''){
                    $mql_where = new MqlWhere($sql_query);
                    $sql_where = $mql_where->parse($parameter, $robject, $ta);
                    $sql_query->table_alias[$field_link]->sql .= ' AND('.$sql_where.')';
                }
                list($field_name, $field_param) = specialsplitparam($field_name);
                $context['parameter'] = $field_param;
                if (!isset($robject->_columns[$field_name])){
                    throw new Exception('Column ['.$field_name.'] not found in object ['.$robject->_name.']');
                }
                return $robject->_columns[$field_name]->get_sql($ta, $fields, $sql_query, $context);
            }
        }
    }

    function sql_create($sql_create, $value, $fields, $context){
        return new zyfra\orm\Callback('sql_create_after_trigger', null);
    }

    function sql_create_after_trigger($sql_create, $value, $fields, $context, $id){
        $robject = $this->get_relation_object();
        foreach($value as $rvalues){
            if (!is_array($rvalues)) continue;
            $rvalues[$this->relation_object_field] = $id;
            $robject->create($rvalues, $context);
        }
    }
}
