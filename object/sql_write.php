<?php
namespace zyfra\orm;
require_once(dirname(__FILE__).'/../debug.php');

class SQLWrite extends OM_SQLinterface{
    function __construct($object, $values, $where, $where_datas, $context){
        parent::__construct($object, $context);
        $this->result = $this->do_query($object, $values, $where, $where_datas, $context);
    }

    function do_query($object, $values, $where, $where_datas, $context){
        if (!is_null($object->_write_date) && !array_key_exists($object->_write_date, $values)) $values[$object->_write_date] = gmdate('Y-m-d H:i:s');
        $user_id = array_get($context, 'user_id', $object->_pool->_default_user_id);
        if (!is_null($object->_write_user_id)) $values[$object->_write_user_id] = $user_id;
        $this->values = $values;
        $this->col_assign = [];
        $this->col_assign_data = [];
        $old_values = [];
        $db = $object->_pool->db;
        $sql = 'SELECT '.$object->_key.' FROM '.$object->_table.' WHERE '.$where;
        if ($this->debug){
            \zyfra_debug::print_set('WRITE SQL: Model['.$this->object->_name.']', htmlentities($sql));
        }
        $this->ids = $db->get_array($sql, $object->_key, '', $where_datas);
        if (count($this->ids) == 0) return true;
        foreach($values as $column=>$value){
            $fields = specialsplit($column, '.');
            $field = array_shift($fields);
            list($field_name, $field_data) = specialsplitparam($field);
            $ctx = $context; //copy
            $ctx['parameter'] = $field_data;
            if (array_key_exists($field_name, $object->__before_write_fields)){
                //Todo
            }
            if (array_key_exists($field_name, $object->__after_write_fields)){
                $where_mql = ['WHERE ('.$where.')AND('.$field_name.'!=%s)',[$values[$field_name]]];
                $old_values[$field_name] = $object->get_scalar_array($field_name, $object->_key, $where_mql, $ctx);
            }
            if (array_key_exists($field_name, $object->_columns)){
                $object->_columns[$field_name]->sql_write($this, $value, $fields, $ctx);
            }else{
                $this->col_assign[] = $field_name.'=%s';
                $this->col_assign_data[] = $value;
            }
        }
        if (count($this->col_assign) == 0) return true;
        $sql = 'UPDATE '.$object->_table.' AS t0 SET '.implode(',', $this->col_assign).' WHERE '.$where;
        $sql = $db->safe_sql($sql, array_merge($this->col_assign_data, $where_datas));
        if ($this->debug){
            \zyfra_debug::print_set('WRITE SQL: Model['.$this->object->_name.']', htmlentities($sql));
        }

        if ($this->dry_run){
            return true;
        }
        $r = $db->query($sql);
        foreach($old_values as $col_name=>$old_value){
            $object->_columns[$col_name]->after_write_trigger($old_value, $values[$col_name]);
        }
        return $r!==false;
    }

    function add_assign($assign){
        $this->col_assign[] = $assign;
    }

    function add_data($data){
        $this->col_assign_data[] = $data;
    }
}
