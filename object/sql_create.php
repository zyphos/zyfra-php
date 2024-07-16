<?php
namespace zyfra\orm;

require_once('tools.php');
require_once('sql_interface.php');

class OM_SQLcreate extends OM_SQLinterface{
    function create($values){
        $obj = &$this->object;
        //Check required fields
        $required_lacking = [];
        foreach($obj->_columns as $col_name=>$column){
            if ($column->required && !isset($values[$col_name])){
                $required_lacking[] = $col_name;
            }
        }
        if (count($required_lacking)){
            throw new \Exception('Fields: '.implode(', ', $required_lacking).' are required for creation in object['.$obj->_name.']');
        }
        $this->debug = array_get($this->context, 'debug', false);
        $user_id = array_get($this->context, 'user_id', $obj->_pool->_default_user_id);
        if (!is_null($obj->_create_user_id)) $values[$obj->_create_user_id] = $user_id;
        if (!is_null($obj->_write_user_id)) $values[$obj->_write_user_id] = $user_id;
        $treated_columns = [];
        $sql_values = [];
        // Parse all values and fieldname
        foreach($values as $col_name=>$value){
            $fields = specialsplit($col_name, '.');
            $field = array_shift($fields);
            list($field_name, $field_data) = specialsplitparam($field);
            $ctx = $this->context; // Copy context
            $ctx['parameter'] = $field_data;
            if (!isset($obj->_columns[$field_name])){
                throw new \Exception('Column ['.$field_name.'] does not exist in object['.$obj->_name.']');
            }
            $col_obj = $obj->_columns[$field_name];
            if (!is_object($col_obj)){
                throw new \Exception('Column ['.$field_name.'] does not exist in object['.$obj->_name.']');
            }
            $sql_value = $col_obj->sql_create($this, $value, $fields, $ctx);
            if ($sql_value instanceof \zyfra\orm\Callback){
                $this->add_callback($col_obj, $sql_value->function_name, [$this, $value, $fields, $ctx]);
                $sql_value = $sql_value->return_value;
            }
            if($col_obj->is_stored($ctx)) $sql_values[$field_name] = $sql_value;
            $treated_columns[] = $field;
        }

        // Add datetimes
        $date = gmdate("'Y-m-d H:i:s'");
        if (!is_null($obj->_create_date) && !in_array($obj->_create_date, $treated_columns)){
            $sql_values[$obj->_create_date] = $date;
            $treated_columns[] = $obj->_create_date;
        }
        if (!is_null($obj->_write_date) && !in_array($obj->_write_date, $treated_columns)){
            $sql_values[$obj->_write_date] = $date;
            $treated_columns[] = $obj->_write_date;
        }

        // Do default values
        $not_stored_default_values = [];
        foreach($obj->_columns as $field_name=>$column){
            if (in_array($field_name, $treated_columns)) continue;
            $default_value = $column->get_default();
            if (!is_null($default_value)){
                if ($column->stored){
                    $sql_values[$field_name] = $default_value;
                }else{
                    $not_stored_default_values[$field_name] = $default_value;
                }
            }
        }

        // Do the insert SQL
        $sql = 'INSERT INTO '.$obj->_table.' ('.implode(',', array_keys($sql_values)).') VALUES ('.implode(',',$sql_values).')';
        if ($this->debug){
            \zyfra_debug::print_set('CREATE:', $sql);
        }

        if ($this->dry_run){
            return null;
        }

        $res = $obj->_pool->db->query($sql);
        if ($res === false){
            throw new \Exception('Insert error: '.$sql.' - '.$obj->_pool->db->get_error());
        }
        $id = $obj->_pool->db->insert_id();

        if (count($not_stored_default_values)){
            $context = $this->context; // copy context
            $obj->write($not_stored_default_values, $id, $context);
        }

        // Treat all callback and after write
        $context = $this->context; // copy context

        foreach($this->callbacks as $callback){
            list($function_def, $params) = $callback;
            $params[] = $id;
            call_user_func_array($function_def, $params);
        }

        // TODO: check code below
        foreach($obj->__after_create_fields as $column=>$none){
            if (array_key_exists($column, $values)) {
                $value = $values[$column];
            }else{
                $value = $obj->_columns[$column]->get_default();
            }

            $obj->_columns[$column]->after_create_trigger($this, $id, $value, $context);
        }
        return $id;
    }
}
