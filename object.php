<?php
/*****************************************************************************
 *
*         Object class
*         ---------------
*
*         ORM
*
*    Copyright (C) 2011 De Smet Nicolas (<http://ndesmet.be>).
*    All Rights Reserved
*
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*****************************************************************************/

/*
 * Usage:
require_once('ZyfraPHP/object.php');

class idea_type extends ObjectModel{
function init(){
$this->name = new CharField('Name', 20);
}
}

class idea extends ObjectModel{
function init(){
$this->name = new CharField('Name', 20, ['translate'=>true]);
$this->type = new Many2OneField('Name', 'idea_type');
$this->meta = new MetaField('Meta');
}
}

$pool = Pool::get();

//Create a new idea
$pool->idea->create(['name'=>'My Idea']);
// Or with active record
$new_idea = $pool->idea->active_record();
$new_idea->name = 'My Idea';
$new_idea->save();

//Retrieve an idea
$ideas = $new_idea->select();
//This is equal to
$ideas = $new_idea->select('*');
//This is equal to
$ideas = $new_idea->select('name');


* In order to use the autoload object when needed
class MyPool extends Pool {
protected function get_include_object_php($object_name){
return dirname(__FILE__).'/objects/'.$object_name.'.php';
}
}
$pool = MyPool::get();
*
*/

require_once('db.php');
require_once('object/fields.php');
require_once('object/sql_query.php');
require_once('object/sql_create.php');
require_once('object/sql_write.php');
require_once('object/pool.php');
require_once('object/active_record.php');
require_once('object/tools.php');

global $mysql_reserved_keywords;
$mysql_reserved_keywords = [
    'accessible','add','all','alter','analyze','and','as','asc','asensitive',
    'before','between','bigint','binary','blob','both','by','call','cascade',
    'case','change','char','character','check','collate','column','condition',
    'constraint','continue','convert','create','cross','current_date',
    'current_role','current_time','current_timestamp','current_user','cursor',
    'database','databases','day_hour','day_microsecond','day_minute',
    'day_second','dec','decimal','declare','default','delayed','delete',
    'delete_domain_id','desc','describe','deterministic','distinct',
    'distinctrow','div','do_domain_ids','double','drop','dual','each','else',
    'elseif','enclosed','escaped','except','exists','exit','explain','false',
    'fetch','float','float4','float8','for','force','foreign','from','fulltext',
    'general','grant','group','having','high_priority','hour_microsecond',
    'hour_minute','hour_second','if','ignore','ignore_domain_ids',
    'ignore_server_ids','in','index','infile','inner','inout','insensitive',
    'insert','int','int1','int2','int3','int4','int8','integer','intersect',
    'interval','into','is','iterate','join','key','keys','kill','leading',
    'leave','left','like','limit','linear','lines','load','localtime',
    'localtimestamp','lock','long','longblob','longtext','loop','low_priority',
    'master_heartbeat_period','master_ssl_verify_server_cert','match',
    'maxvalue','mediumblob','mediumint','mediumtext','middleint',
    'minute_microsecond','minute_second','mod','modifies','natural','not',
    'no_write_to_binlog','null','numeric','offset','on','optimize','option',
    'optionally','or','order','out','outer','outfile','over','page_checksum',
    'parse_vcol_expr','partition','position','precision','primary','procedure',
    'purge','range','read','reads','read_write','real','recursive',
    'ref_system_id','references','regexp','release','rename','repeat','replace',
    'require','resignal','restrict','return','returning','revoke','right',
    'rlike','row_number','rows','schema','schemas','second_microsecond',
    'select','sensitive','separator','set','show','signal','slow','smallint',
    'spatial','specific','sql','sqlexception','sqlstate','sqlwarning',
    'sql_big_result','sql_calc_found_rows','sql_small_result','ssl','starting',
    'stats_auto_recalc','stats_persistent','stats_sample_pages','straight_join',
    'table','terminated','then','tinyblob','tinyint','tinytext','to','trailing',
    'trigger','true','undo','union','unique','unlock','unsigned','update',
    'usage','use','using','utc_date','utc_time','utc_timestamp','values',
    'varbinary','varchar','varcharacter','varying','when','where','while',
    'window','with','write','xor','year_month','zerofill'];

