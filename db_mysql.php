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

include_once "db_common.php";
class zyfra_mysql extends zyfra_db_common {
    var $host = "localhost";
    var $user = "user";
    var $password = "password";
    var $default_db = "my_db";
    var $db_selected=false;


    function ver(){
        return "0.01";
    }

    function IsConnected(){
        if($this->link==false) return false;
        return true;
    }

    function IsDBSelected(){
        if($this->db_selected==false) return false;
        return true;
    }

    function TableExists($tablename) {
        if (!$this->IsConnected()){
            if (!$this->connect()){
                echo "Can't connect to database";
                return false;
            }
        }
        $result = $this->query("SHOW TABLES LIKE '".$tablename."'");
        if(mysql_num_rows($result)==1) return true;
        else return false;
    }

    function query($the_query,$nb_row_per_page=0)	{
        $start_query = microtime(true);
        if (!$this->pre_query()) return false;
        if ($nb_row_per_page!=0){
            //On limite le nombre de r�sultat et on calcul les pages
            $page_nr = 1;
            if(isset($_GET["page_nr"])){
                $page_nr = (int)$_GET["page_nr"];
            }
            $this->nb_row_per_page = $nb_row_per_page;
            $this->page_nr = $page_nr;
            $offset = ($page_nr-1)*$nb_row_per_page;
            $nb_rows = $this->get_nb_row_query($the_query);
            $this->nb_rows = $nb_rows;
            $the_query .= " LIMIT ".$offset.",".$nb_row_per_page;
            $nb_pages = ceil($nb_rows / $nb_row_per_page);
            $this->nb_pages = $nb_pages;
        }
        $result = mysql_query($the_query);
        //$this->log_query();
        $this->nb_query++;
        if(!$result){
            $this->show_error($the_query,mysql_errno(),mysql_error());
        }
        $this->query_time += microtime(true)-$start_query;
        return $result;
    }

    function query_a($the_query,$the_col,$first_letter="")	{
        /* Fonction pour les query, tri�e par ordre alphab�tique, et s�par�e par premi�re lettre.
         *  $the_col = nom de la colonne soumise au tri
         *  $first_letter = premi�re lettre de la colonne.
         *************************************************/
        $nb_row_per_page = 1;
        $start_query = microtime(true);
        if (!$this->pre_query()) return false;
        // 1. On r�cup les lettres
        $sql = "";
        if ($nb_row_per_page!=0){
            //On limite le nombre de r�sultat et on calcul les pages
            $page_nr = 1;
            if(isset($_GET["page_nr"])){
                $page_nr = $_GET["page_nr"];
            }
            $this->nb_row_per_page = $nb_row_per_page;
            $this->page_nr = $page_nr;
            $offset = ($page_nr-1)*$nb_row_per_page;
            $nb_rows = $this->get_nb_row_query($the_query);
            $this->nb_rows = $nb_rows;
            $the_query .= " LIMIT ".$offset.",".$nb_row_per_page;
            $nb_pages = ceil($nb_rows / $nb_row_per_page);
            $this->nb_pages = $nb_pages;
        }
        $result = mysql_query($the_query);
        //$this->log_query();
        $this->nb_query++;
        if(!$result){
            $this->show_error($the_query,mysql_errno(),mysql_error());
        }
        $this->query_time += microtime(true)-$start_query;
        return $result;
    }

    function get_total_query_time(){
        return $this->query_time;
    }

    function log_query(){
        global $console;
        $debug = debug_backtrace();
        //reverse
        $nb = count($debug)-3;
        for($i=0;$i<$nb;$i++){
            array_pop($debug);
        }
        $debug = array_reverse($debug);
        //On retire ce qui est relatif � cette classe
        array_pop($debug);
        $loca = "";
        foreach($debug as $debug_line){
            if(isset($debug_line["class"]))
            $loca  .= $debug_line["class"];
            if(isset($debug_line["type"]))
            $loca  .= $debug_line["type"];
            $loca  .= $debug_line["function"]."()|";
            $file = $debug_line["file"];
            $line = $debug_line["line"];
        }
        $file = array_pop(explode("\\",$file));
        $loca .= "from ".$file." at line ".$line;
        $console->add("SQL query from",$loca);
    }



