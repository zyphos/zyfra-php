<?php
class ShortcutField extends Field{
    // ShortcutField('Label', 'field.field.field');
    var $stored=false;
    var $relation;

    function __construct($label, $relation, $args = null){
        parent::__construct($label, $args);
        $this->relation = $relation;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        return $sql_query->field2sql($this->relation, $this->object, $parent_alias, $context['field_alias']);
    }
}
?>