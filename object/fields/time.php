<?php
class DatetimeField extends Field{
    var $widget='datetime';
    function sql_format($value){
        if (is_int($value)){
            return "'".gmdate('Y-m-d H:i:s', $value)."'";
        }
        return parent::sql_format($value);
    }

    function get_sql_def(){
        return 'DATETIME';
    }
}

class DateField extends Field{
    var $widget = 'date';

    function sql_format($value){
        if (is_int($value)){
            return "'".gmdate('Y-m-d', $value)."'";
        }
        return parent::sql_format($value);
    }

    function get_sql_def(){
        return 'DATE';
    }
}
