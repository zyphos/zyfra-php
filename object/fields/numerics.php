<?php
class IntField extends Field{
    var $unsigned = false;
    var $auto_increment = false;
    var $size = 11;
    var $default_value=null;
    var $type='integer';

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
    var $type='float';

    function sql_format($value){
        return (float)$value;
    }
    function get_sql_def(){
        return 'FLOAT';
    }
}

class DoubleField extends Field{
    var $default_value=0;
    var $type='double';

    function sql_format($value){
        return (double)$value;
    }
    function get_sql_def(){
        return 'DOUBLE';
    }
}

class BooleanField extends Field{
    var $default_value=0;
    var $type='boolean';

    function sql_format($value){
        return $value?1:0;
    }
    function get_sql_def(){
        return 'INT(1)';
    }
}
?>