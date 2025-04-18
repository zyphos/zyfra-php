<?php
/*****************************************************************************
*
*         Synch Database Class
*         ---------------
*
*         Class to synchronize tables from 2 databases.
*         All data exchanges are crypted.
*
*    Copyright (C) 2009 De Smet Nicolas (<http://ndesmet.be>).
*    All Rights Reserved
*
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*****************************************************************************/

/*****************************************************************************
* Quick Usage:
* ------------
* require_once 'ZyfraPHP/database_sync.php';
* class MySync extends zyfra_database_synch{
*     function __construct(){
*         self::set_crypt_key('MyCryptKey');
*         parent::__construct();
*     }
* }
* new MySync();
*
*****************************************************************************/

/*****************************************************************************
* Revisions
* ---------
*
* v0.01    23/10/2009    Creation
*****************************************************************************/


require_once 'db.php';
include_once 'rpc_big.php';
include_once 'debug.php';

class zyfra_STRUCT_table_struct{
  public $name;
  public $fields;
  public $index;
  public $primary;
  public $keys;
  public $fulltext;

  function __construct(){
    $fields = [];
    $primary = [];
    $keys = [];
    $fulltext = [];
  }
}

class zyfra_STRUCT_table_fields{
  public $name;
  public $type;
  public $type_short;
  public $null;
  public $default;
  public $default_value;
  public $extra;
}

class zyfra_synch_flag{
  public $flags;
  function __construct($the_flags){
      $this->flags = $the_flags;
  }

  function src_dst_can_update(){
    return (($this->flags&1)>0);
  }

  function src_dst_can_add(){
    return (($this->flags&2)>0);
  }

  function src_dst_can_delete(){
    return (($this->flags&4)>0);
  }

  function src_dst_forced(){
    return (($this->flags&8)>0);
  }

  function dst_src_can_update(){
    return (($this->flags&16)>0);
  }

  function dst_src_can_add(){
    return (($this->flags&32)>0);
  }

  function dst_src_can_delete(){
    return (($this->flags&64)>0);
  }

  function dst_src_forced(){
    return (($this->flags&128)>0);
  }

  function get_txt(){
      $txt = 'src -> dst: ';
      if($this->src_dst_can_update()) $txt .= 'upd ';
      if($this->src_dst_can_add()) $txt .= 'add ';
      if($this->src_dst_can_delete()) $txt .= 'del ';
      if($this->src_dst_forced()) $txt .= 'force ';
      $txt .= '|...| dst -> src: ';
      if($this->dst_src_can_update()) $txt .= 'upd ';
      if($this->dst_src_can_add()) $txt .= 'add ';
      if($this->dst_src_can_delete()) $txt .= 'del ';
      if($this->dst_src_forced()) $txt .= 'force ';
      return $txt;
  }

  function read_data_needed(){
      return (($this->flags&51)>0);
  }

  function sync_needed(){
      return (($this->flags&119)>0);
  }

  function update(){
      return (($this->flags&17)>0);
  }

  function add(){
      return (($this->flags&34)>0);
  }

  function delete(){
      return (($this->flags&68)>0);
  }
}

class zyfra_database_synch extends zyfra_rpc_big{
    public $delta_time=0;
    public $field_separator;
    public $sync_data_table_name = 'data_sync';
    public $create_field = 'created_on';
    public $update_field = 'modified_on';
    public $db;
    public $table_structure = null;
    public $only_slst_filename = null;

    function __construct(){
        global $db;
        $this->field_separator = chr(254).chr(254);
        $this->db = $db;
        parent::__construct();
    }

    protected function rpc_get_time(){
        return gmdate("Y-m-d H:i:s");
    }

    function log($msg){
        zyfra_debug::_print($msg);
    }

    function mem(){
        return ' Mem: '.number_format(memory_get_usage()).' ';
    }

    function var_size($var){
        $mem = memory_get_usage();
        $t = unserialize(serialize($var));
        $mem_tot = memory_get_usage() - $mem;
        unset($t);
        return ' Mem: '.number_format($mem_tot).' ';
    }

