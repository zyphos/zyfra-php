<?php
require_once('tools.php');

class SqlTableAlias{
    var $parent;
    var $used;
    var $sql;
    var $alias;

    function __construct($alias, $parent, $sql = ''){
        $this->alias = $alias;
        $this->used = false;
        $this->parent = $parent;
        $this->sql = $sql;
    }

    function set_used(){
        $this->used = true;
        if ($this->parent !== null) $this->parent->set_used();
    }
}

class MqlWhere{
    var $sql_query;
    var $ta;
    var $obj;

    function __construct($sql_query){
        $this->sql_query = $sql_query;
        $this->operators = array('parent_of', 'child_of');
        $this->reserved_words = array('unknown', 'between', 'false', 'like', 'null', 'true', 'div', 'mod', 'not', 'xor', 'and', 'or', 'in');
        $this->basic_operators = array('+','-','=',' ','/','*','(',')',',','<','>','!');
    }

    function parse($mql_where, $obj=null, $ta=null){
        $this->obj = $obj;
        $this->ta = $ta;
        $mql_where = trim_inside($mql_where);
        $fields = specialsplitnotpar($mql_where, $this->basic_operators);
        foreach($fields as $key=>&$field){
            $lfield = strtolower($field);
            if($key % 2 == 1) continue;
            if ($field == '') continue;
            if (in_array($lfield, $this->operators)){
                $field = '';
            }elseif (in_array($lfield, $this->reserved_words)){
                continue;
            }elseif (isset($fields[$key+4]) && (in_array(strtolower($fields[$key+2]), $this->operators))){
                $op_data = $this->sql_query->field2sql($fields[$key+4], $this->obj, $this->ta);
                $field = $this->sql_query->field2sql($field, $this->obj, $this->ta, '', strtolower($fields[$key+2]), $op_data);
                $fields[$key+4] = $fields[$key+2] = '';
            }else{
                $field = $this->sql_query->field2sql($field, $this->obj, $this->ta);
            }
        }
        $sql_where = implode('', $fields);
        return $sql_where;
    }
}

class SqlQuery{
    var $table_alias;
    var $table_alias_nb;
    var $table_alias_prefix;
    var $sub_query_nb;
    var $group_by;
    var $order_by;
    var $where;
    var $where_no_parse;
    var $sub_queries;
    var $no_alias = '';

    function __construct($object, $ta_prefix = ''){
        $this->table_alias_prefix = $ta_prefix;
        $this->object = $object;
        $this->mql_where = new MqlWhere($this);
        $this->init();
    }

    private function init(){
        $this->table_alias_nb = 0;
        $this->sub_query_nb = 0;
        $this->pool = $this->object->_pool;
        $this->table_alias = array();
        //$this->fields_alias = array();
        if ($this->table_alias_prefix != ''){
            $this->add_table_alias('', $this->table_alias_prefix, null, '');
        }else{
            $this->get_table_alias('', 'FROM '.$this->object->_table.' AS %ta%');    
        }
        $this->sub_queries = array();
        $this->group_by = array();
        $this->where = array();
        $this->where_no_parse = array();
        $this->order_by = array();
    }

    public function no_alias($alias){
        $this->no_alias = $alias;
    }

    public function get_new_table_alias(){
        return $this->table_alias_prefix.'t'.$this->table_alias_nb++;
    }

    public function get_table_alias($field_link, $sql = '', $parent_alias=null){
        if (array_key_exists($field_link, $this->table_alias)){
            return $this->table_alias[$field_link];
        }
        $table_alias = $this->get_new_table_alias();
        if ($sql != ''){
            $sql = str_replace('%ta%', $table_alias, $sql);
        }
        return $this->add_table_alias($field_link, $table_alias, $parent_alias, $sql);
    }

    private function add_table_alias($field_link, $table_alias, $parent_alias, $sql){
        $ta = new SqlTableAlias($table_alias, $parent_alias, $sql);
        $this->table_alias[$field_link] = $ta;
        return $ta;
    }

    public function get_table_sql(){
        $tables = '';
        foreach($this->table_alias as $table_alias){
            if ($table_alias->used) $tables .= ' '.$table_alias->sql;
        }
        return $tables;
    }

