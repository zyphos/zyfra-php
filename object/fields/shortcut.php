<?php
class ShortcutField extends Field{
    // ShortcutField('Label', 'field.field.field');
    var $stored=false;
    var $select_all=false;
    var $relation;

    function __construct($label, $relation, $args = null){
        parent::__construct($label, $args);
        $this->relation = $relation;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (count($fields)){
            $new_fieldname = $this->relation.'.'.implode('.', $fields);
        }else{
            $new_fieldname = $this->relation;
        }
        $res = $sql_query->field2sql($new_fieldname, $this->object, $parent_alias, $context['field_alias']);
        return $res;
    }
}
?>