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

class zyfra_db_common {
  var $link=false;
  var $db_selected=false;
  var $page_nr=0;
  var $nb_rows=0;
  var $nb_pages=0;
  var $nb_row_per_page=0;
  var $nb_query=0;
  var $query_time=0;
  var $last_query="";
  var $result;
  var $errors2mail='';
  
  function IsConnected(){
	if($this->link==false) return false;
	return true;
  }
  
  function IsDBSelected(){
	if($this->db_selected==false) return false;
	return true;
  }
  
  function pre_query(){
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
  
  function get_array($sql,$key,$value){
    $result = $this->query($sql);
    $temp = array();
    while($row = $this->fetch_object($result)){
      $temp[$row->{$key}]=$row->{$value};
    }
    $this->free_result($result);
    return $temp;
  }
  
  function get_nb_query(){
    return $this->nb_query;
  }
  
  function get_pages_link($url,$separator="&nbsp;&nbsp;",$next_prev=true){
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
  
  function show_error($the_query,$err_no=0,$err=""){
          global $security;
          
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
            $loca .= "<tr><td bgcolor='orange'>".$i."</td><td>".$debug_line["class"].$debug_line["type"].$debug_line["function"]."() from ".$debug_line["file"]." at line ".$debug_line["line"]."</td></tr>";
            $i++;
          }
          $loca .= "</table>";
          $the_html_error= "<table border='1' cellspacing='0'><tr bgcolor='#FF0000'><td>MySQL Error</td></tr>
					<tr><td>SQL:<BR>".$sql."</td></tr><tr><td>
					Error : ".$err_no." : ".$err."</td></tr><tr><td>".$loca."</td></tr></table>";
          if (isset($security)){
              if($security->is_admin()) echo $the_html_error;    
          }else{
              echo $the_html_error;
          }
					$this->errors2mail .= $the_html_error;          
        }
  
  function get_row_object($sql){
    if($sql!=$this->last_query){
	  	$this->result=$this->query($sql);
	  	$this->last_query = $sql;
		}
		$a = $this->fetch_object($this->result);
		if(!$a) {
		  $this->last_query = "";
		  $this->free_result($this->result);
		}
		return $a;
  }
  
  function getArrayObject($sql,$idRow=""){
  	$this->last_query = "";
  	$this->result = $this->query($sql);
  	$resultArray = array();
  	while($a = $this->fetch_object($this->result)){
  		if($idRow!=""){
  			$resultArray[$a->{$idRow}] = $a;
  		}else{
  			$resultArray[] = $a;	
  		}
  	}
  	$this->free_result($this->result);
  	return $resultArray;  	
  }
  
  function safeVar($data){
      //Counter-injection function
      return addslashes($data);
	}	
   
  function __destruct(){
      /*if (strlen($this->errors2mail)>0){
          //Email the error
		      $mailError = new htmlMimeMail5();
		      $mailError->setFrom("email@myhost.tld");
		      $mailError->setSubject("Myhost web db error");
		      $mailError->setHTML($this->errors2mail);
		      $mailError->send(array("email@myhost.tld"));
      }*/
  }
}

?>