    function sync_table($table_name, $key_names, $col_names, $sync_flags,$sync_id,$url,$incremental){
        $this->log('<h2>Doing table "'.$table_name.'"</h2>');
        $this->log($this->mem().'<br>');
        $key_names = explode(',',$key_names);
        $col_names = explode(',',$col_names);
        $col_names[] = $this->create_field;
        $sync_flags = new zyfra_synch_flag($sync_flags);
        $this->log('Flags: '.$sync_flags->get_txt().'<br>');
        if(!$sync_flags->sync_needed()) {
            $this->log('No Sync needed.<br>');
            return;
        }
        //Pseudo start time in the past, we are sure that those datas
        //aren't going to be updated
        // *2 = Be sure
        $sync_start_ts = time() - abs($this->delta_time * 2);
        $this->log('Start time: '.date('Y-m-d H:i:s', $sync_start_ts).'<br>');
        list($last_start_ts, $last_end_ts) = $this->get_last_sync_by_table($sync_id, $table_name);
        $this->log('Last sync started at: '.date('Y-m-d H:i:s', $last_start_ts).'<br>');
        $this->log('Last sync stoped at: '.date('Y-m-d H:i:s', $last_end_ts).'<br>');
        $this->log('Delta time: '.$this->delta_time.' sec.<br>');
        /*if(abs($sync_start_ts - $last_sync_start_ts) < (abs($this->delta_time)*10)){
         //It's to early to make a sync, delta time is huge.
        $this->log('It\'s to early to make a sync, delta time is huge. '.$table_name.'<br>');
        continue; //Skip this sync
        }*/
        //Get indexes
        $this->log('Getting local table indexes... ');
        $local_indexes = $this->rpc_get_table_indexes($table_name,$key_names, $sync_start_ts);
        $this->log(count($local_indexes).'<br>');
        $this->log('Getting remote table indexes... ');
        $remote_indexes = zyfra_rpc_big::send_rpc($url, 'get_table_indexes', [$table_name,$key_names, $sync_start_ts]);
        $this->log(count($remote_indexes).'<br>');

        //Delete
        if($sync_flags->delete()){
            $this->log('Computing deletions...<br>');
            list($local2del, $remote2del) = $this->compute2del($local_indexes, $remote_indexes, $sync_flags, $sync_start_ts, $last_start_ts);
            if (count($local2del) > 0){
                $this->log('Deleting from local...<br>');
                $this->log($this->rpc_delete($table_name, $key_names, $local2del));
            }
            if (count($remote2del) > 0){
                $this->log('Deleting from remote...<br>');
                $this->log(zyfra_rpc_big::send_rpc($url, 'delete', [$table_name, $key_names, $remote2del]));
            }
            unset($local2del, $remote2del);
        }

        //Get datas
        if ($this->read_data_needed($sync_flags)){
            $this->log('Getting local table datas... ');
            $local_datas = $this->rpc_get_table_datas($table_name, $key_names, $col_names, $sync_start_ts, $last_start_ts, $incremental);
            $this->log(count($local_datas).'<br>');
            $this->log('Getting remote table datas... ');
            $remote_datas = zyfra_rpc_big::send_rpc($url, 'get_table_datas', [$table_name, $key_names, $col_names, $sync_start_ts, $last_start_ts, $incremental]);
            $this->log(count($remote_datas).'<br>');

            //Update
            if($sync_flags->update()){
                $this->log('Computing updates...<br>');
                list($local2update, $remote2update) = $this->compute2update($local_indexes, $remote_indexes, $local_datas, $remote_datas, $sync_flags);
                if (count($local2update) > 0){
                    $this->log('Updating to local...<br>');
                    $this->log($this->rpc_update($table_name, $key_names, $col_names, $local2update));
                }
                if (count($remote2update) > 0){
                    $this->log('Updating to remote...<br>');
                    $this->log(print_r(zyfra_rpc_big::send_rpc($url, 'update', [$table_name, $key_names, $col_names, $remote2update]),true));
                }
                unset($local2update, $remote2update);
            }


            //Add
            if($sync_flags->add()){
                $this->log('Computing adds...<br>');
                list($local2add, $remote2add) = $this->compute2add($local_indexes, $remote_indexes, $local_datas, $remote_datas, $sync_flags);
                if (count($local2add) > 0){
                    $this->log('Adding to local...<br>');
                    $this->log($this->rpc_add($table_name, $key_names, $col_names, $local2add));
                }
                if (count($remote2add) > 0){
                    $this->log('Adding to remote...<br>');
                    $this->log(zyfra_rpc_big::send_rpc($url, 'add', [$table_name, $key_names, $col_names, $remote2add]));
                }
                unset($local2add, $remote2add);
            }
        }

        //Mark table has updated
        $sync_stop_ts = time()- abs($this->delta_time * 2);
        $this->log('Stop time: '.date('Y-m-d H:i:s',$sync_stop_ts).'<br>');
        $this->log('Duration: '.($sync_stop_ts - $sync_start_ts).' seconds<br>'.$this->mem().'<hr>');
        $this->set_last_sync_for_table($sync_id,$table_name,$sync_start_ts,$sync_stop_ts);

        unset($table_name,$key_names,$col_names,$sync_flags);
        unset($local_indexes, $remote_indexes, $local_datas, $remote_datas);
    }

