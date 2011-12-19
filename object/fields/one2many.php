<?
class One2ManyField extends Field{
    var $type='one2many';
    var $relation_object_name;
    var $relation_object_field;
    var $relation_object=null;
    var $stored=false;
    var $relational=true;

    function __construct($label, $relation_object_name, $relation_object_field, $args = array()){
        parent::__construct($label, $args);
        $this->left_right = true;
        $this->relation_object_name = $relation_object_name;
        $this->relation_object_field = $relation_object_field;
    }

    function get_relation_object(){
        if ($this->relation_object===null){
            try{
                $this->relation_object = $this->object->_pool->__get($this->relation_object_name);
            } catch(PoolObjectException $e){
                throw new Exception('Could not find object '.$this->relation_object_name.' field many2one '.$this->name.' from object '.$this->object->_name);
            }
        }
        return $this->relation_object;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('parameter', $context)){
            $parameter = $context['parameter'];
        }else{
            $parameter = '';
        }
        $key_field = $parent_alias->alias.'.'.$this->object->_key;
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
                $sql_query->add_sub_query($robject, $this->relation_object_field, $sub_mql, $field_alias, $parameter);
                $parent_alias->set_used();
                //print 'sub_query: '.$sub_mql."<br>\n";
                return $key_field;
            }else{
                if ($parameter != ''){
                    $mql_where = new MqlWhere($sql_query);
                    $sql_where = $mql_where->parse($parameter, $robject, $ta);
                    $sql_query->table_alias[$field_link]->sql .= ' AND('.$sql_where.')';
                }
                //print 'field_name:'.$field_name."<br>\n";
                list($field_name, $field_param) = $sql_query->split_field_param($field_name);
                $context['parameter'] = $field_param;
                //print 'field:'.$field_name."<br>\n";
                //print 'params:'.$field_param."<br>\n<br>\n";

                //print $field_name.'['.$field_param."]<br>\n";
                return $robject->_columns[$field_name]->get_sql($ta, $fields, $sql_query, $context);
            }
        }
    }
}
?>