class ContextedObjectModel{
    protected $_object;
    protected $_pool;
    protected $_context;

    function __construct(&$object, &$pool=null, $context=null){
        $this->_object = &$object;
        if (is_null($pool)){
            $this->_pool = $object->_pool;
        }else{
            $this->_pool = &$pool;
        }
        $this->_context = $context;
    }

    protected function _get_context($context){
        return array_merge($this->_pool->_context, $this->_context, $context);
    }

    public function active_record(array $param, array $context = []){
        return $this->_object->active_record($param, $this->_get_context($context));
    }

    public function create(array $values, array $context = []){
        return $this->_object->create($values, $this->_get_context($context));
    }

    public function write(array $values, $where, array $context = []){
        return $this->_object->write($values, $where, $this->_get_context($context));
    }

    public function select($mql='*', array $context = []){
        return $this->_object->select($mql, $this->_get_context($context));
    }

    public function get_scalar_array($value_field, $key_field=null, $where = '', array $context = []){
        return $this->_object->get_scalar_array($value_field, $key_field, $where, $this->_get_context($context));
    }

    public function unlink($where, array $context = []){
        return $this->_object->unlink($where, $this->_get_context($context));
    }

    public function name_search($txt, array $context=[], $operator='='){
        return $this->_object->name_search($txt, $this->_get_context($context), $operator);
    }

    public function get_id_from_value($value, $context, $field_name=null){
        return $this->_object->get_id_from_value($value, $this->_get_context($context), $field_name);
    }

    public function __call($method, $args){
        return call_user_func_array([$this->_object, $method], $args);
    }

    public function __get($attribute){
        return $this->_object->$attribute;
    }

    public function __set($attribute, $value){
        $this->_object->$attribute = $value;
    }

    public function __invoke($context){
        return new ContextedObjectModel($this, $this->_pool, $context);
    }
}

class ObjectModel{
    public $_pool;
    public $_columns;
    public $_name;
    public $_table;
    public $_key = 'id';
    public $_order_by = null;
    public $_create_date = 'create_date';
    public $_write_date = 'write_date';
    public $_create_user_id = 'create_user_id';
    public $_write_user_id = 'write_user_id';
    public $_visible_field = 'visible';
    public $_visible_condition;
    public $_read_only = false;
    public $_instanciated = false;
    public $_form_view_fields = null;
    public $_tree_view_fields = null;
    public $_display_name_field = 'name'; // Used for widget display
    public $__before_create_fields;
    public $__after_create_fields;
    public $__before_write_fields;
    public $__after_write_fields;
    public $__before_unlink_fields;
    public $__after_unlink_fields;
    public $__update_sql_done = false;

    protected $_name_search_fieldname = 'name';

    function __construct(Pool $pool, array $args = null){
        if(is_array($args)){
            foreach($args as $key=>$value){
                if(property_exists($this, $key)) $this->{$key} = $value;
            }
        }
        $this->init();
        $this->_pool = $pool;
        if (!isset($this->_columns)) throw new Exception('Object needs _columns');
        $methods = ['before_create', 'after_create', 'before_write',
                'after_write', 'before_unlink', 'after_unlink'];
        foreach($methods as $method){
            $this->{'__'.$method.'_fields'} = [];
        }

        if (is_null($this->_order_by) || !strlen($this->_order_by)) $this->_order_by = $this->_key;

        if (!array_key_exists($this->_key, $this->_columns)){
            $key_col = new IntField('Id', ['primary_key'=>true, 'auto_increment'=>true]);
            $this->_columns = [$this->_key=>$key_col] + $this->_columns;
        }
        if (!is_null($this->_create_date) && !array_key_exists($this->_create_date, $this->_columns)){
            $this->_columns[$this->_create_date] = new DatetimeField('Created date');
        }
        if (!is_null($this->_write_date) && !array_key_exists($this->_write_date, $this->_columns)){
            $this->_columns[$this->_write_date] = new DatetimeField('Writed date');
        }
        if (!is_null($this->_create_user_id) && !array_key_exists($this->_create_user_id, $this->_columns)){
            $this->_columns[$this->_create_user_id] = new IntField('Create user', ['default_value'=>$this->_pool->_default_user_id]);
        }
        if (!is_null($this->_write_user_id) && !array_key_exists($this->_write_user_id, $this->_columns)){
            $this->_columns[$this->_write_user_id] = new IntField('Write user', ['default_value'=>$this->_pool->_default_user_id]);
        }

        if (!array_key_exists('_display_name', $this->_columns)){
            if (array_key_exists($this->_display_name_field, $this->_columns)){
                $this->_columns['_display_name'] = new ShortcutField('Display Name', $this->_display_name_field);
            }else{
                $this->_columns['_display_name'] = new ShortcutField('Display Name', $this->_key);
            }
        }
    }