    function sync($synch_table_lst, $incremental = true, $only_table = null){
        global $db;
        $db->query('BEGIN');

        $tables_struct = $this->get_tables_struct();
        //Send table struct
        list($sync_id, $url, $table_list) = $this->read_table_list($synch_table_lst);
        $remote_time = zyfra_rpc_big::send_rpc($url, 'get_time', NULL);
        if (trim($remote_time) == '') throw new Exception('RPC isn\'t working!');
        $this->delta_time = strtotime($remote_time.' UTC')-time();
        if ($incremental){
            $this->log('Synchronization is INCREMENTAL<hr>');
        }else{
            $this->log('Synchronization is FULL<hr>');
        }
        $this->log('Sync id: '.$sync_id.'<hr>');
        foreach ($table_list as $table){
            list($table_name,$key_names,$col_names,$sync_flags) = explode(':',$table);
            if (!is_null($only_table) && $table_name != $only_table) continue;
            $nb_try = 0;
            while(true){
                try {
                    $nb_try++;
                    if ($nb_try>1) $this->log('Failed retrying attempt nr '.$nb_try);
                    $this->sync_table($table_name,$key_names,$col_names,$sync_flags,$sync_id,$url,$incremental);
                    break;
                } catch (Exception $e) {
                    if ($nb_try > 4) throw $e;
                }
            }
        }
        $db->query('COMMIT');
    }

    function read_data_needed($sync_flags){
        return $sync_flags->src_dst_can_update() ||
            $sync_flags->src_dst_can_add() ||
            $sync_flags->dst_src_can_update() ||
            $sync_flags->dst_src_can_add();
    }

    function read_table_list($synch_table_lst){
        $this->log('Reading table list file "'.$synch_table_lst.'"<br>');
        $f_handle = fopen($synch_table_lst,'r');
        $sync_id = (int)rtrim(fgets($f_handle));
        $url = rtrim(fgets($f_handle));
        $table_list = [];
        while(!feof($f_handle)){
            /*On lit la ligne source
             * de type :
             * nom table:key1[,key2,...]:row1[,row2,...]:synch_flag
             */
            /*synch_flag
             * bit 0   (1): synch SRC -> DST: can update
             * bit 1   (2): can add
             * bit 2   (4): can delete
             * bit 3   (8): Forced update (don't look at the date)
             *
             * bit 4  (16): synch DST -> SRC: can update
             * bit 5  (32): can add
             * bit 6  (64): can delete
             * bit 7 (128): Forced update (don't look at the date)
             */
            $row_src = rtrim(fgets($f_handle));
            $table_list[] = $row_src;
        }
        fclose($f_handle);
        return [$sync_id, $url, $table_list];
    }

