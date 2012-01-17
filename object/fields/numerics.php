<?php
class IntField extends Field{
    var $unsigned = false;
    var $auto_increment = false;
    var $size = 11;
    var $default_value=null;
    var $widget='integer';

    function sql_format($value){
        return (int)$value;
    }

    function get_sql_def(){
        return 'INT('.$this->size.')'.($this->unsigned?' UNSIGNED ':'');
    }

    function get_sql_def_flags(){
        return ($this->auto_increment?' AUTO_INCREMENT':'').parent::get_sql_def_flags();
    }

    function get_sql_extra(){
        return ($this->auto_increment?'auto_increment':'');
    }
}

class FloatField extends Field{
    var $default_value=0;
    var $widget='float';

    function sql_format($value){
        return (float)$value;
    }
    function get_sql_def(){
        return 'FLOAT';
    }
}

class DoubleField extends Field{
    var $default_value=0;
    var $widget='double';

    function sql_format($value){
        return (double)$value;
    }
    function get_sql_def(){
        return 'DOUBLE';
    }
}

class BooleanField extends Field{
    var $default_value=0;
    var $widget='boolean';

    function sql_format($value){
        return $value?1:0;
    }

    function get_sql_def(){
        return 'INT(1)';
    }
}

class IntSelectField extends Field{
    var $select_values;
    var $widget='intselect';

    function __construct($label, $select_values = null, $args = null){
        if (is_array($select_values)) {
            $this->select_values = $select_values;
        }else{
            $this->select_values = array($select_values);
        }
        parent::__construct($label, $args = null);
    }

    function sql_format($value){
        if(is_string($value)){
            $key = array_search($value, $this->select_values);
            if($key !== false) return $key;
        }
        if (is_numeric($value)) return (int)$value;
        return null;
    }
}
?>