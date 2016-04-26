<?php
/*****************************************************************************
 *
*		 Object class
*		 ---------------
*
*		 ORM
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
$this->name = new CharField('Name', 20, array('translate'=>true));
$this->type = new Many2OneField('Name', 'idea_type');
$this->meta = new MetaField('Meta');
}
}

$pool = Pool::get();

//Create a new idea
$pool->idea->create(array('name'=>'My Idea'));
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

class ObjectModel{
    var $_columns;
    var $_name;
    var $_table;
    var $_key = 'id';
    var $_order_by;
    var $_create_date = 'create_date';
    var $_write_date = 'write_date';
    var $_visible_field = 'visible';
    var $_visible_condition;
    var $_read_only = false;
    var $_instanciated = false;
    var $_form_view_fields = null;
    var $_tree_view_fields = null;

    function __construct($pool, $args = null){
        if(is_array($args)){
            foreach($args as $key=>$value){
                if(property_exists($this, $key)) $this->{$key} = $value;
            }
        }
        $this->init();
        $this->_pool = $pool;
        if (!isset($this->_columns)) throw new Exception('Object needs _columns');
        $methods = array('before_create', 'after_create', 'before_write',
                'after_write', 'before_unlink', 'after_unlink');
        foreach($methods as $method){
            $this->{'__'.$method.'_fields'} = array();
        }

        if (!strlen($this->_order_by)) $this->_order_by = $this->_key;

        if (!array_key_exists($this->_key, $this->_columns)){
            $key_col = new IntField('Id', array('primary_key'=>true, 'auto_increment'=>true));
            $this->_columns = array($this->_key=>$key_col) + $this->_columns;
        }
        if (!array_key_exists($this->_create_date, $this->_columns)){
            $this->_columns[$this->_create_date] = new DatetimeField('Created date');
        }
        if (!array_key_exists($this->_write_date, $this->_columns)){
            $this->_columns[$this->_write_date] = new DatetimeField('Writed date');
        }
    }

    function add_column($name, &$col){
        $this->_columns[$name] = $col;
        $this->set_column_instance($name, $col);
    }

    function set_column_instance($name, &$col){
        if ($col->instanciated) return;
        $col->set_instance($this, $name);
        if ($name == $this->_visible_field && $this->_visible_condition == ''){
            $this->_visible_condition = $this->_visible_field.'=1';
        }
        $methods = array('before_create', 'after_create', 'before_write',
                'after_write', 'before_unlink', 'after_unlink');
        foreach($methods as $method){
            if (method_exists($col, $method.'_trigger')){
                $this->{'__'.$method.'_fields'}[$name] = True;
            }
        }
        foreach($col->needed_columns as $name=>&$col){
            $this->set_column_instance($name, $col);
            $this->_columns[$name] = $col;
        }
    }

    function set_instance(){
        if ($this->_instanciated) return;
        $this->_instanciated = true;
        if (!isset($this->_name)) $this->_name = get_class($this);
        foreach($this->_columns as $name=>&$col){
            $this->set_column_instance($name, $col);
        }

        if (!isset($this->_table)) $this->_table = $this->_name;
        if ($this->_pool->get_auto_create()) $this->update_sql();
    }

    function __set($name, $value){
        if ($value instanceof Field){
            $this->_columns[$name] = $value;
        }else{
            $this->{$name} = $value;
        }
    }

    function __get($name){
        return $this->_columns[$name];
    }

    function init(){
        //Contains fields definitions
    }

    function active_record($param = array(), $context = array()){
        return new ActiveRecord($this, $param, $context);
    }

    function update_sql(){
        if ($this->_read_only) return null;
        if (property_exists($this, '__update_sql_done')) return;
        #1 Check if table exists
        $db = $this->_pool->db;
        if (!$db->get_object('SHOW TABLES like %s', array($this->_table))){
            //Does not exists
            $columns_def = array();
            foreach($this->_columns as $name=>$column){
                if (!$column->stored) continue;
                $columns_def[] = $name.' '.$column->get_sql_def().$column->get_sql_def_flags();
            }
            $sql = 'CREATE TABLE '.$this->_table.' ('.implode(',', $columns_def).')';
            $db->query($sql);
        }else{
            $sql = 'SHOW COLUMNS FROM '.$this->_table;
            $fields = $db->get_array_object($sql, 'Field');
            $columns_def = array();
            foreach($this->_columns as $field_name=>$field){
                if (!$field->stored) continue;
                $sql_def = $field->get_sql_def();
                if(array_key_exists($field_name, $fields)){
                    //Update ?
                    if (strtoupper($fields[$field_name]->Type) != $sql_def || $fields[$field_name]->Extra != $field->get_sql_extra()){
                        $columns_def[] = 'MODIFY '.$field_name.' '.$sql_def.$field->get_sql_def_flags();
                    }
                }else{
                    //Create !
                    //Todo check for name change, (similar column)
                    $columns_def[] = 'ADD '.$field_name.' '.$sql_def.$field->get_sql_def_flags();
                }
            }
            if (count($columns_def)){
                $sql = 'ALTER TABLE '.$this->_table.' '.implode(',',$columns_def);
                $db->query($sql);
            }
        }
        $this->__update_sql_done = true;
    }

    function __add_default_values($values, $default = false){
        foreach($this->_columns as $col_name=>$column){
            if (!array_key_exists($col_name, $values) && $default && $col_name != $this->_key){
                if (!is_null($column->default_value)){
                    $values[$col_name] = $column->default_value;
                }
            }
        }
        return $values;
    }

    function create($values, $context = array()){
        /* Create new record(s)
         * $values = array (column: value, col2: value2);
        * or
        * $values = array[](column: value, col2: value2);
        */
        if ($this->_read_only || count($values) == 0) return null;
        $require_ids = isset($context['require_ids'])?$context['require_ids']:false;
        $values2add = array();
        if(is_int(key($values))){
            $multi_values=true;
            foreach($values as &$value){
                $value = $this->__add_default_values($value, true);
                $values2add[implode(',',array_keys($value))][] = $value;
            }
        }else{
            $multi_values=false;
            $values = $this->__add_default_values($values, true);
            $values2add[implode(',',array_keys($values))][] = $values;
        }
        if (count($values2add) == 0) return;
        
        $ids = array();
        foreach($values2add as $values){
            $sql_create = new OM_SQLcreate($this, $context);
            $id = $sql_create->create($values, $require_ids);
            if ($require_ids){
                $ids = array_merge($ids, $id);
            }
        }
        if ($require_ids) return $ids;
        return $id;
    }

    function write($values, $where, $where_datas = array(), $context = array()){
        if ($this->_read_only) return null;
        if (is_int($where)) $where = $this->_key.'='.$where;
        if (is_array($where)) $where = $this->_key.' in ('.implode(',', $where).')';
        $sql_write = new SQLWrite($this, $values, $where, $where_datas, $context);
        return $sql_write->result;
    }

    function unlink($where, $datas = array(), $context = array()){
        if ($this->_read_only) return null;
        if (is_int($where)) $where = $this->_key.'='.$where;
        if (is_array($where)) $where = $this->_key.' in ('.implode(',', $where).')';
        $columns_before = array_keys($this->__before_unlink_fields);
        $columns_after = array_keys($this->__after_unlink_fields);
        $columns = array_merge($columns_before, $columns_after);
        if (count($columns) > 0){
            $sql = 'SELECT '.$this->_key.', '.implode(',', $columns).' FROM '.$this->_table.' WHERE '.$where;
            $rows = $this->_pool->db->get_array_object($sql, '', $datas);
        }
        foreach($columns_before as $column){
            $old_values = array();
            foreach($rows as $row){
                $old_values[$row->{$this->_key}] = $row->{$column};
            }
            $this->_columns[$column]->before_unlink_trigger($old_values);
        }
        $sql = 'DELETE FROM '.$this->_table.' WHERE '.$where;
        $this->_pool->db->safe_query($sql, $datas);
        foreach($columns_after as $column){
            $old_values = array();
            foreach($rows as $row){
                $old_values[$row->{$this->_key}] = $row->{$column};
            }
            $this->_columns[$column]->after_unlink_trigger($old_values);
        }
    }

    function read($where='', array $fields=array()){
        if (count($fields) == 0){
            $fields = array_keys($this->_columns);
        }
        if (trim($where) != '') $where = ' WHERE '.$where;
        $mql = implode(',', $fields).$where;
        $res = $this->select($mql);
        foreach($res as &$row){
        	foreach($row as $col_name=>&$value){
        		if (isset($this->_columns[$col_name])) $value = $this->_columns[$col_name]->sql2php($value); 
        	}
        }
        return $res;
    }

    function select($mql='*', array $context = array(), array $datas = array()){
        try{
            $mql = $this->_pool->db->safe_sql($mql, $datas);
        }catch(Exception $e){
            if(in_array('debug', $datas) && $datas[debug]){
                throw $e;
            }
            return array();
        }
        $sql_query = new SqlQuery($this);
        return $sql_query->get_array($mql, $context);
    }
    
    function get_scalar_array($value_field, $key_field=null, $where = '', array $context = array(), array $datas = array()){
        try{
            $where = $this->_pool->db->safe_sql($where, $datas);
        }catch(Exception $e){
            if(in_array('debug', $datas) && $datas[debug]){
                throw $e;
            }
            return array();
        }
    	$sql_query = new SqlQuery($this);
    	return $sql_query->get_scalar_array($value_field, $key_field, $where, $context);
    }
    
    function get_view($fields_list = null){
    	if (is_null($fields_list)) $fields_list = array_keys($this->_columns);
    	$view = array();
    	foreach($fields_list as $name){
    		$column = $this->_columns[$name];
    		$col = array('name'=>$name,
    				'widget'=>$column->widget,
    				'required'=>$column->required,
    				'read_only'=>($name==$this->_key || $name==$this->_create_date || $name==$this->_write_date || $column->read_only),
    				'is_key'=>($name==$this->_key));
    		if (isset($column->relation_object_name)){
    			$col['relation_object_name'] = $column->relation_object_name;
    			$col['relation_object_key'] = $column->relation_object_key;
    		}
    		$view[] = $col;
    	}
    	return $view;
    }

    function get_form_view(){
    	return $this->get_view($this->_form_view_fields);
    }

    function get_tree_view(){
    	return $this->get_view($this->_tree_view_fields);
    }
    
    function get_full_diagram($max_depth = 0, $lvl=0, $done=null){
        if (is_null($done)) {
            $done = array($this->_name);
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
    
    function get_dot_full_diagram($max_depth=0, $lvl=0, &$done=null, &$relations=null, $parent=null, $column2skip=null){
        if ($relations==null) $relations = array();
        $name_under = str_replace('.','_', $this->_name);
        if ($done == null){
            $done = array($this->_name);
        }elseif (in_array($this->_name, $done)){
            return '';
        }else{
            $done[] = $this->_name;
        }
        if ($column2skip == null) $column2skip = array();
        $other_txt = '';
        $columns = array();
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
                    //$rrelation_name = $rname_under.' -> '.$name_under;
                    //if (!in_array($relation_name, $relations) && !in_array($rrelation_name, $relations)) $relations[] = $relation_name;
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

}

require_once('object/objects/meta.php');
?>