    function get_tables_struct($use_cache=false){
        if ($use_cache && !is_null($this->table_structure)) return $this->table_structure;
        $db = $this->db;
        //Function that creates an image of the entire database structure.
        $tables = [];
        //retrieve tablename from the database
        $sql = 'SHOW TABLES FROM '.$db->default_db;
        $result_table = $db->query($sql);
        while($table = $db->fetch_array($result_table)){
            $the_table = new zyfra_STRUCT_table_struct;
            $the_table->name = $table[0];  //Get the table name

            //Get colums from the table
            $sql = 'SHOW COLUMNS FROM '.$the_table->name;
            $result_cols = $db->query($sql);
            //Field    Type    Null    Key    Default    Extra
            while($col = $db->fetch_object($result_cols)){
                $the_col = new zyfra_STRUCT_table_fields;
                $the_col->name = $col->Field;  //Get the col name
                $type = $col->Type;
                $the_col->type = $type;  //Get the col type
                $type = explode('(', $type);
                $the_col->type_short = $type[0];  //Get the col type
                $the_col->null = 'NOT NULL';
                if($col->Null=='YES') $the_col->null = 'NULL';  //Is col type null ?
                $the_col->extra = is_null($col->Extra) ? null: trim($col->Extra);
                $the_col->default = '';
                $the_col->default_value = $col->Default;
                if(!is_null($col->Default) && trim($col->Default)!=''){
                    $the_col->default = "DEFAULT '".$col->Default."'";  //Get default value
                }
                $the_table->fields[$the_col->name] = $the_col;
            }
            $db->free_result($result_cols);  //Free used memory
            //retrieve index
            $sql = "SHOW INDEX FROM ".$the_table->name;
            $result_index = $db->query($sql);
            while($index = $db->fetch_object($result_index)){
                if($index->Index_type=="BTREE"){
                    if($index->Key_name=="PRIMARY"){
                        $the_table->primary[]=$index->Column_name;
                    }else{
                        $the_table->index[$index->Key_name][]=$index->Column_name;
                    }
                }elseif($index->Index_type=="FULLTEXT"){
                    $the_table->fulltext[$index->Key_name][]=$index->Column_name;
                }
            }
            $db->free_result($result_index);  //Free used memory
            $tables[$the_table->name] = $the_table;
        }
        $db->free_result($result_table);  //Free used memory
        if ($use_cache) $this->table_structure = &$tables;
        return $tables;
    }

    function rpc_get_table_datas($table_name, $key_names, $col_names, $start_ts, $last_start_ts, $incremental = true){
        $db = $this->db;
        $data_array = [];
        //$this->log('Getting data from '.$table_name.'...');
        $wheres = [];
        //$wheres[] = $this->update_field.'<'.gmdate('\'Y-m-d H:i:s\'', $start_ts);
        if($incremental){
            $wheres[] = $this->update_field.'>='.gmdate('\'Y-m-d H:i:s\'', $last_start_ts);
        }
        if (count($wheres)>0){
            $where = ' WHERE ('.implode(')AND(', $wheres).')';
        }else{
            $where = '';
        }
        $sql = "SELECT ".implode(",",$key_names).",".implode(",",$col_names)."
            FROM ".$table_name.$where;
        $result = $db->query($sql);
        while($row = $db->fetch_object($result)){
            $the_index = $this->make_index($key_names, $row);
            $cols = [];
            foreach($col_names as $col_name){
                $cols[] = $row->{$col_name};
            }
            $data_array[$the_index] = implode($this->field_separator, $cols);
        }
        //$this->log(' Done.<br>');
        return $data_array;
    }

    function rpc_get_table_indexes($table_name, $key_names, $start_ts){
        // Return all keys with modified unix timestamp
        //$this->log('Getting keys from '.$src->table_name.'...');
        //$sql = 'SELECT '.implode(',',$key_names).','.$this->create_field.','.$this->update_field.'
        //    FROM '.$table_name. ' WHERE ('.$this->update_field.'<'.gmdate('\'Y-m-d H:i:s\'', $start_ts).')';
        $sql = 'SELECT '.implode(',',$key_names).','.$this->create_field.','.$this->update_field.'
            FROM '.$table_name;
        $all_index = [];
        while($row = $this->db->get_object($sql)){
            $the_index = $this->make_index($key_names, $row);
            //$create = strtotime($row->{$this->create_field}.' UTC');
            if ($row->{$this->update_field} < $row->{$this->create_field}){
                $row->{$this->update_field} = $row->{$this->create_field};
            }
            $update = strtotime($row->{$this->update_field}.' UTC');
            $all_index[$the_index] = $update;
        }
        return $all_index;
    }

