<?php
class Many2OneField extends Field{
    var $relation_object_name;
    var $relation_object=null;
    var $left_right=true;
    var $relational=true;
    var $default_value=null;
    var $back_ref_field=null; // If set, name of the back reference (O2M) to this field in the relational object
    var $widget='many2one';

    function __construct($label, $relation_object_name, $args = array()){
        parent::__construct($label, $args);
        $this->relation_object_name = $relation_object_name;
    }

    function get_relation_object(){
        if ($this->relation_object===null){
            try{
                $this->relation_object = $this->object->_pool->__get($this->relation_object_name);
            } catch(PoolObjectException $e){
                throw new Exception('Could not find object ['.$this->relation_object_name.'] field many2one ['.$this->name.'] from object ['.$this->object->_name.']');
            }
        }
        return $this->relation_object;
    }

    function set_instance($object, $name){
        parent::set_instance($object, $name);
        if (!$this->left_right) return;
        if ($object->_name != $this->relation_object_name){
            $this->left_right = false;
        }else{
            $this->pleft = $name.'_pleft';
            $this->pright = $name.'_pright';
            $this->needed_columns[$this->pleft] = new IntField($this->label.' left');
            $this->needed_columns[$this->pright] = new IntField($this->label.' right');
        }
        if ($this->back_ref_field !== null){
            if(is_array($this->back_ref_field)){
                list($label,$field) = $this->back_ref_field;
            }else{
                $label = $field = $this->back_ref_field;
            }
            $this->get_relation_object()->add_column($field, new One2ManyField($label, $object->_name, $name));
        }
    }

    function get_sql_def(){
        return $this->get_relation_object()->_columns[$this->relation_object->_key]->get_sql_def();
    }

    function get_sql($parent_alias, $fields, $sql_query, $context=array()){
        if ((count($fields) == 0)||($fields[0] == $this->get_relation_object()->_key)){
            if ($this->left_right && array_key_exists('operator',$context) && in_array($context['operator'], array('parent_of', 'child_of'))){
                $pa = $parent_alias->alias;
                $operator = $context['operator'];
                switch($operator){
                    case 'parent_of':
                        $sql = 'LEFT JOIN '.$this->object->_table.' AS %ta% ON '.$pa.'.'.$this->pleft.'<%ta%.'.$this->pleft.' AND '.$pa.'.'.$this->pright.'>%ta%.'.$this->pright;
                        break;
                    case 'child_of':
                        $sql = 'LEFT JOIN '.$this->object->_table.' AS %ta% ON '.$pa.'.'.$this->pleft.'>%ta%.'.$this->pleft.' AND '.$pa.'.'.$this->pright.'<%ta%.'.$this->pright;
                        break;
                }
                $field_link = $parent_alias->alias.'.'.$this->name.$operator;
                $ta = $sql_query->get_table_alias($field_link, $sql, $parent_alias);
                $sql_query->order_by[] = $pa.'.'.$this->pleft;
                $ta->set_used();
                return $ta->alias.'.'.$this->object->_key.'=';
            }else{
                $field_link = $parent_alias->alias.'.'.$this->name;
                $parent_alias->set_used();
                return $field_link;
            }
        }
        $parameter = array_key_exists('param', $context)?$context['parameter']:'';
        $field_link = $parent_alias->alias.'.'.$this->name.$parameter;
        $sql = 'LEFT JOIN '.$this->relation_object->_table.' AS %ta% ON %ta%.'.$this->relation_object->_key.'='.$parent_alias->alias.'.'.$this->name;
        if (array_get($sql_query->context, 'visible', true)&&($this->relation_object->_visible_condition != '')){
            list($sql_txt, $on_condition) = explode(' ON ', $sql);
            $visible_sql_q = new SqlQuery($this->relation_object, '%ta%');
            $sql = $sql_txt.' ON ('.$on_condition.')AND('.$visible_sql_q->where2sql('').')';
        }
        $ta = $sql_query->get_table_alias($field_link, $sql, $parent_alias);
        $field_name = array_shift($fields);
        list($field_name, $field_param) = $sql_query->split_field_param($field_name);
        if($field_name[0] == '(' && $field_name[strlen($field_name)-1] == ')'){
            $sub_mql = substr($field_name, 1, -1);
            if(array_key_exists('field_alias',$context)){
                $field_alias = $context['field_alias'];
            }else{
                $field_alias = '';
            }
            $sql_query->split_select_fields($sub_mql, false, $this->relation_object, $ta, $field_alias);
            return null;
        }
        $context['parameter'] = $field_param;
        return $this->relation_object->_columns[$field_name]->get_sql($ta, $fields, $sql_query, $context);
    }

    function sql_create($sql_create, $value, $fields, $context){
        if (count($fields)==0){
            return parent::sql_create($sql_create, $value, $fields, $context);
        }
        //Handle subfield (meanfull ?)
        return null;
    }

    function sql_write($sql_write, $value, $fields, $context){
        if(count($fields)==0){
            parent::sql_write($sql_write, $value, $fields, $context);
            return;
        }
        //to do: handle case of subfield
    }

