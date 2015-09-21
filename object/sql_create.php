<?
require_once('tools.php');
require_once('sql_interface.php');

class OM_SQLcreate extends OM_SQLinterface{
    function create($values_array, $require_ids){
        $obj = $this->object;
        $columns = array();
        $sql_columns = array();
        foreach(array_keys(current($values_array)) as $col_name){
            $fields = specialsplit($col_name, '.');
            $field = array_shift($fields);
            list($field_name, $field_data) = specialsplitparam($field);
            $ctx = $this->context; // Copy context
            $ctx['parameter'] = $field_data;
            $col_obj = $obj->_columns[$field_name];
            if (!is_object($col_obj)){
                throw new Exception('Column '.$field_name.' do not exists in object['.$obj->name.']');
            }
            if($col_obj->stored) $sql_columns[] = $field_name;
            $columns[] = array($col_obj, $field_name, $ctx, $fields);
        }
        $added_time = array();
        if (!in_array($obj->_create_date, $sql_columns)){
        	$sql_columns[] = $obj->_create_date;
        	$added_time[] = $obj->_create_date;
        }
        if (!in_array($obj->_write_date, $sql_columns)) {
        	$sql_columns[] = $obj->_write_date;
        	$added_time[] = $obj->_write_date;
        }
        $date = gmdate("'Y-m-d H:i:s'");
        $sql_values = array();
        foreach($values_array as $values){
            $sql_values_array = array(array());
            foreach($columns as $col){
                list($col_obj, $field_name, $ctx, $fields) = $col;
                $value = $values[$field_name];
                if($col_obj->stored){
                    if(!is_array($value)) $value = array($value);
                    foreach($value as $val){
                        $new_value = $col_obj->sql_create($this, $val, $fields, $ctx);
                        /*if ($new_value == null) {
                        	$column2del[] = $field_name;
                        	continue;
                        }*/
                        $new_sql_value_array = array();
                        foreach($sql_values_array as &$row){
                            $new_row = $row; //copy
                            $new_row[] = $new_value;
                            $new_sql_value_array[] = $new_row;
                        }
                        $sql_values_array = $new_sql_value_array;
                    }
                }else{
                    $col_obj->sql_create($this, $value, $fields, $ctx);
                }
            }
            foreach($sql_values_array as $row){
                if (in_array($obj->_create_date, $added_time)) $row[] = $date;
                if (in_array($obj->_write_date, $added_time)) $row[] = $date;
                $sql_values[] = '('.implode(',', $row).')';
            }
        }
        unset($sql_values_array);
        
        if ($require_ids){
            $ids = array();
            foreach($sql_values as $sql_vals){
                $sql = 'INSERT INTO '.$obj->_table.' ('.implode(',', $sql_columns).') VALUES '.$sql_vals;
                //throw new Exception($sql);
                //echo $sql.'<br>';
                $res = $obj->_pool->db->query($sql);
                if ($res === false){
                throw new Exception('Insert error: '.$sql.' - '.mysql_error());
                }
                $id = $obj->_pool->db->insert_id();
                $ids[] = $id;
            }
        }else{
            $sql = 'INSERT INTO '.$obj->_table.' ('.implode(',', $sql_columns).') VALUES '.implode(',',$sql_values);
            //throw new Exception($sql);
            //echo $sql.'<br>';
            $res = $obj->_pool->db->query($sql);
            if ($res === false){
            throw new Exception('Insert error: '.$sql.' - '.mysql_error());
            }
            $id = $obj->_pool->db->insert_id();
        }
        
        

        // $db->safe safe_var
        $context = $this->context; // copy context
        
        foreach($this->callbacks as $callback){
            call_user_func($callback, $this, $values[$col_name], $id, $context);
        }
        foreach($obj->__after_create_fields as $column=>$none){
            if (array_key_exists($column, $values)) {
                $value = $values[$column];
            }else{
                $value = $obj->_columns[$column]->default_value;
            }
            $obj->_columns[$column]->after_create_trigger($id, $value, $context);
        }
        if ($require_ids){
            return $ids;
        }
        return $id;
    }
}
?>