    function make_index($key_names, $obj){
        $keys = [];
        foreach($key_names as $key_name){
            $keys[] = $obj->{$key_name};
        }
        return implode($this->field_separator, $keys);
    }

    function set_tables_struct($db, $foreign_tables){
        $local_tables = $this->get_tables_struct();
        // 1. Checking deleted tables
        foreach ($local_tables as $key=>$table){
            if(!isset($foreign_tables[$key])){
                //the table has been deleted => remove it localy
                //$db->query("DROP TABLE ".$key);
            }
        }
    }

    function compute2del($local_indexes, $remote_indexes, $sync_flags, $sync_start_ts, $last_start_ts){
        $local2del = [];
        $remote2del = [];
        if ($sync_flags->src_dst_can_delete()){
            //$remote2del
            foreach($remote_indexes as $key=>$dates){
                if ((!isset($local_indexes[$key])&&($dates<$sync_start_ts)&&
                  ($dates<($last_start_ts + $this->delta_time)))){
                    $remote2del[] = $key;
                }
            }
        }
        if ($sync_flags->dst_src_can_delete()){
            //$local2del
            foreach($local_indexes as $key=>$dates){
                if ((!isset($remote_indexes[$key])&&($dates<$sync_start_ts)&&
                  ($dates<$last_start_ts))){
                    $local2del[] = $key;
                }
            }
        }
        return [$local2del, $remote2del];
    }

    function compute2update($local_indexes, $remote_indexes, $local_datas, $remote_datas, $sync_flags){
        $fs = $this->field_separator;
        $local2update = [];
        $remote2update = [];
        foreach($local_datas as $index=>$local_data){
            if(isset($remote_datas[$index])){
                //Join dates
                if ($sync_flags->src_dst_can_update()){
                    if (($local_indexes[$index] > $remote_indexes[$index])||($sync_flags->src_dst_forced()&&($local_data != $remote_datas[$index]))){
                        $remote2update[$index] = $local_data.$fs.$this->get_gmdate($local_indexes[$index]);
                        continue; //Skip update in the other way
                    }
                }
                if ($sync_flags->dst_src_can_update()){
                    if (($local_indexes[$index] < $remote_indexes[$index])||($sync_flags->dst_src_forced()&&($local_data != $remote_datas[$index]))){
                        $local2update[$index] = $remote_datas[$index].$fs.$this->get_gmdate($remote_indexes[$index]);
                    }
                }
            }
        }
        return [$local2update, $remote2update];
    }

    function compute2add($local_indexes, $remote_indexes, $local_datas, $remote_datas, $sync_flags){
        $fs = $this->field_separator;
        $local2add = [];
        $remote2add = [];
        if ($sync_flags->src_dst_can_add()){
            foreach($local_datas as $index=>$local_data){
                if(!isset($remote_datas[$index])){
                    $remote2add[$index] = $local_data.$fs.$this->get_gmdate($local_indexes[$index]);
                }
            }
        }
        if ($sync_flags->dst_src_can_add()){
            foreach($remote_datas as $index=>$remote_data){
                if(!isset($local_datas[$index])){
                    $local2add[$index] = $remote_data.$fs.$this->get_gmdate($remote_indexes[$index]);
                }
            }
        }
        return [$local2add,$remote2add];
    }

    function get_gmdate($date){
        return gmdate('Y-m-d H:i:s', $date);
    }

