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

    function active_record(){
        return new ActiveRecord($this);
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

        foreach($values2add as $values){
            $sql_create = new OM_SQLcreate($this, $context);
            $id = $sql_create->create($values);
        }
        return $id;
    }

    function write($values, $where, $where_datas = array(), $context = array()){
        if ($this->_read_only) return null;
        $sql_write = new SQLWrite($this, $values, $where, $where_datas, $context);
    }

    function unlink($where, $datas = array(), $context = array()){
        if ($this->_read_only) return null;
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

    function read($where='', $fields=array()){
        if (count($fields) == 0){
            $fields = array_keys($this->_columns);
        }
        $sql_query = new SqlQuery($this);
        if (trim($where) != '') $where .= ' WHERE'.$where;
        $mql = implode(',', $fields).$where.' ORDER BY '.$this->_order_by;
        return $this->select($mql);
    }

    function select($mql='*', $context = array(), $datas = array()){
        $mql = $this->_pool->db->safe_sql($mql, $datas);
        $sql_query = new SqlQuery($this);
        return $sql_query->get_array($mql, $context);
    }
    
    function get_form_view(){
        $view = array();
        foreach($this->_columns as $name=>$column){
            $col = array('name'=>$name, 'widget'=>$column->widget, 'required'=>$column->required);
            $view[] = $col;
        }
        return $view;
    }
    
    function get_tree_view(){
        return $this->get_form_view();        
    }
}

require_once('object/objects/meta.php');
?>