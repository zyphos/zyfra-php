<?php
class MetaField extends Field{
    var $type='meta';
    var $stored=false;
    var $type_objects;
    var $column_object;

    function __construct($label, $args = array()){
        parent::__construct($label, $args);
        $this->types = array('int'=>'IntField', 'float'=>'DoubleField', 'txt'=>'TinytextField');
        $this->type_objects = array();
        $this->tof = array(1=>array('float'),2=>array('int'),3=>array('txt'));
    }

    function set_instance($object, $name){
        parent::set_instance($object, $name);
        $pool = $object->_pool;
        $this->metaname_base = $object->_name.'_'.$name;
        foreach($this->types as $key=>$type){
            $meta_name = $this->metaname_base.'_'.$key;
            $meta_obj = new ObjectModel($pool, array(
                        '_name'=>$meta_name,
                        '_columns'=>array('src_id'=>new Many2OneField('Source obj', $this->object->_name),
                                          'col_id'=>new Many2OneField('Column', 'meta_columns'),
                                          'value'=>new $type('Value')
            )));
            $this->type_objects[$key] = $meta_obj;
            $pool->add_object($meta_name, $meta_obj);
        }
    }

    function sql_create($sql_create, $value, $fields, $context){
        $sql_create->add_callback($this, 'sql_create_after_trigger');
        return null;
    }

    function sql_create_after_trigger($sql_create, $value, $fields, $id, $context){
        $this->update_ids(array($id), $value, $context, true);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if(count($fields)==0){
            $this->update_ids($sql_write->ids, $value, $context);
            return;
        }
        //Handle subfield
        $field = array_shift($fields);
        list($field_name, $field_data) = specialsplitparam($field);
        $this->update_ids($sql_write->ids, array($field_name=>$value), $context);
    }

    function update_ids($src_ids, $values, $context, $new_parents=false){
        if (!count($src_ids)) return;
        print_r($values);
        $properties = array_keys($values);
        print_r($properties);
        $cols = $this->column_object->select(['name,tof,id WHERE name IN %s', [$properties]]);
        $col_array = array();
        $col_name = array('col_id', 'src_id', 'value');
        $col2del = array();
        foreach($cols as $col){
            $value = $values[$col->name];
            if ($value == null) {
                $col2del[] = $col->id;
                continue;
            }
            $col_array[$col->tof][$col->id]=array('col_id'=>$col->id, 'src_id'=>$src_ids, 'value'=>$value);
        }
        if ($new_parents){
            foreach($col_array as $tof=>$cols){
                $this->type_objects[$tof]->create($col_array[$tof], $context);
            }
        }else{
            foreach($col_array as $tof=>$cols){
                //1. Delete
                $tof_obj = $this->type_objects[$this->tof[$tof][0]];
                if (count($col2del)){
                    $tof_obj->unlink(['src_id IN %s AND col_id IN %s', [$src_ids, $col2del]]);
                }

                //2. Update
                $current_values = $tof_obj->select(['col_id,src_id WHERE src_id IN %s AND col_id IN %s', [$src_ids, array_keys($cols)]]);
                $update_ids = array();
                foreach($current_values as $row){
                    $update_ids[$row->col_id][] = $row->src_id;
                }
                foreach($update_ids as $col_id=>$src_col_ids){
                    $tof_obj->write(array('value'=>$cols[$col_id]['value']), ['col_id=%s AND src_id IN %s', [$col_id, $src_ids]], $context);
                }

                //3. Update
                $create_rows = array();
                foreach($cols as $col_id=>$row){
                    if (!in_array($col_id, $update_ids)) continue;
                    $ids = array();
                    foreach($src_ids as $id){
                        if (!in_array($id,$update_ids[$col_id])) $ids[] = $id;
                    }
                    $create_rows[] = array('col_id'=>$col_id, 'value'=>$cols[$col_id]['value'], 'src_id'=>$ids);
                }
                $tof_obj->create($create_rows, $context);
            }
        }
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if (array_key_exists('parameter', $context)){
            $parameter = $context['parameter'];
        }else{
            $parameter = '';
        }
        $key_field = $parent_alias->alias.'.'.$this->object->_key;
        $field_alias = $context['field_alias'];

        if (count($fields)==0){
            $sub_mql = 'col_id.name AS column,value';
        }elseif (count($fields)>0){
            $field_name = array_shift($fields);
            if($field_name[0] == '('){
                $metas = explode(',', substr($field_name, 1, -1));
                if (count($metas)==0) return '';
                $sub_mql = "col_id.name AS `column`, value WHERE col_id.name IN ('".implode("','", $metas)."')";
            }else{
                //Normal id
                $cols = $this->column_object->select($this->column_object->_key." AS `key`, tof WHERE name='".$field_name."'");
                if (count($cols)!=1) return '';
                $col = $cols[0];
                $col_object = $this->type_objects[$this->tof[$col->tof][0]];
                $parameter = array_key_exists('param', $context)?$context['parameter']:'';
                $field_link = $parent_alias->alias.'.'.$this->name.$field_name;
                $sql = 'LEFT JOIN '.$col_object->_table.' AS %ta% ON %ta%.col_id='.$col->key.' AND %ta%.src_id='.$key_field;
                $ta = $sql_query->get_table_alias($field_link, $sql, $parent_alias);
                $ta->set_used();
                return $ta->alias.'.value';
            }
        }
        $parent_alias->set_used();
        foreach($this->type_objects as $meta_name=>$type_object){
            $sub_mql = 'col_id.name AS `column`,value';
            $sql_query->add_sub_query($type_object, 'src_id', $sub_mql, $field_alias, $parameter);
        }
        return $key_field;
    }
}
?>