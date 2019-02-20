<?php
class IntField extends Field{ // 4 Bytes
    var $unsigned = false;
    var $auto_increment = false;
    var $display_width = 11; // Display width
    var $default_value=null;
    var $widget='integer';
    
    function sql2php($value){
        if (!is_numeric($value)) throw new UnexpectedValueException('Integer value is expected.');
    	return (int)$value;
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        if (!is_numeric($value)) throw new UnexpectedValueException('Integer value is expected.');
        return (int)$value;
    }

    function get_sql_def(){
        return 'INT('.$this->display_width.')'.($this->unsigned?' UNSIGNED':'');
    }

    function get_sql_def_flags($update=false){
        return ($this->auto_increment?' AUTO_INCREMENT':'').parent::get_sql_def_flags($update);
    }

    function get_sql_extra(){
        return ($this->auto_increment?'auto_increment':'');
    }
}

class TinyIntField extends IntField{ // 1 byte
    var $display_width = 3;
    
    function get_sql_def(){
        return 'TINYINT('.$this->display_width.')'.($this->unsigned?' UNSIGNED':'');
    }
}

class SmallIntField extends IntField{ // 2 bytes
    var $display_width = 6;

    function get_sql_def(){
        return 'SMALLINT('.$this->display_width.')'.($this->unsigned?' UNSIGNED':'');
    }
}

class MediumIntField extends IntField{ // 3 bytes
    var $display_width = 8;

    function get_sql_def(){
        return 'MEDIUMINT('.$this->display_width.')'.($this->unsigned?' UNSIGNED':'');
    }
}

class BigIntField extends IntField{ // 8 bytes
    var $display_width = 20;

    function get_sql_def(){
        return 'BIGINT('.$this->display_width.')'.($this->unsigned?' UNSIGNED':'');
    }
}

class FloatField extends Field{
    var $default_value=0;
    var $widget='float';
    
    function sql2php($value){
        if (!is_numeric($value)) throw new UnexpectedValueException('Float value is expected.');
    	return (float)$value;
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        return (float)$value;
    }
    function get_sql_def(){
        return 'FLOAT';
    }
}

class DoubleField extends Field{
    var $default_value=0;
    var $widget='double';
    
    function sql2php($value){
        if (is_null($value)) return $this->_sql_format_null();
        if (!is_numeric($value)) throw new UnexpectedValueException('Double value is expected.');
    	return (double)$value;
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        return (double)$value;
    }
    function get_sql_def(){
        return 'DOUBLE';
    }
}

class DecimalField extends Field{
    var $default_value=0;
    var $widget='decimal';

    function sql2php($value){
        if (is_null($value)) return $this->_sql_format_null();
        if (!is_numeric($value)) throw new UnexpectedValueException('Decimal value is expected.');
        return (double)$value;
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        return (double)$value;
    }

    function get_sql_def(){
        return 'DECIMAL';
    }
}

class BooleanField extends Field{
    var $default_value=0;
    var $widget='boolean';
    
    function sql2php($value){
    	return $value?true:false;
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        return $value?1:0;
    }

    function get_sql_def(){
        return 'TINYINT(1)';
    }
}

class IntSelectField extends Field{
    var $select_values;
    var $widget='intselect';
    var $unsigned = false;
    var $size = 11;

    function __construct($label, $select_values = null, $args = null){
        if (is_array($select_values)) {
            $this->select_values = $select_values;
        }else{
            $this->select_values = array($select_values);
        }
        parent::__construct($label, $args = null);
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (count($fields) && $fields[0] == 'value'){ // return value when using field_name.value
            $parent_alias->set_used();
            $whens = [];
            foreach($this->select_values as $key=>$value){
                $whens[] = 'WHEN '.$key." THEN '".$value."'";
            }

            $sql = '(CASE '.$parent_alias->alias.'.'.$this->name.' '.implode(' ', $whens)." ELSE '' END)";
            return $this->add_operator($sql, $context);
        }
        return parent::get_sql($parent_alias, $fields, $sql_query, $context);
    }

    function sql_format($value){
        if (is_null($value)) return $this->_sql_format_null();
        if(is_string($value)){
            $key = array_search($value, $this->select_values);
            if($key !== false) return $key;
        }
        if (is_numeric($value)) return (int)$value;
        return null;
    }
    
    function sql2php($value){
        return $this->select_values[$value];
    }
    
    function get_sql_def(){
        return 'INT('.$this->size.')'.($this->unsigned?' UNSIGNED':'');
    }
}