    function after_write_trigger(&$old_values, $new_value){
        if (!$this->left_right) return;
        //Update left and right tree
        $db = $this->object->_pool->db;
        $modified_values_ids = array();
        $left_col = $this->pleft;
        $right_col = $this->pright;
        $table = $this->object->_table;
        $key = $this->object->_key;
        foreach($old_values as $id=>$old_value){
            if ($old_value == $new_value) continue;
            $obj = $db->get_object('SELECT '.$left_col.' AS lc, '.$right_col.' AS rc FROM '.$table.' WHERE '.$this->object->_key.'='.$id);
            $l0 = $obj->lc;
            $r0 = $obj->rc;
            $d = $r0 - $l0;
            $children_ids = $db->get_array('SELECT '.$key.' FROM '.$table.' WHERE '.$left_col.'>='.$l0.' AND '.$right_col.'<='.$r0, $key);
            $l1 = $this->_tree_get_new_left($id, $new_value);
            if ($l1 > $l0){
                $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'-'.($d+1).' WHERE '.$left_col.'>'.$r0.' AND '.$left_col.'<'.$l1);
                $db->safe_query('UPDATE '.$table.' SET '.$right_col.'='.$right_col.'-'.($d+1).' WHERE '.$right_col.'>'.$r0.' AND '.$right_col.'<'.$l1);
                $delta = $l1 - $l0 - $d - 1;
            }else{
                $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'+'.($d+1).' WHERE '.$left_col.'>='.$l1.' AND '.$left_col.'<'.$l0);
                $db->safe_query('UPDATE '.$table.' SET '.$right_col.'='.$right_col.'+'.($d+1).' WHERE '.$right_col.'>='.$l1.' AND '.$right_col.'<'.$l0);
                $delta = $l1 - $l0;
            }
            $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'+'.$delta.','.$right_col.'='.$right_col.'+'.$delta.' WHERE '.$key.' in %s', array($children_ids));
        }
    }

    function _tree_get_new_left($id, $value){
        $db = $this->object->_pool->db;
        $key = $this->object->_key;
        $left_col = $this->pleft;
        $right_col = $this->pright;
        $table = $this->object->_table;
        if ($value == null || $value == 0){
            $l1 = 1;
            $brothers = $this->object->select($key.' AS id,'.$right_col.' AS rc WHERE '.$this->name.' IS NULL OR '.$this->name.'=0');
            foreach($brothers as $brother){
                if ($brother->id == $id) break;
                $l1 = $brother->rc + 1;
            }
        }else{
            $parent_obj = $this->object->_pool->db->get_object('SELECT '.$left_col.' AS lc FROM '.$table.' WHERE '.$key.'=%s', array($value));
            $l1 = $parent_obj->lc + 1;
            $brothers = $this->object->select($key.' AS id,'.$right_col.' AS rc WHERE '.$this->name.'=%s', array(), array($value));
            foreach($brothers as $brother){
                if ($brother->id == $id) break;
                $l1 = $brother->rc + 1;
            }
        }
        return $l1;
    }

    function rebuilt_tree($id = 0, $left = 1, $key='', $table=''){
        if($key == '' || $table == ''){
            $key = $this->object->_key;
            $table = $this->object->_table;
        }
        $right = $left+1;

        if ($id==null || $id==0){
            $rows = $this->object->select($key.' AS id WHERE '.$this->name.' IS NULL OR '.$this->name.'=0');
        }else{
            $rows = $this->object->select($key.' AS id WHERE '.$this->name.'=%s', array(), array($id));
        }
        foreach ($rows as $row){
            $right = $this->rebuilt_tree($row->id, $right, $key, $table);
        }
        if ($id!=0 && $id!=null){
            $db = $this->object->_pool->db;
            $db = $this->object->_pool->db;
            $db->safe_query('UPDATE '.$table.' SET '.$this->pleft.'='.$left.', '.$this->pright.'='.$right.' WHERE '.$key.'=%s', array($id));
        }
        return $right+1;
    }

    function before_unlink_trigger($old_values){
        if (!$this->left_right) return;
        if (count($old_values) == 0) return;
        $db = $this->object->_pool->db;
        $table = $this->object->_table;
        $left_col = $this->pleft;
        $right_col = $this->pright;
        $sql = 'SELECT '.$this->pleft.' AS pleft FROM '.$this->object->_table.' WHERE '.$this->object->_key.' IN %s ORDER BY pleft';
        $plefts = $db->get_array($sql, 'pleft', '', array(array_keys($old_values)));
        $nb = count($plefts);
        for($i=0; $i<$nb; $i++){
            $nbi = ($i+1)*2;
            if ($i+1 < $nb){
                if ($plefts[$i+1]-$plefts[$i]>=2){
                    $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'-'.$nbi.' WHERE '.$left_col.'>'.$plefts[$i].' AND '.$left_col.'<'.$plefts[$i+1]);
                    $db->safe_query('UPDATE '.$table.' SET '.$right_col.'='.$right_col.'-'.$nbi.'  WHERE '.$right_col.'>'.$plefts[$i].' AND '.$right_col.'<'.$plefts[$i+1]);
                }
            }else{
                $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'-'.$nbi.' WHERE '.$left_col.'>'.$plefts[$i]);
                $db->safe_query('UPDATE '.$table.' SET '.$right_col.'='.$right_col.'-'.$nbi.'  WHERE '.$right_col.'>'.$plefts[$i]);
            }
        }
    }

    function after_create_trigger($id, $value, $context){
        if (!$this->left_right) return;
        $db = $this->object->_pool->db;
        $l1 = $this->_tree_get_new_left($id, $value);
        $left_col = $this->pleft;
        $right_col = $this->pright;
        $table = $this->object->_table;
        $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$left_col.'+2  WHERE '.$left_col.'>='.$l1);
        $db->safe_query('UPDATE '.$table.' SET '.$right_col.'='.$right_col.'+2  WHERE '.$right_col.'>='.$l1);
        $db->safe_query('UPDATE '.$table.' SET '.$left_col.'='.$l1.', '.$right_col.'='.($l1+1).' WHERE '.$this->object->_key.'=%s', array($id));
    }
}
?>