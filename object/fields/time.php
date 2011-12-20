<?
class DatetimeField extends Field{
    var $widget='datetime';
    function sql_format($value){
        if (is_int($value)){
            return "'".gmdate('Y-m-d H:i:s', $value)."'";
        }
        return "'".$value."'";
    }

    function get_sql_def(){
        return 'DATETIME';
    }
}
?>