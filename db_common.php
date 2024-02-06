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
    var $backtrace = null;
    
    function __construct($sql, &$backtrace=null){
        $this->sql = $sql;
        $this->start = microtime(true);
        $this->backtrace = &$backtrace;
    }

    function stop($force_update=false){
        if ($this->duration === false || $force_update) $this->duration = microtime(true) - $this->start;
    }
}

class zyfra_db_common {
    protected $link=false;
    protected $db_selected=false;
    protected $page_nr=0;
    protected $error_callback=null;
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
    public $debug=false;

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
        if ($this->debug){
            $backtrace = debug_backtrace();
            array_pop($backtrace);
        }else{
            $backtrace = null;
        }
        $this->queries[] = new zyfra_db_query($sql, $backtrace);
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
        end($this->queries)->stop(true); //Update full time query
        return $temp;
    }

    public function get_array_object($sql,$idRow='', $datas=[]){
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
        end($this->queries)->stop(true); //Update full time query
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
    
    public function render_backtrace(&$traceback){
        //$res = "<table bgcolor='grey'>";
        $res = '<ol>';
        $i = 1;
        foreach($traceback as $line){
            if (!isset($line["class"])) $line["class"] = '';
            if (!isset($line["type"])) $line["type"] = '';
            //$res .= "<tr><td bgcolor='orange'>".$i."</td><td>".$line["class"].$line["type"].$line["function"]."() from ".$line["file"]." at line ".$line["line"]."</td></tr>";
            $res .= "<li>".$line["class"].$line["type"].$line["function"]."() from ".$line["file"]." at line ".$line["line"]."</li>";
            $i++;
        }
        //$res .= "</table>";
        $res .= "</ol";
        return $res;
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
        $backtrace = debug_backtrace();
        //reverse
        $backtrace = array_reverse($backtrace);
        //On retire ce qui est relatif � cette classe
        array_pop($backtrace);
        //array_pop($debug);
        $loca = $this->render_backtrace($backtrace);
        $the_html_error= "<table border='1' cellspacing='0'><tr bgcolor='#FF0000'><td>MySQL Error</td></tr>
					<tr><td>SQL:<BR>".$sql."</td></tr><tr><td>
					Error : ".$err_no." : ".$err."</td></tr><tr><td>".$loca."</td></tr></table>";
        
        /*$f = fopen($log_filename, 'a');
        $fwrite($f, $the_html_error."\n");
        fclose($f);*/
        if (!isset($security) || $security->is_dev()){
            echo $the_html_error;
            throw new Exception($the_html_error);
        }
        if (!is_null($this->error_callback)) call_user_func($this->error_callback, $the_html_error);
        $this->errors2mail .= $the_html_error;
    }
    
    public function set_error_callback($callback){
        $this->error_callback = $callback;
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

    public function safe_sql($sql, $datas=[]){
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
        $that = $this;
        $datas_origin = $datas; // Copy
        $sql = preg_replace_callback('/(?<!%)%s/', function($matches) use(&$that, &$datas, &$sql, &$datas_origin){
            if (count($datas) == 0) throw new Exception('Not enough datas for SQL. '."\n".$sql."\n".print_r($datas_origin, true)."\n");
            $data = array_shift($datas);
            return $that->var2sql($data);
        }, $sql);
        return str_replace('%%','%', $sql);
    }

    public function safe_query($sql, $datas=[]){
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
        if (count($datas)) $sql = $this->safe_sql($sql, $datas);
        return $this->query($sql);
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