    function connect($host="",$user="",$password=""){
        if (!$this->IsConnected()){
            if ($host=="") $host = $this->host;
            if ($user=="") $user = $this->user;
            if ($password=="") $password = $this->password;
            //echo $host." ".$user." ".$password;
            $this->link = mysql_connect($host,$user,$password);
            if (!$this->IsConnected()){
                echo "Error : Can't connect to database.<br>";
                return false;
            }
        }
        return true;
    }

    function fetch_object($result){
        if (!$result) return false;
        return mysql_fetch_object($result);
    }

    function fetch_array($result){
        if (!$result) return false;
        return mysql_fetch_array($result);
    }

    function close(){
        if ($this->IsConnected()){
            mysql_close($this->link);
            $this->link = false;
        }
    }

    function result_all($result) {
        if (!$result) return false;
        echo '<table>';
        $nb_fields = mysql_num_fields($result);
        for($i = 0; $i < $nb_fields; $i++) {
            echo '<th>';
            echo mysql_field_name($result,$i);
            echo '</th>';
        }
        while($row = mysql_fetch_array($result)) {
            echo '<tr>';
            for($i = 0; $i < $nb_fields; $i++) {
                echo '<td>'.$row[$i].'</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    }

    function select_db($db=""){
        if ($db=="") $db = $this->default_db;
        return mysql_select_db($db);
        //or die("Impossible de choisir la db ".$db."!");
    }

    function num_rows($result){
        if (!$result) return 0;
        return mysql_num_rows($result);
    }

    function free_result($result){
        if (!$result) return 0;
        return mysql_free_result($result);
    }

    function insert_id(){
        if ($this->link) return mysql_insert_id($this->link);
        return false;
    }



    function get_nb_row_query($sql){
        /*$tag = "/select|from|where|left join|into|group by|having|order by|limit|procedure/i";
         $unusable_tag = "/straight_join|sql_small_result|sql_big_result|sql_buffer_result|sql_cache|sql_no_cache|sql_calc_found_rows|high_priority_distinct|distinctrow|all|for update|lock in share mode/i";
         $sql_a = preg_replace($unusable_tag,"",$sql);
         $data = preg_split($tag,$sql_a);
         preg_match_all ($tag, $sql_a,$data2);
         $sql_e = "SELECT count(*) as nb_row ";
         foreach($data2[0] as $key=>$the_tag){
         switch(strtolower($the_tag)){
         case  "from":
         case  "group by":
         case  "where":
         $sql_e .= $the_tag." ".$data[$key+1];
         break;
         }
         }
         echo "<pre>";
         print_r($sql_e);
         echo "</pre>";
         $result = $this->query($sql_e);
         $row = $this->fetch_object($result);
         $this->free_result($result);*/
        $result = $this->query($sql);
        return mysql_num_rows($result);
        //return $row->nb_row;
    }

    function get_pages_link($url,$separator="&nbsp;&nbsp;",$next_prev=true){
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
            }elseif($this->page_nr==2){
                $content = "<a href='".$url.".html'>__L_prev_page__</a>&nbsp;&nbsp;".$content;
            }
            if($this->page_nr<$this->nb_pages){
                $content .= "&nbsp;&nbsp;<a href='".$url."--".($this->page_nr+1).".html'>__L_next_page__</a>";
            }
        }
        return $content;
    }

    function insert_where($sql,$the_where){
        $sql = strtolower($sql);
        list($before_group,$after_group) = explode("group by ",$sql);
        $sql_end = $before_group. " where ".$the_where;
        if (trim($after_group)!="") $sql_end .= " group by ".$after_group;
        return $sql_end;
    }

    function __destruct(){
        if($this->IsConnected()){
            mysql_close($this->link);
        }
    }

    function explode_query_in_array($sql){
        $sql_struct = array();
        $sql_struct[] = "SELECT";
        $sql_struct[] = "FROM";
        $sql_struct[] = "LEFT JOIN";
        $sql_struct[] = "RIGHT JOIN";
        $sql_struct[] = "JOIN";
        $sql_struct[] = "WHERE";
        $sql_struct[] = "GROUP BY";
        $sql_struct[] = "ORDER BY";
        $sql_struct[] = "LIMIT";
    }

    function safe_var($data){
        //Counter-injection function
        if (is_array($data)){
            $res = array();
            foreach($data as $key=>$value){
                $res[$key] = $this->safe_var($value);
            }
            return $res;
        }
        if(is_string($data)) return addslashes($data);
        return $data;
    }

    function safeVar($data){
        //Counter-injection function
        return mysql_real_escape_string($data);
    }
}
$MySQL = new zyfra_mysql;
?>