    public function add_column($name,Field $col){
        $this->_columns[$name] = $col;
        $this->set_column_instance($name, $col);
    }

    protected function set_column_instance($name,Field &$col){
        if ($col->instanciated) return;
        $col->set_instance($this, $name);
        if ($name == $this->_visible_field && $this->_visible_condition == ''){
            $this->_visible_condition = $this->_visible_field.'=1';
        }
        $methods = ['before_create', 'after_create', 'before_write',
                'after_write', 'before_unlink', 'after_unlink'];
        foreach($methods as $method){
            if (method_exists($col, $method.'_trigger')){
                //echo '__'.$method.'_fields['.$name.']';
                $this->{'__'.$method.'_fields'}[$name] = True;
            }
        }
        foreach($col->needed_columns as $name=>&$col){
            $this->set_column_instance($name, $col);
            $this->_columns[$name] = $col;
        }
    }

    public function set_instance(){
        if ($this->_instanciated) return;
        $this->_instanciated = true;
        if (!isset($this->_name)) $this->_name = get_class($this);
        foreach($this->_columns as $name=>&$col){
            $this->set_column_instance($name, $col);
        }

        if (!isset($this->_table)) $this->_table = $this->_name;
        //if ($this->_pool->get_auto_create()) $this->update_sql_structure();
    }

    public function __set($name, $value){
        global $mysql_reserved_keywords;
        if ($value instanceof Field){
            if (in_array($name, $mysql_reserved_keywords)){
                throw new Exception('"'.$name.'" is a MySQL reserved keyword, can not be used as field name in model '.$this->_name);
            }
            $this->_columns[$name] = $value;
        }else{
            throw new Exception('Can not create dynamic property ['.$name.']');
        }
    }

    public function __get($name){
        return $this->_columns[$name];
    }

    public function init(){
        //Contains fields definitions
    }

    public function active_record($param, array $context = []){
        return new ActiveRecord($this, $param, $context);
    }

    public function update_sql_structure(){
        if ($this->_read_only) return null;
        if ($this->__update_sql_done) return;
        #1 Check if table exists
        $db = $this->_pool->db;
        if (!$db->get_object('SHOW TABLES like %s', [$this->_table])){
            //Does not exists
            $columns_def = [];
            foreach($this->_columns as $name=>$column){
                if (!$column->stored) continue;
                $columns_def[] = $name.' '.$column->get_sql_def().$column->get_sql_def_flags();
                if ($column->index) $columns_def[] = 'INDEX ('.$name.')';
            }
            $sql = 'CREATE TABLE '.$this->_table.' ('.implode(',', $columns_def).')';
            $db->query($sql);
            $this->after_table_creation();
        }else{
            $sql = 'SHOW COLUMNS FROM '.$this->_table;
            $fields = $db->get_array_object($sql, 'Field');
            $columns_def = [];
            foreach($this->_columns as $field_name=>$field){
                if (!$field->stored || $field->primary_key) continue;
                $sql_def = $field->get_sql_def();
                if(array_key_exists($field_name, $fields)){
                    $db_field  = &$fields[$field_name];
                    //Update ?
                    if (strtoupper($db_field->Type) != $sql_def || $db_field->Extra != $field->get_sql_extra()){
                        $columns_def[] = 'MODIFY '.$field_name.' '.$sql_def.$field->get_sql_def_flags(true);
                    }
                    if ($field->index && $db_field->Key != 'MUL'){
                        $columns_def[] = 'ADD INDEX ('.$field_name.')';
                    }
                }else{
                    //Create !
                    //Todo check for name change, (similar column)
                    $columns_def[] = 'ADD '.$field_name.' '.$sql_def.$field->get_sql_def_flags();
                    if ($field->index) $columns_def[] = 'ADD INDEX ('.$field_name.')';
                }
            }
            if (count($columns_def)){
                $sql = 'ALTER TABLE '.$this->_table.' '.implode(',',$columns_def);
                $db->query($sql);
            }
        }
        $this->__update_sql_done = true;
    }