    private function escape_field($field_name, $value, &$table_structure){
        $field = $table_structure->fields[$field_name];
        //echo $field_name.'['.$field->type_short.'] value['.$value.'] default['.$field->default_value.'] null['.$field->null.']<br>';
        switch($field->type_short){
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                if ($value === ''){
                    if ($field->null == 'NULL'){
                        $value = 'null';
                    }else{
                        $value = $field->default_value;
                    }
                }else{
                    $value = (int)$value;
                }
                break;
            case 'float':
                if ($value === ''){
                    if ($field->null == 'NULL'){
                        $value = 'null';
                    }else{
                        $value = $field->default_value;
                    }
                }else{
                    $value = (float)$value;
                }
                break;
            case 'double':
                if ($value === ''){
                    if ($field->null == 'NULL'){
                        $value = 'null';
                    }else{
                        $value = $field->default_value;
                    }
                }else{
                    $value = (double)$value;
                }
                break;
            case 'datetime':
                if ($value === '' || $value == '0000-00-00 00:00:00'){
                    if ($field->null == 'NULL'){
                        $value = 'null';
                    }else{
                        $value = '\''.$field->default_value.'\'';
                    }
                }else{
                    $value = '\''.$this->db->safe_var($value).'\'';
                }
                break;
            case 'date':
                if ($value === '' || $value == '0000-00-00'){
                    if ($field->null == 'NULL'){
                        $value = 'null';
                    }else{
                        $value = '\''.$field->default_value.'\'';
                    }
                }else{
                    $value = '\''.$this->db->safe_var($value).'\'';
                }
                break;
            default:
                $value = '\''.$this->db->safe_var($value).'\'';
        }
        if ($value === '') $value = '\'\'';
        return $value;
    }

    function rpc_delete($table_name, $key_names, $row2del){
        $table_structure = $this->get_tables_struct(true);
        $table_structure = $table_structure[$table_name];
        $out = '';
        $db = $this->db;
        if($this->is_rpc_call()) $db->query('BEGIN');
        foreach($row2del as $index2del){
            $keys_sql = $this->get_fields_sql($key_names, $index2del, $table_structure);
            $sql = 'DELETE FROM '.$table_name.' WHERE ('.implode(')AND(',$keys_sql).');';
            $out .= '. ';
            //$out .= $sql.'<br>';
            $db->query($sql);
        }
        if($this->is_rpc_call()) $db->query('COMMIT');
        return $out;
    }

    function rpc_update($table_name, $key_names, $col_names, $row2update){
        $table_structure = $this->get_tables_struct(true);
        $table_structure = $table_structure[$table_name];
        $out = '';
        $db = $this->db;
        if($this->is_rpc_call()) $db->query('BEGIN');
        $col_names_sql = $col_names;
        array_push($col_names_sql, $this->update_field);
        foreach($row2update as $index2update=>$data2update){
            $keys_sql = $this->get_fields_sql($key_names, $index2update, $table_structure);
            $datas_sql = $this->get_fields_sql($col_names_sql, $data2update, $table_structure);
            $sql = 'UPDATE '.$table_name.' SET '.implode(',',$datas_sql).' WHERE ('.implode(')AND(',$keys_sql).');';
            $out .= '. ';
            //$out .= $sql.'<br>';
            $db->query($sql);
        }
        if($this->is_rpc_call()) $db->query('COMMIT');
        return $out;
    }

    function rpc_add($table_name, $key_names, $col_names, $row2add){
        $table_structure = $this->get_tables_struct(true);
        $table_structure = $table_structure[$table_name];
        $out = '';
        $db = $this->db;
        if($this->is_rpc_call()) $db->query('BEGIN');
        $col_names_sql = array_merge($key_names, $col_names);
        array_push($col_names_sql, $this->update_field);
        $col_names_sql = $db->safe_var($col_names_sql);
        $sql_common = 'INSERT INTO '.$table_name.' ('.implode(',',$col_names_sql).') VALUES (';
        foreach($row2add as $index2add=>$data2add){
            $keys = explode($this->field_separator,$index2add);
            $datas_array = explode($this->field_separator, $data2add);
            $datas_array = array_merge($keys, $datas_array);
            $datas = [];
            foreach($col_names_sql as $i=>$field_name){
                $data = $datas_array[$i];
                $datas[] = $this->escape_field($field_name, $data, $table_structure);
            }
            $sql = $sql_common.implode(',',$datas).');';
            $out .= '. ';
            //$out .= $sql.'<br>';
            $db->query($sql);
        }
        if($this->is_rpc_call()) $db->query('COMMIT');
        return $out;
    }

    /*function do_sql_escape($array){
        $db = $this->db;
        if(!$db->IsConnected()) $db->connect();
        foreach($array as &$row){
            $row = $db->safe_var($row);
        }
        return $array;
    }*/

