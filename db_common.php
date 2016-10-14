<?php
/*****************************************************************************
 *
 *		 Db Class
 *		 ---------------
 *
 *		 Unification for Database
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
#require_once("htmlMimeMail5/htmlMimeMail5.php");

class zyfra_db_query{
    var $sql;
    var $start;
    var $duration=false;
    
    function __construct($sql){
        $this->sql = $sql;
        $this->start = microtime(true);
    }
    
    function stop(){
        if ($this->duration === false) $this->duration = microtime(true) - $this->start;
    }
}

class zyfra_db_common {
    protected $link=false;
    protected $db_selected=false;
    protected $page_nr=0;
    var $nb_rows=0;
    var $nb_pages=0;
    var $nb_row_per_page=0;
    var $nb_query=0;
    var $query_time=0;
    var $last_query="";
    var $last_query_datas;
    var $result;
    var $errors2mail='';
    var $log=false;
    var $queries=null;

    function __construct(){
        $last_query_datas = array();
        $this->queries = array();
    }

    protected function IsConnected(){
        if($this->link==false) return false;
        return true;
    }

    protected function IsDBSelected(){
        if($this->db_selected==false) return false;
        return true;
    }

    public function query($sql){
        $nb_queries = count($this->queries);
        if ($nb_queries) $this->queries[$nb_queries-1]->stop();
        $this->queries[] = new zyfra_db_query($sql);
        $this->nb_query++;
        if ($this->log) echo $sql."<br>\n";
    }

    protected function pre_query(){
        if (!$this->IsConnected()){
            if (!$this->connect()){
                echo "Can't connect to database";
                return false;
            }
        }

        if (!$this->IsDBSelected()){
            if (!$this->select_db()){
                echo "Can't select database";
                return false;
            }
        }
        return true;
    }

    public function get_array($sql,$key='',$value='', $datas=array()){
        if (is_string($sql)){
            $result = $this->safe_query($sql, $datas);
        }else{
            $result = $sql;
        }
        $temp = array();
        if ($value == ''){
          if ($key == ''){
            while($row = $this->fetch_array($result)){
              $temp[]=$row[0];
            }
          }else{
            while($row = $this->fetch_object($result)){
                $temp[]=$row->{$key};
            }
          }
        }else{
            if ($key == ''){
                while($row = $this->fetch_object($result)){
                    $temp[]=$row->{$value};
                }
            }else{
                while($row = $this->fetch_object($result)){
                    $temp[$row->{$key}]=$row->{$value};
                }
            }
        }
        $this->free_result($result);
        return $temp;
    }

    public function get_array_object($sql,$idRow='', $datas=array()){
        if (is_string($sql)){
            $result = $this->safe_query($sql, $datas);
        }else{
            $result = $sql;
        }

        $resultArray = array();
        while($a = $this->fetch_object($result)){
            if($idRow!=""){
                $resultArray[$a->{$idRow}] = $a;
            }else{
                $resultArray[] = $a;
            }
        }
        $this->free_result($result);
        return $resultArray;
    }

    public function get_nb_query(){
        return $this->nb_query;
    }

    public function get_pages_link($url,$separator="&nbsp;&nbsp;",$next_prev=true){
        /*if(stripos($url,"?") === false){
         $url .= "?";
         }else{
         $url .= "&";
         }*/
        if($this->nb_pages==1) return "";
        $content = "";
        $is_first = true;
        for($i=1;$i<$this->nb_pages+1;$i++){
            if (!$is_first) $content .= $separator;
            if ($i==$this->page_nr){
                $content.="<b>".$i."</b>";
            }
            elseif($i==1){
                $content.="<a href='".$url.".html'>".$i."</a>";
            }else{
                $content.="<a href='".$url."--".$i.".html'>".$i."</a>";
            }
            $is_first=false;
        }
        if ($content!="") $content = "[&nbsp;".$content."&nbsp;]";
        if (($this->nb_pages>1)&&($next_prev)){
            //On ajoute next et prev
            //Check is first
            if($this->page_nr>2){
                $content = "<a href='".$url."--".($this->page_nr-1).".html'>__L_prev_page__</a>&nbsp;&nbsp;".$content;
                if ($this->page_nr>2){
                    //$content = "<a href='".$url."&page_nr=1'>&lt;&lt;</a> ".$content;
                }
            }elseif($this->page_nr==2){
                $content = "<a href='".$url.".html'>__L_prev_page__</a>&nbsp;&nbsp;".$content;
            }
            if($this->page_nr<$this->nb_pages){
                $content .= "&nbsp;&nbsp;<a href='".$url."--".($this->page_nr+1).".html'>__L_next_page__</a>";
                if ($this->page_nr<$this->nb_pages-1){
                    //$content .= " <a href='".$url."&page_nr=".($this->nb_pages)."'>&gt;&gt;</a>";
                }
            }
        }
        //if ($this->nb_pages<2) $content = "";
        return $content;
    }

    protected function show_error($the_query,$err_no=0,$err=""){
        global $security;
        $log_filename = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zyfra_db_log.txt';

        $sql="<table bgcolor='grey' style='color:black;'>";
        $sql_table = explode("\n",$the_query);
        foreach($sql_table as $no_line=>$line){
            $sql.="<tr><td bgcolor='orange' align='right'>".($no_line+1)."</td><td>".$line."</td></tr>";
        }
        $sql .= "</table>";
        $debug = debug_backtrace();
        //reverse
        $debug = array_reverse($debug);
        //On retire ce qui est relatif � cette classe
        array_pop($debug);
        //array_pop($debug);
        $loca = "<table bgcolor='grey'>";
        $i = 1;
        foreach($debug as $debug_line){
            if (!isset($debug_line["class"])) $debug_line["class"] = '';
            if (!isset($debug_line["type"])) $debug_line["type"] = '';
            $loca .= "<tr><td bgcolor='orange'>".$i."</td><td>".$debug_line["class"].$debug_line["type"].$debug_line["function"]."() from ".$debug_line["file"]." at line ".$debug_line["line"]."</td></tr>";
            $i++;
        }
        $loca .= "</table>";
        $the_html_error= "<table border='1' cellspacing='0'><tr bgcolor='#FF0000'><td>MySQL Error</td></tr>
					<tr><td>SQL:<BR>".$sql."</td></tr><tr><td>
					Error : ".$err_no." : ".$err."</td></tr><tr><td>".$loca."</td></tr></table>";
        
        $f = fopen($log_filename, 'a');
        $fwrite($f, $the_html_error."\n");
        fclose($f);
        if (!isset($security) || $security->is_dev()){
            echo $the_html_error;
            throw new Exception($the_html_error);
        }
        $this->errors2mail .= $the_html_error;
    }

    public function get_object($sql, $datas = array()){
        if($sql!=$this->last_query || $this->last_query_datas!=$datas){
            $this->result=$this->safe_query($sql, $datas);
            $this->last_query = $sql;
        }
        $obj = $this->fetch_object($this->result);
        if(!$obj) {
            $this->last_query = "";
            $this->free_result($this->result);
        }
        return $obj;
    }

    public function safe_var($data){
        //Counter-injection function
        if (is_array($data)){
            $res = array();
            foreach($data as $key=>$value){
                $res[$key] = $this->safe_var($data);
            }
            return $res;
        }
        if(is_string($var)) return addslashes($data);
        return $data;
    }

    public function var2sql($var, $safe=false){
        if ($var === null) return 'null';
        if (is_array($var)){
            if(count($var) == 0) throw new Exception('Array parameter is empty !');
            if(!$safe) $var = $this->safe_var($var);
            foreach($var as $k=>$v){
                $var[$k] = $this->var2sql($v, true);
            }
            return '('.implode(',', $var).')';
        }elseif(is_string($var)){
            if(!$safe) $var = $this->safe_var($var);
            return "'".$var."'";
        }
        return $var;
    }

    public function safe_sql($sql, $datas = array()){
        /*
         * Safe sql against Database (avoid SQL injection)
         * @param $sql: SQL statement
         * @param $datas: array of datas
         *
         * Ie:
         * safe_sql('select a from b where c=%s and e in %s and t=%s', array(4, array(4,5,7), 'ha'))
         * result in
         * 'select a from b where c=4 and e in (4,5,7) and t=\'ha\''
         */
        $sql_array = explode('%s', $sql);
        $new_sql = array_shift($sql_array);
        $nb_var = count($sql_array);
        if (count($datas) < $nb_var){
            throw new Exception('Not enough datas for SQL. '."\n".$sql."\n".print_r($datas, true)."\n");
        }
        for($i=0; $i<$nb_var; $i++){
            $data = array_shift($datas);
            $new_sql .= $this->var2sql($data).array_shift($sql_array);
        }
        return $new_sql;
    }

    public function safe_query($sql, $datas = array()){
        /*
         * Safe query against Database (avoid SQL injection)
         * @param $sql: SQL statement
         * @param $datas: array of datas
         *
         * Ie:
         * safe_query('select a from b where c=%s and e in %s and t=%s', array(4, array(4,5,7), 'ha'))
         * result in
         * query('select a from b where c=4 and e in (4,5,7) and t=\'ha\'');
         */
        $new_sql = $this->safe_sql($sql, $datas);
        return $this->query($new_sql);
    }
     
    public function __destruct(){
        /*if (strlen($this->errors2mail)>0){
         //Email the error to webmaster@matedex.be
         $mailError = new htmlMimeMail5();
         $mailError->setFrom("website@matedex.be");
         $mailError->setSubject("Matedex web db error");
         $mailError->setHTML($this->errors2mail);
         $mailError->send(array("webmaster@matedex.be"));
         }*/
    }

    //Compatibility
    public function get_row_object($sql){
        return $this->get_object($sql);
    }

    public function getArrayObject($sql,$idRow=""){
        //Compatibility
        return $this->get_array_object($sql, $idRow);
    }

    public function safeVar($data){
        return $this->safe_var($data);
    }
}

?>