    function mql2sql($mql, $context = array(), $no_init=false){
        $debug = array_get($context, 'debug', false);
        if ($debug) echo 'mql: '.htmlentities($mql)."<br>\n";
        $this->context = $context;
        $mql = strtolower($mql);
        $keywords = array('limit', 'order by', 'having', 'group by', 'where');
        $query_datas = array();
        foreach($keywords as $keyword){
            $datas = multispecialsplit($mql, $keyword.' ');
            if (count($datas)> 1){
                $query_datas[$keyword] = trim($datas[1]);
            }
            $mql = $datas[0];
        }
        $sql = 'SELECT '.$this->parse_mql_fields($mql);
        if(!array_key_exists('order by',$query_datas)){
            $query_datas['order by'] = '';
        }

        if (count($this->group_by)>0 && !array_key_exists('group by',$query_datas)){
            $query_datas['group by'] = '';
        }
        $keywords = array_reverse($keywords);
        $sql_words = '';
        if (array_get($this->context, 'domain')){
            $this->where[] = $this->context['domain'];
        }
        if (array_get($this->context, 'visible', true)&&($this->object->_visible_condition != '')){
            $this->where[] = $this->object->_visible_condition;
        }
        if (count($this->where)>0 && !array_key_exists('where',$query_datas)){
            $query_datas['where'] = '';
        }
        foreach ($keywords as $keyword){
            if (array_key_exists($keyword, $query_datas)){
                $data = call_user_func_array(array($this, 'parse_mql_'.str_replace(' ', '_', $keyword)),array($query_datas[$keyword]));
                if ($data != '') $sql_words .= ' '.strtoupper($keyword).' '.$data;
            }elseif ($keyword=='where' && array_get($this->context, 'domain', '') != ''){
                $sql_words .= ' WHERE '.$this->mql_where->parse($this->context['domain']);
            }
        }
        $sql .= ' '.$this->get_table_sql().$sql_words;
        if(!$no_init) $this->init();
        if ($debug) echo 'sql: '.htmlentities($sql)."<br>\n";
        return $sql;
    }
    
    function where2sql($mql, $context = array()){
        $this->context = $context;
        if (array_get($this->context, 'domain')){
            $this->where[] = $this->context['domain'];
        }
        if (array_get($this->context, 'visible', true)&&($this->object->_visible_condition != '')){
            $this->where[] = $this->object->_visible_condition;
        }
        $sql = $this->parse_mql_where($mql);
        $sql .= ' '.$this->get_table_sql();
        return $sql;
    }

    function get_array($mql, $context = array()){
        $sql = $this->mql2sql($mql, $context, true);
        $key = array_get($context, 'key', '');
        $datas = $this->pool->db->get_array_object($sql, $key);
        $field_alias_ids = array();
        $row_field_alias_ids = array();
        if (count($datas)>0){
            foreach($this->sub_queries as $sub_query){
                list($robject, $rfield, $sub_mql, $field_alias, $parameter) = $sub_query;
                if(array_key_exists($field_alias, $field_alias_ids)){
                    $ids = $field_alias_ids[$field_alias];
                    $row_alias_ids = $row_field_alias_ids[$field_alias];
                    foreach(array_keys($ids) as $key) if (trim($ids[$key])=='') unset($ids[$key]);
                }else{
                    $ids = array();
                    $row_alias_ids = array();
                    foreach($datas as $row_id=>$row){
                        $ids[$row->{$field_alias}] = true;
                        $row_alias_ids[$row_id] = $row->$field_alias;
                        $row->$field_alias = array();
                    }
                    $ids = array_keys($ids);
                    foreach(array_keys($ids) as $key) if (trim($ids[$key])=='') unset($ids[$key]);
                    $field_alias_ids[$field_alias] = $ids;
                    $row_field_alias_ids[$field_alias] = $row_alias_ids;
                }
                if ($sub_mql == '!function!'){
                    $sub_datas = $robject->$rfield->get($ids, $context);
                }else{
                    if ($parameter!='') $parameter .= ' AND ';
                    $sub_datas = $robject->select($rfield.' AS _subid,'.$sub_mql, array_merge($context, array('domain'=>$parameter.$rfield.' IN('.implode(',', $ids).')')));
                }
                foreach($row_alias_ids as $row_id=>$id){
                    foreach($sub_datas as $sub_row){
                        if ($sub_row->_subid == $id){
                            $datas[$row_id]->{$field_alias}[] = $sub_row;
                        }
                    }
                }
                foreach($sub_datas as $sub_row){
                    unset($sub_row->_subid);
                }
            }
        }
        $this->init();
        return $datas;
    }

    function parse_mql_fields($field_defs, $recursive=false){
        if ($recursive) $saved_fields = $this->sql_select_fields;
        $this->sql_select_fields = array();
        $this->split_select_fields($field_defs, $recursive);
        $result = implode(',', $this->sql_select_fields);
        if ($recursive) $this->sql_select_fields = $saved_fields;
        return $result;
    }

