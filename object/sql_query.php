<?php
require_once('tools.php');
require_once(dirname(__FILE__).'/../debug.php');

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
        $this->reserved_words = array('unknown', 'between', 'false', 'like', 'null', 'true', 'div', 'mod', 'not', 'xor', 'and', 'or','in');
        $this->basic_operators = array('+','-','=','/','*','<','>','!','in');
        $this->parenthesis = array('(',')',' ',',');
        $this->split_char = array_merge($this->basic_operators, $this->parenthesis);
        $this->all_operators = array_merge($this->basic_operators, $this->operators);
    }
    
    protected function field2sql($field_name, $obj = null, $ta = null, $field_alias = '', &$operator='', $op_data=''){
        if ($this->sql_query->debug > 4) {
            $obj_name = is_null($obj)?'':$obj->_name;
            zyfra_debug::show_msg('Where Field2sql['.$obj_name.'->'.$field_name.']');
        }
        $res = $this->sql_query->field2sql($field_name, $obj, $ta, $field_alias, $operator, $op_data, true);
        if ($this->sql_query->debug > 3) {
            $obj_name = is_null($obj)?'':$obj->_name;
            zyfra_debug::show_msg('Where Field2sql['.$obj_name.'->'.$field_name.']=>['.$res.']');
        }
        return $res;
    }

    function parse($mql_where, $obj, $ta=null){
        $language_id = array_get($this->sql_query->context, 'language_id', 0);
        $mql_where = str_replace('%language_id%', $language_id, $mql_where);
        $this->obj = $obj;
        $this->ta = $ta;
        $mql_where = trim_inside($mql_where);
        $fields = specialsplitnotpar($mql_where, $this->split_char);
        $previous_operator = null;
        for($i=count($fields)-1;$i>0;$i--){
            if (trim($fields[$i]) == ''){
                unset($fields[$i]);
                continue;
            }
            if (in_array($fields[$i], $this->basic_operators)){
                if (is_null($previous_operator)){
                    $previous_operator = $i;
                    continue;
                }else{
                    $fields[$previous_operator] = $fields[$i].$fields[$previous_operator];
                    unset($fields[$i]);
                    continue;
                }
            }else{
                $previous_operator = null;
            }
            if ($fields[$i] == 'in') $fields[$i] = ' in '; 
        }

        $fields = array_merge($fields);
        
        if ($this->sql_query->debug > 3){
            echo '<pre>Where fields:';
            print_r($fields);
            echo '</pre>';
        }
        $nfields = count($fields);
        for ($key=0;$key<$nfields;$key++){
            $field = &$fields[$key];
            $lfield = strtolower($field);
            if ($field == '') continue;
            if ($field == ',') continue;
            if (in_array($lfield, $this->operators)){
                $field = '';
            }elseif (in_array($lfield, $this->reserved_words)){
                continue;
            }elseif (isset($fields[$key+2]) && (in_array(strtolower($fields[$key+1]), $this->all_operators))){
                //echo 'operator:'.$fields[$key+1].'<br>';
                $i = 2;
                $parenthesis_lvl = 0;
                $op_data = '';
                while(isset($fields[$key+$i])){
                    $val = trim($fields[$key+$i]);
                    if ($val == ''){
                        // Do nothing skip
                    }elseif ($val == '('){
                        $op_data .= $val;
                        $parenthesis_lvl++;
                    }elseif ($parenthesis_lvl){
                        if ($val == ')'){
                            $op_data .= $val;
                            $parenthesis_lvl--;
                            if ($parenthesis_lvl == 0) break;
                        }else{
                            $op_data .= $this->field2sql($val, $this->obj, $this->ta);
                        }
                    }else{
                        $op_data .= $this->field2sql($val, $this->obj, $this->ta);
                        break;
                    }
                    $i++;
                }
                //echo 'operator:'.$fields[$key+1].'<br>';
                //echo '$op_data:<br>'.htmlentities($op_data).'<br>';
                // Rebuild
                $operator = strtolower($fields[$key+1]);
                $field = $this->field2sql($field, $this->obj, $this->ta, '', $operator, $op_data);
                if (is_null($operator)){ // Operator has been treated
                    for ($j = 1; $j <= $i; $j++){
                        unset($fields[$key+$j]);
                    }
                }
                $key += $i;
            }else{
                $field = $this->field2sql($field, $this->obj, $this->ta);
            }
        }
        if ($this->sql_query->debug > 4){
            echo 'Fields after where parse:';
            zyfra_debug::printr($fields);
        }
        $sql_where = implode(' ', $fields);
        $sql_where = str_replace(' ( ', '(', $sql_where);
        $sql_where = str_replace(' ) ', ')', $sql_where);
        if ($this->sql_query->debug > 3){
            echo '<pre>Where sql:<br>';
            echo htmlentities($sql_where);
            echo '</pre>';
        }
        return $sql_where;
    }
}
$sql_query_id = 0;

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
    var $sql_field_alias;
    var $required_fields;
    var $remove_from_result;
    protected $has_group_by = false;
    var $debug = false;
    protected $keywords = array('limit', 'order by', 'having', 'group by', 'where');
    protected $keywords_split = array('limit ', 'order by ', 'having ', 'group by ', 'where ');
    private $rqi = 0; 

    function __construct($object, $ta_prefix = ''){
        global $sql_query_id;
        $this->table_alias_prefix = $ta_prefix;
        $this->object = $object;
        $this->mql_where = new MqlWhere($this);
        $this->init();
        $this->remove_from_result = array();
        $this->__uid__ = ++$sql_query_id;
    }

    private function init(){
        $this->table_alias_nb = 0;
        $this->sub_query_nb = 0;
        $this->pool = $this->object->_pool;
        $this->table_alias = array();
        //$this->fields_alias = array();
        if ($this->table_alias_prefix != ''){
            $this->ta = $this->add_table_alias('', $this->table_alias_prefix, null, '');
        }else{
            $this->ta = $this->get_table_alias('', 'FROM '.$this->object->_table.' AS %ta%');    
        }
        $this->sub_queries = array();
        $this->group_by = array();
        $this->where = array();
        $this->where_no_parse = array();
        $this->order_by = array();
        $this->required_fields = array();
        $this->sql_field_alias = array();
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
    
    /*function split_keywords_text($mql){
        $nb_check = 1000;
        
        $start = microtime(true);
        for($i=0;$i<$nb_check;$i++){
            list($mql_old, $data_old) = $this->split_keywords_old($mql);
        }
        $stop = microtime(true);
        $old_time = $stop-$start;
        $start = microtime(true);
        for($i=0;$i<$nb_check;$i++){
            list($mql_new, $data_new) = $this->split_keywords_new($mql);
        }
        $stop = microtime(true);
        $new_time = $stop-$start;
        
        $start = microtime(true);
        for($i=0;$i<$nb_check;$i++){
            list($mql_new, $data_new) = $this->split_keywords_new2($mql);
        }
        $stop = microtime(true);
        $new2_time = $stop-$start;

        list($mql_old, $data_old) = $this->split_keywords_old($mql);
        list($mql_new, $data_new) = $this->split_keywords_new($mql);
        list($mql_new2, $data_new2) = $this->split_keywords_new2($mql);
        echo '<h4>old</h4>';
        print_pre($mql_old);
        print_pre($data_old);
        printf("%.3f sec",$old_time);
        echo '<h4>new</h4>';
        print_pre($mql_new);
        print_pre($data_new);
        printf("%.3f sec",$new_time);
        echo '<h4>new2</h4>';
        print_pre($mql_new2);
        print_pre($data_new2);
        printf("%.3f sec",$new2_time);
        echo '<hr>';
        return array($mql_old, $data_old);
    }
    
    function split_keywords_old($mql){
        $query_datas = array();
        foreach($this->keywords as $keyword){
            $datas = multispecialsplit($mql, $keyword.' ');
            if (count($datas)> 1){
                $query_datas[$keyword] = trim($datas[1]);
            }
            $mql = $datas[0];
        }
        return array($mql, $query_datas);
    }
    
    function split_keywords_new($mql){
        $datas = multispecialsplit($mql, $this->keywords_split, true);
        $mql = $datas[0];
        $nb = count($datas);
        $query_datas = array();
        for($i=1; $i < $nb; $i+=2){
            $query_datas[substr($datas[$i],0,-1)] = $datas[$i+1];
        }
        return array($mql, $query_datas);
    }*/
    
    function split_keywords($mql){
        $datas = r_multi_split_array($mql, $this->keywords_split);
        $mql = &$datas[''];
        $query_datas = array();
        $keywords = array_keys($datas);
        foreach($keywords as &$keyword){
            if ($keyword=='') continue;
            $query_datas[substr($keyword,0,-1)] = &$datas[$keyword];
        }
        return array($mql, $query_datas);
    }

    function mql2sql($mql, $context = array(), $no_init=false){
        $this->debug = array_get($context, 'debug', false);
        $this->context = $context;
        $mql = strtolower($mql);
        list($mql, $query_datas) = $this->split_keywords($mql);
        if ($this->debug) {
            $s = multispecialsplit($mql, ',');
            $s = array_map('htmlentities', $s);
            $txt = implode($s,",<br>");
            $txt .= "<br>";
            foreach(array_reverse($query_datas,true) as $key=>$value){
                $txt .= '<b>'.strtoupper($key).'</b><br>'.htmlentities($value).'<br>';
            }
            $txt .= '<b>Context:</b><pre>'.print_r($context, true).'</pre>';
            zyfra_debug::print_set('MQL['.$this->__uid__.']: Model['.$this->object->_name.']', $txt);
        }
        $sql = 'SELECT '.$this->parse_mql_fields($mql);
        if(!array_key_exists('order by',$query_datas)){
            $query_datas['order by'] = '';
        }

        if (count($this->group_by)>0 && !array_key_exists('group by',$query_datas)){
            $query_datas['group by'] = '';
        }
        $keywords = array_reverse($this->keywords);
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
                $sql_words .= ' WHERE '.$this->mql_where->parse($this->context['domain'], $this->object, $this->ta);
            }
        }
        $sql .= ' '.$this->get_table_sql().$sql_words;
        if(!$no_init) $this->init();
        if ($this->debug) {
            $mss = multispecialsplit($sql, array('LIMIT ', 'ORDER BY ', 'HAVING ', 'GROUP BY ', 'WHERE ','SELECT ', 'FROM ', 'LEFT JOIN', 'JOIN'), true);
            $txt = '';
            for($i=1;$i<count($mss);$i+=2){
                $key = $mss[$i];
                $ss = $mss[$i+1];
                if (trim($ss) == '') continue;
                $txt .= '<b>'.$key.'</b><br>';
                if ($key == 'SELECT '){
                    $s = multispecialsplit($ss, ',');
                    $s = array_map('htmlentities', $s);
                    $txt .= implode($s,",<br>");
                    $txt .= "<br>";
                }else{
                    $txt .= htmlentities($ss).'<br>';
                }
            }
            zyfra_debug::print_set('SQL['.$this->__uid__.']:', $txt);
        }
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
        if(isset($context['key'])){
            $key = $context['key'];
            unset($context['key']);
        }else{
            $key = '';
        }
        //if (!isset($this->object->_columns[$key])) $key = ''; //$key != $this->object->_key &&
        $datas = $this->pool->db->get_array_object($sql, $key);
        $field_alias_ids = array();
        $row_field_alias_ids = array();
        if (count($datas)>0){
            foreach($this->sub_queries as $sub_query){
                list($robject, $rfield, $sub_mql, $field_alias, $parameter) = $sub_query;
                $is_fx = $sub_mql == '!function!';
                if(array_key_exists($field_alias, $field_alias_ids)){
                    $ids = $field_alias_ids[$field_alias];
                    $row_alias_ids = $row_field_alias_ids[$field_alias];
                    //foreach(array_keys($ids) as $key) if (trim($ids[$key])=='') unset($ids[$key]);
                }else{
                    $ids = array();
                    $row_alias_ids = array();
                    foreach($datas as $row_id=>$row){
                        $ids[$row->{$field_alias}] = true;
                        //$row_alias_ids[$row_id] = $row->$field_alias;
                        if(!isset($row_alias_ids[$row->$field_alias])) $row_alias_ids[$row->$field_alias] = array();
                        $row_alias_ids[$row->$field_alias][] = $row_id;
                        if (!$is_fx) $row->$field_alias = array();
                    }
                    $ids = array_keys($ids);
                    foreach(array_keys($ids) as $key) if (trim($ids[$key])=='') unset($ids[$key]);
                    $field_alias_ids[$field_alias] = $ids;
                    $row_field_alias_ids[$field_alias] = $row_alias_ids;
                }
                if ($is_fx){
                    $fx_data = array();
                    if(count($parameter)>0){
                        foreach ($ids as $id){
                            $obj = new stdClass;
                            foreach($parameter as $key=>$field){
                                foreach($row_alias_ids[$id] as $row_id){
                                    $obj->$key = $datas[$row_id]->{$this->sql_field_alias[$field]};
                                }
                            }
                            $fx_data[$id] = $obj;
                        }
                    }
                    $sub_datas = $robject->$rfield->get($ids, $context, $fx_data);
                    foreach($row_alias_ids as $id=>$row_ids){
                        if ($id=='') continue;
                        foreach($row_ids as $row_id){
                            $datas[$row_id]->{$field_alias}= $sub_datas[$id];
                        }
                    }
                }else{
                    if ($parameter!='') $parameter = '('.$parameter.') AND ';
                    if (count($ids)){
                        $ids = $this->object->_pool->db->var2sql($ids, true);
                        $nctx = array_merge($context, array('domain'=>$parameter.$rfield.' IN '.$ids));
                        if (array_key_exists('key', $nctx)) unset($nctx['key']); 
                        /*echo 'context:<br><pre>';
                         print_r($nctx);
                        echo '</pre>';//*/
                        $sub_datas = $robject->select($rfield.' AS _subid,'.$sub_mql, $nctx);
                        foreach($row_alias_ids as $id=>$row_ids){
                            foreach($sub_datas as $sub_row){
                                if ($sub_row->_subid == $id){
                                    foreach($row_ids as $row_id){
                                        $datas[$row_id]->{$field_alias}[] = $sub_row;
                                    }
                                }
                            }
                        }
                    }
                }
                if (isset($sub_datas)){
                    foreach($sub_datas as $sub_row){
                        unset($sub_row->_subid);
                    }
                }
            }
        }
        if(count($this->remove_from_result)){
            foreach($datas as &$row){
                foreach($this->remove_from_result as $alias){
                    unset($row->$alias);
                }
            }
        }
        $this->init();
        return $datas;
    }
    
    function get_scalar_array($value_field, $key_field=null, $where = '', $context = array()){
    	$mql = $value_field.(is_null($key_field)?'':','.$key_field).' '.$where;
    	$sql = $this->mql2sql($mql, $context, true);
    	if (is_null($key_field)){
    		$key_field = '';
    	}
    	return $this->pool->db->get_array($sql, $key_field, $value_field);
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
                    if ($column->select_all && $name != '_display_name') $field_defs[] = $name;
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
                $alias = str_replace(array('.', '[', ']','=','<','>'), '_', $alias);
            }
            if ($pre_alias != ''){
                $alias = $pre_alias.'_'.$alias;
            }
            $sql_field = $this->field2sql($field_name, $obj, $ta, $alias);
            if ($sql_field != null) {
                $fields = explode('.',$sql_field);
                $last_field = array_pop($fields);
                $no_alias = $recursive || $auto_alias && ($last_field==$alias);
                $this->sql_select_fields[] = $sql_field.($no_alias?'':' AS '.$alias);
                if (!$recursive) $this->sql_field_alias[$sql_field] = ($no_alias?$last_field:$alias);
            }
        }
        foreach($this->required_fields as $field){
            if ($recursive || isset($this->sql_field_alias[$field])) continue;
            $alias = '_rq'.++$this->rqi;
            $this->sql_select_fields[] = $field.' AS '.$alias;
            $this->sql_field_alias[$field] = $alias;
            $this->remove_from_result[] = $alias;
        }
    }

    function parse_mql_where($mql_where){
        if (count($this->where)){
            $where = '('.implode(')AND(', $this->where).')';
            if ($mql_where != ''){
                $mql_where = $where.' AND('.$mql_where.')';
            }else{
                $mql_where = $where;
            }
        }
        $where = $this->mql_where->parse($mql_where, $this->object, $this->ta);
        if (count($this->where_no_parse)){
            $where_np = '('.implode(')AND(', $this->where_no_parse).')';
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
        $this->has_group_by = true;
        return implode(',', $sql_fields);
    }

    function parse_mql_having($mql_having){
        return $this->mql_where->parse($mql_having, $this->object, $this->ta);
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
        if (!$this->has_group_by){
            $sql_order = array_merge($sql_order, $this->order_by);
            if (count($sql_order) == 0){
                $this->convert_order_by($sql_order, $this->object->_order_by);
            }
        }
        return implode(',', $sql_order);
    }

    function parse_mql_limit($mql_limit){
        return $mql_limit;
    }

    function split_field_param($field_name){
        return specialsplitparam($field_name);
    }

    function field2sql($field_name, $obj = null, $ta = null, $field_alias = '', &$operator='', $op_data='', $is_where=false){
        if ($obj === null) $obj = $this->object;
        if ($this->debug > 1) {
            zyfra_debug::show_msg('Field2sql['.$obj->_name.'->'.$field_name.']');
        }
        if (is_numeric($field_name) || $field_name == ',' || $field_name == ' ') return $field_name;
        if ($ta === null) $ta = $this->table_alias[''];
        $fx_regex = '/^([a-z_]+)\((.*)\)$/';
        if (preg_match($fx_regex, $field_name, $matches)){
            return $matches[1].'('.$this->parse_mql_fields($matches[2], true).')';
        }
        $fields = specialsplit($field_name, '.');
        $field = array_shift($fields);
        list($field_name, $field_data) = specialsplitparam($field);
        if (!array_key_exists($field_name, $obj->_columns)) return $field_name;
        $field_obj = &$obj->_columns[$field_name];
        
        $context = array('parameter'=>$field_data, 'field_alias'=>$field_alias, 'is_where'=>$is_where);
        if ($field_obj->handle_operator) {
            $context['operator'] = $operator;
            $context['op_data'] = $op_data;
            $operator = null;
        }
        $context = array_merge($this->context, $context);
        return $field_obj->get_sql($ta, $fields, $this, $context);
    }

    function add_sub_query($robject, $rfield, $sub_mql, $field_alias, $parameter){
        $this->sub_queries[] = array($robject, $rfield, $sub_mql, $field_alias, $parameter);
    }
    
    function add_required_fields($required_fields){
        $this->required_fields = array_unique(array_merge($this->required_fields, $required_fields));
    }
}
?>