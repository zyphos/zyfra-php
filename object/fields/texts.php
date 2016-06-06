<?php
class TextField extends Field{
    var $widget='text';
    var $translate=false;
    
    public function is_stored(&$context){
        return !$this->translate || !strlen(array_get($context, 'parameter'));
    }
    
    protected function get_language_id(&$context){
        $language_id = array_get($context, 'language_id');
        $parameter = array_get($context, 'parameter');
        $pool = &$this->object->_pool;
        $object_tr = $pool->{$pool->get_language_object_name()};
        if (is_numeric($parameter)){
            $language_id = (int)$parameter;
        }elseif($parameter){
            $language_ids = $object_tr->name_search($parameter, $context);
            if (count($language_ids) == 1) $language_id = $language_ids[0];
        }
        return $language_id;
    }

    function sql_create($sql_create, $value, $fields, $context){
        if (!$this->translate){
            return parent::sql_create($sql_create, $value, $fields, $context);
        }
        
        $language_id = $this->get_language_id($context);
        
        if (!$this->translate || !$language_id){
            return parent::sql_create($sql_create, $value, $fields, $context);
        }else{
            return new zyfra\orm\Callback('sql_create_after_trigger', null);
            //$sql_create->add_callback($this, 'sql_create_after_trigger', $value, $fields, $context);
        }
        return null;
    }

    function sql_create_after_trigger($sql_create, $value, $fields, $context, $id){
        $language_id = $this->get_language_id($context);
        $t = $this->translate;
        $tr_obj = $this->object->_pool->{$t['object']};
        $create = array($t['column']=>$value, $t['key']=>$id, $t['language_id']=>$language_id);
        $tr_obj->create($create, $context);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if ($this->read_only) return;
        if (!$this->translate){
            return parent::sql_write($sql_write, $value, $fields, $context);
        }
        $language_id = $this->get_language_id($context);
        
        if (!$language_id){
            return parent::sql_write($sql_write, $value, $fields, $context);
        }
        
        $t = &$this->translate;
        $object_tr = $this->object->_pool->{$t['object']};
        
        //'column'=>$name, 'key'=>'source_id', 'language_id'=>'language_id'
        $where = $t['key'].' IN %s AND '.$t['language_id'].'=%s';
        $where_values = array($sql_write->ids, $language_id);
        if ($value == null || $value == ''){
            $object_tr->unlink($where, $where_values);
            return;
        }
        $sql = $t['key'].' AS oid,'.$object_tr->_key.' AS id,'.$t['column'].' AS tr WHERE '.$where;
        $rows = $object_tr->select($sql, array_merge($sql_write->context, array('key'=>'oid')), $where_values);
        $row2add = array();
        $row2update = array();
        foreach($sql_write->ids as $id){
            if(!array_key_exists($id, $rows)){
                $row2add[] = $id;

            }elseif($rows[$id]->tr != $value){
                $row2update[] = $rows[$id]->id;
            }
        }
        foreach($row2add as $id){
            $create = array($t['column']=>$value, $t['key']=>$id, $t['language_id']=>$language_id);
            $object_tr->create($create, $sql_write->context);
        }
        if (count($row2update) == 0) return;
        $where = $object_tr->_key.' IN %s AND '.$t['language_id'].'=%s';
        $object_tr->write(array($t['column']=>$value), $where, array($row2update, $language_id), $sql_write->context);
    }

    function __get_translate_col_instance(){
        $col_type = get_class($this);
        if ($col_type=='CharField'){
            return new $col_type($this->label, $this->size);
        }else{
            return new $col_type($this->label);
        }
    }

    function set_instance($object, $name){
        parent::set_instance($object, $name);
        if ($this->translate === false) return;
        $pool = $object->_pool;
        if ($this->translate === true){
            $tr_name = $object->_name.'_tr';
            if ($pool->object_in_pool($tr_name)){
                //Add field
                $pool->{$tr_name}->add_column($name, $this->__get_translate_col_instance());
            }else{
                $language_object_name = $pool->get_language_object_name();
                try{
                     $pool->{$language_object_name};
                }catch (Exception $e) {}
                
                if (!$pool->object_in_pool($language_object_name)){
                    $lg_obj = new ObjectModel($pool, array(
                            '_name'=>$language_object_name,
                        '_columns'=>array('name'=>new CharField('Name', 30))));
                    $pool->add_object($language_object_name, $lg_obj);
                }
                $tr_obj = new ObjectModel($pool, array(
                            '_name'=>$tr_name,
                      '_columns'=>array(
                        'language_id'=>new Many2OneField('Language', $language_object_name), 
                        'source_id'=>new Many2OneField('Source row id', $object->_name),
                $name=>$this->__get_translate_col_instance())));

                $pool->add_object($tr_name, $tr_obj);
            }
            $this->translate = array('object'=>$tr_name, 'column'=>$name, 'key'=>'source_id', 'language_id'=>'language_id', 'local_key'=>'');
        }
        if (!array_key_exists('_translation', $object)){
            $args = array();
            if (isset($this->translate['local_key'])) $args['local_key'] = $this->translate['local_key'];
            $object->add_column('_translation', new One2ManyField('Translation', $this->translate['object'], $this->translate['key'], $args));
        }
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (isset($context['operator'])){
            $new_context = $context; // Copy array
            unset($new_context['operator']);
        }else{
            $new_context = &$context;
        }
        $this_sql = parent::get_sql($parent_alias, $fields, $sql_query, $new_context);
        if (!$this->translate){
            return $this->add_operator($this_sql, $context);
        }
        if (array_key_exists('parameter', $context) && $context['parameter'] != ''){
            $language_id = (int)$context['parameter'];
        }else{
            $language_id = array_get($sql_query->context, 'language_id');
        }
        if (!$language_id) return $this->add_operator($this_sql, $context);
        $context = array('parameter'=>$this->translate['language_id'].'='.$language_id);
        $fields = array($this->translate['column']);
        $tr_sql = $this->object->_columns['_translation']->get_sql($parent_alias, $fields, $sql_query, $context);
        return $this->add_operator('coalesce('.$tr_sql.','.$this_sql.')', $context);
    }

    function get_sql_def(){
        return 'TEXT';
    }
}

class CharField extends TextField{
    var $widget='char';
    var $size;

    function __construct($label, $size, $args = array()){
        parent::__construct($label, $args);
        $this->size = $size;
    }

    function get_sql_def(){
        return 'VARCHAR('.$this->size.')';
    }
}

class TinytextField extends TextField{
    function get_sql_def(){
        return 'TINYTEXT';
    }
}
?>