    public function check_table_structure(){
        $db = $this->_pool->db;
        $error_msgs = [];
        if (!$db->get_object('SHOW TABLES like %s', [$this->_table])){
            $error_msgs[] = '['.$this->_table.'] does not exist.';
            return $error_msgs;
        }
        $sql = 'SHOW COLUMNS FROM '.$this->_table;
        $fields = $db->get_array_object($sql, 'Field');
        $checked_field_names = [];
        foreach($this->_columns as $field_name=>$field){
            $checked_field_names[] = $field_name;
            if (!$field->stored) continue;
            if(!array_key_exists($field_name, $fields)){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] does not exist in table';
                continue;
            }
            $db_field = $fields[$field_name];

            $sql_def = $field->get_sql_def();
            if (strtoupper($db_field->Type) != $sql_def){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] mismatch of DEFINITION DB['.strtoupper($db_field->Type).'] != ORM['.$sql_def.']';
            }
            if ($db_field->Extra != $field->get_sql_extra()){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] mismatch of EXTRA definition DB['.$db_field->Extra.'] != ORM['.$field->get_sql_extra().']';
            }

            $db_is_primary = $db_field->Key == 'PRI';
            if ($field->primary_key != $db_is_primary){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] mismatch of PRIMARY definition DB['.var_export($db_is_primary, true).'] != ORM['.var_export($field->primary_key, true).']';
            }

            $db_is_index = $db_field->Key == 'MUL';
            if ($field->index != $db_is_index){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] mismatch of INDEX definition DB['.var_export($db_is_index, true).'] != ORM['.var_export($field->index, true).']';
            }
        }
        foreach($fields as $field_name=>$field){
            if (!in_array($field_name, $checked_field_names)){
                $error_msgs[] = '['.$this->_table.'] Field ['.$field_name.'] is in DB but not in ORM.';
            }
        }
        return $error_msgs;
    }

    protected function after_table_creation(){
        //Can be overrided
    }

    protected function __add_default_values($values, $default = false){
        // Should be done by DB to remove in future
        foreach($this->_columns as $col_name=>$column){
            if (!array_key_exists($col_name, $values) && $default && $col_name != $this->_key){
                if (!is_null($column->default_value)){
                    $values[$col_name] = $column->default_value;
                }
            }
        }
        return $values;
    }

    public function create(array $values, array $context = []){
        /* Create new record(s)
         * $values = array (column: value, col2: value2);
        * or
        * $values = array[](column: value, col2: value2);
        */
        if ($this->_read_only || count($values) == 0) return null;
        if(is_int(key($values))){
            $ids = [];
            foreach($values as $values_data){
                $sql_create = new zyfra\orm\OM_SQLcreate($this, $context);
                $values_data = $this->__add_default_values($values_data, true);
                $ids[] = $sql_create->create($values_data);
            }
            return $ids;
        }else{
            $sql_create = new zyfra\orm\OM_SQLcreate($this, $context);
            $values = $this->__add_default_values($values, true);
            return $sql_create->create($values);
        }
    }

    protected function __parse_where($where){
        if (is_string($where)){
            if (is_numeric($where)) $where = $this->_key.'='.$where;
            $where_datas = [];
        }elseif (is_int($where)){
            $where = $this->_key.'='.$where;
            $where_datas = [];
        }elseif (is_array($where)){
            $nb = count($where);
            if ($nb==0) return [null, null];
            if (is_string($where[0])){
                $where_datas = &$where[1];
                $where = &$where[0];
            }else{
                $where = $this->_key.' in ('.implode(',', $where).')';
                $where_datas = [];
            }
        }else{
            throw new Exception('Unsupported where ['.$where.']');
        }
        return [$where, $where_datas];
    }

    public function write(array $values, $where, array $context = []){
        if ($this->_read_only) return null;
        list($where, $where_data) = $this->__parse_where($where);
        if (is_null($where)) return null;
        $sql_write = new zyfra\orm\SQLWrite($this, $values, $where, $where_data, $context);
        return $sql_write->result;
    }

    public function unlink($where, array $context = []){
        if ($this->_read_only) return null;
        list($where, $where_data) = $this->__parse_where($where);
        if (is_null($where)) return null;
        $columns_before = array_keys($this->__before_unlink_fields);
        $columns_after = array_keys($this->__after_unlink_fields);
        $columns = array_merge($columns_before, $columns_after);
        if (count($columns) > 0){
            $sql = 'SELECT '.$this->_key.', '.implode(',', $columns).' FROM '.$this->_table.' WHERE '.$where;
            $rows = $this->_pool->db->get_array_object($sql, '', $datas);
        }
        foreach($columns_before as $column){
            $old_values = [];
            foreach($rows as $row){
                $old_values[$row->{$this->_key}] = $row->{$column};
            }
            $this->_columns[$column]->before_unlink_trigger($old_values);
        }
        $sql = 'DELETE FROM '.$this->_table.' WHERE '.$where;

        if (isset($context['dry_run']) && $context['dry_run']){
            return $this->_pool->db->safe_sql($sql, $where_data);
        }

        $this->_pool->db->safe_query($sql, $where_data);
        foreach($columns_after as $column){
            $old_values = [];
            foreach($rows as $row){
                $old_values[$row->{$this->_key}] = $row->{$column};
            }
            $this->_columns[$column]->after_unlink_trigger($old_values);
        }
    }

    public function read($where='', array $fields=[], array $context = []){
        if (count($fields) == 0){
            $fields = array_keys($this->_columns);
        }
        if (is_string($where)){
            $datas = [];
        }elseif (is_array($where)){
            $datas = &$where[1];
            $where = &$where[0];
        }
        if (trim($where) != '') $where = ' WHERE '.$where;
        $mql = implode(',', $fields).$where;
        $res = $this->select([$mql, $datas], $context);
        foreach($res as &$row){
            foreach($row as $col_name=>&$value){
                if (isset($this->_columns[$col_name])) $value = $this->_columns[$col_name]->sql2php($value);
            }
        }
        return $res;
    }

    public function select($mql='*', array $context = []){
        $oquery = $this->_pool->add_query($this->_name, $mql);
        if (is_string($mql)){
            $datas = [];
        }elseif (is_array($mql)){
            $datas = &$mql[1];
            $mql = &$mql[0];
        }
        try{
        $mql = $this->_pool->db->safe_sql($mql, $datas);
        }catch(Exception $e){
            if(array_key_exists('debug', $context) && $context['debug']){
                throw $e;
            }
            return [];
        }
        $sql_query = new SqlQuery($this);
        $res = $sql_query->get_array($mql, $context);
        $oquery->stop();
        return $res;
    }

    public function get_scalar_array($value_field, $key_field=null, $where = '', array $context = []){
        list($where, $datas) = $this->__parse_where($where);
        if (is_null($where)) {
            $where = '';
            $datas = [];
        }
        try{
            $where = $this->_pool->db->safe_sql($where, $datas);
        }catch(Exception $e){
            if(array_key_exists('debug', $context) && $context['debug']){
                throw $e;
            }
            return [];
        }
        $sql_query = new SqlQuery($this);
        return $sql_query->get_scalar_array($value_field, $key_field, $where, $context);
    }

    public function get_view(array $fields_list = null){
        if (is_null($fields_list)) {
            $fields_list = array_keys($this->_columns);
            if(($key = array_search('_display_name', $fields_list)) !== false) {
                unset($fields_list[$key]);
            }
        }
        if (!in_array($this->_key, $fields_list)) array_unshift($fields_list, $this->_key);
        $view = [];
        foreach($fields_list as $name){
            if ($name == '_translation') continue;
            $column = $this->_columns[$name];
            $col = (object)['name'=>$name,
                    'label'=>$column->label,
                    'default_value'=>$column->default_value,
                    'widget'=>$column->widget,
                    'required'=>$column->required,
                    'help'=>$column->help,
                    'read_only'=>($name==$this->_key || $name==$this->_create_date || $name==$this->_write_date || $column->read_only),
                    'hidden'=>$column->hidden,
                    'is_key'=>($name==$this->_key)];
            if (isset($column->translate)){
                $col->translated = $column->translate && true; // transform it in boolean
            }
            if (isset($column->relation_object_name)){
                $col->relation_object_name = $column->relation_object_name;
            }
            if (isset($column->relation_object_field)){
                $col->relation_object_field = $column->relation_object_field;
            }
            if (isset($column->select_values)){
                $col->select_values = $column->select_values;
            }
            $view[] = $col;
        }
        return $view;
    }

    private function table2txt($table){
        //1. find max length of each column
        $max_lengths = [];
        foreach($table as $row){
            for($i = 0; $i<count($row);$i++){
                $len = strlen($row[$i]);
                if (!isset($max_lengths[$i])||$max_lengths[$i] < $len){
                    $max_lengths[$i] = $len;
                }
            }
        }

        //2. Format row
        $txt_table = [];
        foreach($table as $row){
            $row_txt = [];

            for($i = 0; $i<count($row);$i++){
                $row_txt[] = str_pad($row[$i], $max_lengths[$i]," ");
            }
            $txt_table[] = implode(' ', $row_txt);
        }

        //3. Join all row
        return implode("\n", $txt_table);
    }

    public function get_fields(){
        $fields = $this->get_view();
        $fields_txt = [];
        $table = [];
        foreach ($fields as $field){
            $row = [$field->name];

            $type = $field->widget;
            if (isset($field->relation_object_name)){
                if (isset($field->relation_object_field)){
                    $type .= '('.$field->relation_object_name.'.'.$field->relation_object_field.')';
                }else{
                    $type .= '('.$field->relation_object_name.')';
                }
            }
            $row[] = $type;
            $row[] = $field->default_value;
            $row[] = $field->label;
            $table[] = $row;
        }
        return $this->table2txt($table);
    }

    public function get_form_view(){
        return $this->get_view($this->_form_view_fields);
    }

    public function get_tree_view(){
        return $this->get_view($this->_tree_view_fields);
    }

    public function get_full_diagram($max_depth = 0, $lvl=0, $done=null){
        if (is_null($done)) {
            $done = [$this->_name];
        }else{
            if (in_array($this->_name, $done)){
                return str_repeat(' ', $lvl*2).'-recursive-'."\n";
            }else{
                $done[] = $this->_name;
            }
        }
        $txt = '';
        foreach($this->_columns as $col){
            $txt .= str_repeat(' ', $lvl*2).'+ '.$col->name.'['.get_class($col).']';
            if ($col->relational){
                $robj = $col->get_relation_object();
                $txt .= '['.$robj->_name."]\n";
                if ($max_depth == 0 || $lvl+1 < $max_depth)
                    $txt .= $robj->get_full_diagram($max_depth, $lvl + 1, $done);
            }else{
                $txt .= "\n";
            }
        }
        return $txt;
    }

    public function get_svg_full_diagram($max_depth=0){
        $dot_data = $this->get_dot_full_diagram($max_depth);
        $process = proc_open('dot -Tsvg',
                             [['pipe','r'],
                              ['pipe','w'],
                              ['pipe','r']],
                             $pipes);
        if(!is_resource($process)) throw new Exception('Can not call dot');
        fwrite($pipes[0], $dot_data);
        fclose($pipes[0]);
        $svg_data = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_value = proc_close($process);
        return $svg_data;
    }

    public function get_dot_full_diagram($max_depth=0, $lvl=0, &$done=null, &$relations=null, $parent=null, $column2skip=null){
        if ($relations==null) $relations = [];
        $name_under = str_replace('.','_', $this->_name);
        if ($done == null){
            $done = [$this->_name];
        }elseif (in_array($this->_name, $done)){
            return '';
        }else{
            $done[] = $this->_name;
        }
        if ($column2skip == null) $column2skip = [];
        $other_txt = '';
        $columns = [];
        foreach($this->_columns as $col){
            if (in_array($col->name, $column2skip)) continue;
            if ($col instanceof One2Many){
                $params = '('.$col->relation_object_name.','.col.relation_object_field.')';
            }elseif($col instanceof Relational){
                $params = '('.$col->relation_object_name.')';
            }else{
                $params = '';
            }
            $columns[] = '+ '.$col->name.'['.get_class($col).$params.']'.str_replace('>', '', $col->label);
            if ($col->relational){
                $robj = $col->get_relation_object();
                if ($max_depth == 0 || $lvl+1 < $max_depth || in_array($robj->_name, $done)){
                    $rname_under = str_replace('.','_', $robj->_name);
                    $relation_name = $name_under.' -> '.$rname_under.' [label="'.$col->name.'['.get_class($col).']",fontname="Bitstream Vera Sans",fontsize=8]';
                    if (!in_array($relation_name, $relations)) $relations[] = $relation_name;
                }

                if ($max_depth == 0 || $lvl+1 < $max_depth){
                    $other_txt .= $robj->get_dot_full_diagram($max_depth, $lvl + 1, $done, $relations, $name_under, $column2skip);
                }
            }
        }
        $txt = $name_under.' [label = "{'.$this->_name.'|'.implode('\l', $columns)."}\"]\n".$other_txt;
        if ($lvl == 0){
            //edge [dir="both"]
            $txt = 'digraph G {
             node [
                fontname = "Bitstream Vera Sans"
                fontsize = 8
                shape = "record"
            ]'."\n".$txt.implode("\n", $relations)."\n}";
        }
        return $txt;
    }

    public function name_search($txt, array $context=[], $operator='='){
        // Return ids corresponding to search on name
        $where = ['WHERE '.$this->_name_search_fieldname.' '.$operator.' %s', [$txt]];
        return $this->get_scalar_array($this->_key, null, $where, $context);
    }

    public function name_search_details($txt, array $context=[], $operator='='){
        // Return ids corresponding to search on name
        $where = [$this->_name_search_fieldname.' '.$operator.' %s', [$txt]];
        return $this->read($where, [$this->_key.' AS id', $this->_name_search_fieldname.' AS name'], $context);
    }

    public function __invoke($context){
        return new ContextedObjectModel($this, $this->_pool, $context);
    }

    public function get_id_from_value($value, $context, $field_name=null){
        if (is_null($field_name)) $field_name = $this->_key;
        try{
            return $this->_columns[$field_name]->sql2php($value);
        }catch(UnexpectedValueException $e){
        }
        // Try to search it
        $ids = $this->name_search($value, $context);
        if (count($ids) != 1){
            throw new UnexpectedValueException('Can not found match for this value. ['.$value.'] in ['.$this->_name.']');
        }
        return $ids[0];
    }

    public function does_id_exists($id){
        $id = (int)$id;
        $db = &$this->_pool->db;
        if (!$db->get_object('SELECT '.$this->_key.' FROM '.$this->_table.' WHERE '.$this->_key.'='.$id)){
            return false;
        }
        return true;
    }
}

require_once('object/objects/meta.php');