    function get_fields_sql($col_names, $col_datas, &$table_structure){
        $db = $this->db;
        if(!$db->IsConnected()) $db->connect();
        $fields_sql = [];
        $datas = explode($this->field_separator, $col_datas);
        foreach($datas as $id=>$data){
            $field_name = $col_names[$id];
            $data = $this->escape_field($field_name, $data, $table_structure);
            $fields_sql[] = $db->safe_var($field_name).'='.$data;
        }
        return $fields_sql;
    }


    private function get_last_sync_by_table($sync_id, $table_name){
        $db = $this->db;
        $this->create_data_sync_table();
        $sql = 'SELECT last_sync_start, last_sync_end FROM '.$this->sync_data_table_name.'
            WHERE table_name="'.$table_name.'" AND sync_id='.$sync_id;
        $res = $db->query($sql);
        $obj = $db->fetch_object($res);
        if (!is_object($obj)){
            return [strtotime('1980-01-01 00:00:00 UTC'), strtotime('1980-01-01 00:00:00 UTC')];
        }else{
            return [strtotime($obj->last_sync_start.' UTC'), strtotime($obj->last_sync_end.' UTC')];
        }
    }

    private function set_last_sync_for_table($sync_id, $table_name, $sync_start_ts, $sync_end_ts){
        $db = $this->db;
        $this->create_data_sync_table();
        $start_date = gmdate('Y-m-d H:i:s', $sync_start_ts);
        $end_date = gmdate('Y-m-d H:i:s', $sync_end_ts);
        $sql = 'INSERT INTO '.$this->sync_data_table_name.'
            (table_name, sync_id, last_sync_start, last_sync_end)
            VALUES
            ("'.$table_name.'",'.$sync_id.',"'.$start_date.'", "'.$end_date.'")
            ON DUPLICATE KEY UPDATE last_sync_start="'.$start_date.'", last_sync_end="'.$end_date.'";';
        $db->query($sql);
    }

    private function create_data_sync_table(){
        $db = $this->db;
        $sql = 'CREATE TABLE IF NOT EXISTS '.$this->sync_data_table_name.'
            (table_name CHAR(50), sync_id TINYINT, last_sync_start DATETIME,
            last_sync_end DATETIME,
            UNIQUE tns (table_name, sync_id));';
        $db->query($sql);
    }

    function no_rpc(){
        //Default no_rpc, can be override
        if (isset($_GET['do_sync'])&&isset($_GET['slst_file'])){
            $incremental = false;
            if(isset($_GET['incremental'])&&(int)$_GET['incremental']==1){
                $incremental = true;
            }
            if (isset($_GET['only_table']) && strlen(trim($_GET['only_table']))>0){
                $only_table = trim($_GET['only_table']);
            }else{
                $only_table = null;
            }
            echo '<h1>Doing sync</h1>';
            echo 'Started at: '.date('Y-m-d H:i:s').'<hr>';
            $start = microtime(true);
            $this->sync($_GET['slst_file'], $incremental, $only_table);
            $finish = microtime(true);
            echo '<hr>Finished at: '.date('Y-m-d H:i:s');
            printf('<hr>%.3f seconds',$finish-$start);
        }else{
            echo "<table width='100%'><tr><td align='center'>";
            echo '<h1>Sync</h1>';
            echo "<form action='' method='GET'>";
            echo "<select name='slst_file'>";
            foreach($this->get_slst() as $slst_file){
                echo "<option value='".$slst_file."'>".$slst_file."</option>";
            }
            echo "</select> ";
            echo " <input type='checkbox' name='incremental' value='1'>Incremental</input><br><br>";
            echo "Only table: <input type='text' name='only_table'><br><br>";
            echo "<input type='submit' name='do_sync' value='Go'>";
            echo "</form>";
            echo "</td></tr></table>";
        }
    }

    private function get_slst(){
        if (!is_null($this->only_slst_filename)) return [$this->only_slst_filename];
        $slst_files = [];
        $files = scandir(getcwd());
        foreach($files as $filename){
            $file_name_exploded = explode('.',$filename);
            if (strtolower(array_pop($file_name_exploded))=='slst'){
                $slst_files[] = $filename;
            }
        }
        return $slst_files;
    }
}