    function split_select_fields($field_defs, $recursive=false, $obj = null, $ta = null, $pre_alias = ''){
        if ($obj==null) $obj = $this->object;
        if(!is_array($field_defs)) $field_defs = specialsplit($field_defs);
        foreach (array_keys($field_defs) as $key){
            if (trim($field_defs[$key]) == '*'){
                unset($field_defs[$key]);
                foreach($obj->_columns as $name=>$column){
                    if (!$column->relational) $field_defs[] = $name;
                }
            }
        }

        foreach($field_defs as $field_def){
            $datas = multispecialsplit($field_def, ' as ');
            $field_name = trim($datas[0]);
            if (count($datas)>1){
                $alias = trim($datas[1]);
                $auto_alias = false;
            }else{
                //No alias auto generate it
                $auto_alias = true;
                $alias = $field_name;
                $pos = strpos($alias, '.(');
                if ($pos !== false) $alias = substr($alias, 0, $pos);
                $alias = str_replace(array('.', '[', ']','='), '_', $alias);
            }
            if ($pre_alias != ''){
                $alias = $pre_alias.'_'.$alias;
            }
            $sql_field = $this->field2sql($field_name, $obj, $ta, $alias);
            if ($sql_field != null) {
                $fields = explode('.',$sql_field);
                $no_alias = $recursive || $auto_alias && (array_pop($fields)==$alias);
                $this->sql_select_fields[] = $sql_field.($no_alias?'':' AS '.$alias);
            }
        }
    }

    function parse_mql_where($mql_where){
        if (count($this->where)){
            $where = implode(' AND ', $this->where);
            if ($mql_where != ''){
                $mql_where = $where.' AND('.$mql_where.')';
            }else{
                $mql_where = $where;
            }
        }
        $where = $this->mql_where->parse($mql_where);
        if (count($this->where_no_parse)){
            $where_np = implode(' AND ', $this->where_no_parse);
            if ($where != ''){
                $where = $where_np.' AND('.$where.')';
            }else{
                $where = $where_np;
            }
        }
        return $where;
    }

    function parse_mql_group_by($mql_group_by){
        $fields = explode(',', $mql_group_by);
        $sql_fields = array();
        foreach($this->group_by as $field_name){
            $sql_fields[] = $field_name;
        }
        foreach($fields as $field_name){
            $field_name = trim($field_name);
            if ($field_name != '') $sql_fields[] = $this->field2sql($field_name);
        }
        return implode(',', $sql_fields);
    }

    function parse_mql_having($mql_having){
        return $this->mql_where->parse($mql_having);
    }

    private function convert_order_by(&$array_order_parsed, $mql_order_by){
        $fields = explode(',', $mql_order_by);
        foreach($fields as $field){
            if (trim($field)=='') continue;
            $fields = explode(' ', trim($field));
            $field_name = array_shift($fields);
            $array_order_parsed[] = $this->field2sql($field_name).' '.implode(' ', $fields);
        }
    }

    function parse_mql_order_by($mql_order_by){
        $sql_order = array();
        $this->convert_order_by($sql_order, $mql_order_by);
        $sql_order = array_merge($sql_order, $this->order_by);
        $this->convert_order_by($sql_order, $this->object->_order_by);
        return implode(',', $sql_order);
    }

    function parse_mql_limit($mql_limit){
        return $mql_limit;
    }

    function split_field_param($field_name){
        return specialsplitparam($field_name);
    }

    function field2sql($field_name, $obj = null, $ta = null, $field_alias = '', $operator='', $op_data=''){
        if (is_numeric($field_name)) return $field_name;
        if ($obj === null) $obj = $this->object;
        if ($ta === null) $ta = $this->table_alias[''];
        $fx_regex = '/^([a-z_]+)\((.*)\)$/';
        if (preg_match($fx_regex, $field_name, $matches)){
            return $matches[1].'('.$this->parse_mql_fields($matches[2], true).')';
        }
        $fields = specialsplit($field_name, '.');
        $field = array_shift($fields);
        list($field_name, $field_data) = specialsplitparam($field);
        if (!array_key_exists($field_name, $obj->_columns)) return $field_name;
        $context = array('parameter'=>$field_data, 'field_alias'=>$field_alias, 'operator'=>$operator, 'op_data'=>$op_data);
        return $obj->_columns[$field_name]->get_sql($ta, $fields, $this, $context);
    }

    function add_sub_query($robject, $rfield, $sub_mql, $field_alias, $parameter){
        $this->sub_queries[] = array($robject, $rfield, $sub_mql, $field_alias, $parameter);
    }
}
?>