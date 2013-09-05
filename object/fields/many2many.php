<?php
require_once 'relational.php';

class Many2ManyField extends One2ManyField{
    var $widget='many2many';
    var $relation_object_field;
    var $stored=false;
    var $back_ref_field=null; // If set, name of the back reference (O2M) to this field in the relational object
    var $relation_table=null;
    var $rt_local_field=null;
    var $rt_foreign_field=null;
    var $equal2equal = false;

    function __construct($label, $relation_object_name, $args = array()){
        parent::__construct($label, $relation_object_name, '', $args);
        $this->left_right = false;
    }

    function set_instance($object, $name){
        parent::set_instance($object, $name);
        $robj = $this->get_relation_object();
        $br_field = '';
        if ($this->back_ref_field !== null){
            if(is_array($this->back_ref_field)){
                list($br_label,$br_field) = $this->back_ref_field;
            }else{
                $br_label = $br_field = $this->back_ref_field;
            }
            $this->equal2equal = $object->_name == $robj->_name && $name == $br_field;
        }
        if (is_null($this->relation_table)) $this->_auto_set_relation_table($object, $name, $br_field, $robj);
        if ($this->relation_object_key == '') $this->relation_object_key = $robj->_key;
        if (is_null($this->rt_local_field)){
            $this->rt_local_field = $object->_name.'_id';
        }
        if (is_null($this->rt_foreign_field)){
            $this->rt_foreign_field = $robj->_name.'_id';
        }
        if ($this->rt_foreign_field == $this->rt_local_field){
            if ($this->back_ref_field !== null) $this->rt_local_field = $br_field.'_'.$this->rt_local_field;
            $this->rt_foreign_field = $name.'_'.$this->rt_foreign_field;
        }
        if ($this->back_ref_field !== null){
            // Bug: !! The remote column won't be created if this class isn't intanciated !!
            if(!isset($robj->_columns[$br_field])){
                $robj->add_column($br_field,
                        new Many2ManyField($br_label,
                                $object->_name,
                                array('relation_table'=>$this->relation_table,
                                        'rt_foreign_field'=>$this->rt_local_field,
                                        'rt_local_field'=>$this->rt_foreign_field)));
            }
        }
        $pool = $object->_pool;
        if (!$pool->object_in_pool($this->relation_table)){
            $rel_table_object = new ObjectModel($pool, array(
                    '_name'=>$this->relation_table,
                    '_columns'=>array($this->rt_local_field=>new Many2OneField(null, $object->_name),
                            $this->rt_foreign_field=>new Many2OneField(null, $robj->_name)
                    )));
            $pool->add_object($this->relation_table, $rel_table_object);
        }else{
            $rel_table_object = $object->_pool->{$this->relation_table};
        }
        $this->m2m_relation_object = $this->relation_object;
        $this->relation_object = $rel_table_object;
        $this->relation_object_field = $this->rt_local_field;
    }

    function _auto_set_relation_table($object, $name, $br_field, $robj){
        //Auto find relation table name maximum 64 chars according to MySQL
        if ($this->back_ref_field !== null){
            if ($this->equal2equal){
                $this->relation_table = 'e2e_'.$object->_name.'_'.$name;
            }else{
                if ($object->_name == $robj->_name){
                    $this->relation_table = 'm2m_'.$object->_name.'_';
                    if ($name < $br_field){
                        $this->relation_table .= $name.'_'.$br_field;
                    }else{
                        $this->relation_table .= $br_field.'_'.$name;
                    }

                }elseif($object->_name < $robj->_name){
                    $this->relation_table = 'm2m_'.substr($object->_name, 0, 10).'_'.substr($name, 0, 10).'_'.substr($robj->_name, 0, 10).'_'.substr($br_field, 0, 10);
                }else{
                    $this->relation_table = 'm2m_'.substr($robj->_name, 0, 10).'_'.substr($br_field, 0, 10).'_'.substr($object->_name, 0, 10).'_'.substr($name, 0, 10);
                }
            }
        }else{
            if ($object->_name <= $robj->_name){
                $this->relation_table = 'm2m_'.$object->_name.'_'.$robj->_name;
            }else{
                $this->relation_table = 'm2m_'.$robj->_name.'_'.$object->_name;
            }
        }
    }
    
