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

        if (is_numeric($parameter)){
            $language_id = (int)$parameter;
        }elseif($parameter){
            $pool = &$this->object->_pool;
            $object_tr = $pool->{$pool->get_language_object_name()};
            $language_ids = $object_tr->name_search($parameter, $context);
            if (count($language_ids) == 1) {
                $language_id = $language_ids[0];
                $context['parameter'] = $language_id; // Cache result
            }
        }
        return $language_id;
    }

    function sql_create($sql_create, $value, $fields, $context){
        if (!$this->translate){
            return parent::sql_create($sql_create, $value, $fields, $context);
        }

        $language_id = $this->get_language_id($context);

        if (!$language_id || $language_id == $this->object->_pool->_base_language_id){
            return parent::sql_create($sql_create, $value, $fields, $context);
        }
        return new zyfra\orm\Callback('sql_create_after_trigger', null);
    }

    function sql_create_after_trigger($sql_create, $value, $fields, $context, $id){
        $fake_sql_write = new stdClass;
        $fake_sql_write->ids = [$id];
        $this->sql_write($fake_sql_write, $value, $fields, $context);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if ($this->read_only) return;
        if (!$this->translate){
            return parent::sql_write($sql_write, $value, $fields, $context);
        }
        $language_id = $this->get_language_id($context);
        if (!$language_id || $language_id == $this->object->_pool->_base_language_id){
            return parent::sql_write($sql_write, $value, $fields, $context);
        }

        $t = &$this->translate;
        $object_tr = $this->object->_pool->{$t['object']};

        //'column'=>$name, 'key'=>'source_id', 'language_id'=>'language_id'
        $where = $t['key'].' IN %s AND '.$t['language_id'].'=%s';
        $where_values = [$sql_write->ids, $language_id];
        if ($value == null || $value == ''){
            // Check if other tr fields are null too
            $cols2check = [];
            foreach($object_tr->_columns as $col_name => $col){
                if ($col_name == $this->name || $col->sys || !$col->stored) continue;
                $cols2check[] = $col_name;
            }
            if (count($cols2check)){
                $r = $object_tr->select([implode(',',$cols2check).' WHERE '.$where,$where_values]);
                if (count($r)>0){
                    $to_unlink = true;
                    foreach($r[0] as $key=>$value){
                        if ($value != ''){
                            $to_unlink = false;
                            break;
                        }
                    }
                    if ($to_unlink){
                        $object_tr->unlink([$where, $where_values]);
                        return;
                    }
                }
            }
        }
        $sql = $t['key'].' AS oid,'.$object_tr->_key.' AS id,'.$t['column'].' AS tr WHERE '.$where;
        $new_context = array_merge($context, ['key'=>'oid']);
        $rows = $object_tr->select([$sql, $where_values], $new_context);
        $row2add = [];
        $row2update = [];
        foreach($sql_write->ids as $id){
            if(!array_key_exists($id, $rows)){
                $row2add[] = $id;

            }elseif($rows[$id]->tr != $value){
                $row2update[] = $rows[$id]->id;
            }
        }
        foreach($row2add as $id){
            $create = [$t['column']=>$value, $t['key']=>$id, $t['language_id']=>$language_id];
            $object_tr->create($create, $context);
        }
        if (count($row2update) == 0) return;
        $where = $object_tr->_key.' IN %s AND '.$t['language_id'].'=%s';
        $object_tr->write([$t['column']=>$value], [$where, [$row2update, $language_id]], $context);
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
                $model_class = $this->get_model_class();
                if (!$pool->object_in_pool($language_object_name)){
                    $name_field = new CharField('Name', 30);
                    $lg_obj = new $model_class($pool, [
                                                       '_name'=>$language_object_name,
                                                       '_columns'=>['name'=>$name_field]]);
                    $pool->add_object($language_object_name, $lg_obj);
                }
                $language_field = new Many2OneField('Language', $language_object_name);
                $source_field = new Many2OneField('Source row id', $object->_name);
                $tr_obj = new $model_class($pool, [
                            '_name'=>$tr_name,
                            '_columns'=>[
                                'language_id'=>$language_field,
                                'source_id'=>$source_field,
                                $name=>$this->__get_translate_col_instance()]
                            ]);

                $pool->add_object($tr_name, $tr_obj);
            }
            $this->translate = ['object'=>$tr_name, 'column'=>$name, 'key'=>'source_id', 'language_id'=>'language_id', 'local_key'=>''];
        }
        if (!property_exists($object, '_translation')){
            $args = [];
            if (isset($this->translate['local_key'])) $args['local_key'] = $this->translate['local_key'];
            $translation_field = new One2ManyField('Translation', $this->translate['object'], $this->translate['key'], $args);
            $object->add_column('_translation', $translation_field);
        }
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=[]){
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
        $language_id = $this->get_language_id($context);
        if (!$language_id) return $this->add_operator($this_sql, $context);
        $context = ['parameter'=>$this->translate['language_id'].'='.$language_id];
        $fields = [$this->translate['column']];
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

    function __construct($label, $size, $args = []){
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

class MediumTextField extends TextField{
    function get_sql_def(){
        return 'MEDIUMTEXT';
    }
}

class LongTextField extends TextField{
    function get_sql_def(){
        return 'LONGTEXT';
    }
}