    function join_key_words($keywords){
        $r = '';
        foreach($keywords as $keyword=>$value){
            $r .= ' '.$keyword.' '.$value;
        }
        return $r;
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if ($sql_query->debug > 1) echo 'M2M['.$this->name.']: '.print_r($fields, true).'<br>';
        $nb_fields = count($fields);
        $new_fields = $fields; //copy
        $new_ctx = $context; //copy
        if ($nb_fields){
            if ($nb_fields == 1 && $fields[0] == $this->m2m_relation_object->_key){
                $new_fields = array($this->rt_foreign_field);
            }else{
                $field_name = $fields[0];
                if($field_name[0] == '(' && $field_name[strlen($field_name)-1] == ')'){
                    $sub_mql = substr($field_name, 1, -1);
                    list($mql, $keywords) = $sql_query->split_keywords($sub_mql);
                    $fields[0] = '('.$mql.')';
                }else{
                    $keywords = array();
                }
                $new_fields = array('('.$this->rt_foreign_field.'.'.implode('.',$fields).' as  '.$context['parameter'].$this->join_key_words($keywords).')');
                if ($sql_query->debug > 1) echo 'M2M New field: '.print_r($new_fields, true).'<br>';
                unset($new_ctx['parameter']);
            }
        }
        return parent::get_sql($parent_alias, $new_fields, $sql_query, $new_ctx);
    }

    function sql_write($sql_write, $value, $fields, $context){
        if (!is_array($value)) return;
        /* Values: (0, 0,  { fields })    create
         *         (1, ID, { fields })    modification
         *         (2, ID)                remove
         *         (3, ID)                unlink
         *         (4, ID)                link
         *         (5, ID)                unlink all
         *         (6, ?, ids)            set a list of links
         * compatible with openobject
         */
        $local_ids = $sql_write->ids;
        $robj = $this->m2m_relation_object;
        foreach($value as $val){
            switch($val[0]){
                case 0: //create
                    $new_id = $robj->create($val[2], $context);
                    foreach ($local_ids as $id){
                        $this->relation_object->create(array($this->rt_local_field=>$id, $this->rt_foreign_field=>$new_id));
                    }
                    break;
                case 1: //modification
                    $robj->write($val[2], $robj->_key.'=%s', array($val[1]), $context);
                    break;
                case 2: //remove remote object
                    $robj->unlink($val[1]);
                    //Do also unlink
                case 3: //unlink
                    $this->relation_object->unlink($this->rt_local_field.' in %s and '.$this->rt_foreign_field.'=%s', array($local_ids, $val[1]));
                    break;
                case 4: //link
                    foreach ($local_ids as $id){
                        $this->relation_object->create(array($this->rt_local_field=>$id, $this->rt_foreign_field=>$val[1]));
                    }
                    break;
                case 5: //unlink all
                    $this->relation_object->unlink($this->rt_local_field.' in %s', array($local_ids));
                    break;
                case 6: //Set a list of links
                    $new_rids = $val[2];
                    if (!count($new_rids)){
                        $this->relation_object->unlink($this->rt_local_field.' in %s', array($local_ids));
                        return;
                    }
                    $this->relation_object->unlink($this->rt_local_field.' in %s and '.$this->rt_foreign_field.' not in %s', array($local_ids, $new_rids));
                    $result = $this->relation_object->select($this->rt_local_field.' as id,'.$this->rt_foreign_field.' as rid where '.$this->rt_local_field.' in %s and '.$this->rt_foreign_field.' in %s', array(), array($local_ids, $new_rids));
                    $existing_ids = array();
                    foreach($result as $row){
                        if(!isset($existing_ids[$row->id])) $existing_ids[$row->id] = array();
                        $existing_ids[$row->id][] = $row->rid;
                    }
                    foreach($local_ids as $id){
                        if(!isset($existing_ids[$id])){
                            $rids2add = $new_rids;
                        }else{
                            $rids2add = array_diff($new_rids, $existing_ids[$id]);
                        }
                        foreach ($rids2add as $rid){
                            $this->relation_object->create(array($this->rt_local_field=>$id, $this->rt_foreign_field=>$rid));
                        }
                    }
                    break;
            }
        }
    }
}
?>