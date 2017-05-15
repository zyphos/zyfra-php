<?php
/*****************************************************************************
*
*    Template Class
*    ---------------
*
*    Class to manage template 
*
*    Copyright (C) 2011 De Smet Nicolas (<http://ndesmet.be>).
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
* 
* $tpl = new PhpTemplate('myfirsttemplate');
* $tpl->set_template_path(dirname(__FILE__).'/templates/');
* $tpl->assign('a', 45);
* $tpl->assign(array('b'=>'c',
* 					 'd'=>7.45
* 			   ));
* echo $tpl->fetch();
* 
* myfirsttemplate.php
* a=
* <?php
* 	echo $a;
* ?><br>
* b=<?=$b?><br>
* d=<?=$d?>
* <?php require($_tpl_path.'subtemplate.php');?>
* 
*****************************************************************************/

require_once('debug.php');

function remove_accent($str){
    //return strtr($chaine,
    //   'àâäåãáÂÄÀÅÃÁæÆçÇéèêëÉÊËÈïîìíÏÎÌÍñÑöôóòõÓÔÖÒÕùûüúÜÛÙÚÿ',
    //   'aaaaaaaaaaaaaacceeeeeeeeiiiiiiiinnoooooooooouuuuuuuuy');
    $str = htmlentities($str, ENT_COMPAT, 'UTF-8');
    $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde|cedil);/', '$1',$str);
    $str = str_replace("&amp;"," and ",$str);
    $str = str_replace("&trade;","",$str);
    $str = str_replace("&deg;","d",$str);
    $str = str_replace("&oslash;","d",$str);
    $str = str_replace("&sup2;","2",$str);
    $str = str_replace("&reg;","",$str);
    return html_entity_decode($str, ENT_COMPAT, 'UTF-8');
}

function text2url($txt){
    if (strlen($txt)==0) return '';
    $txt = remove_accent($txt);
    $txt = strtolower($txt);
    /*echo htmlentities(htmlentities($txt, ENT_COMPAT, 'UTF-8'))."\n";
    for ($i=0; $i < strlen($txt);$i++){
    	$c = $txt[$i];
    	echo '['.$c.'] '.ord($c)."\n";
    }//*/
    $txt = str_replace(array('(',')','"'),'', $txt);
    $txt = str_replace('%','pc', $txt);
    $txt = str_replace(array(' ',',',"'",'/',':','<','>'),'-', $txt);
    $txt = preg_replace('/-+/', '-', $txt);
    return $txt[strlen($txt)-1]=='-'?substr($txt, 0, strlen($txt)-1):$txt;
}

function html($var, $nl2br=false){
    $var = htmlentities($var, ENT_COMPAT, 'UTF-8');
    $var = str_replace(chr(153),'<sup>TM</sup>', $var);
    if ($nl2br){
        $var = str_replace(chr(10), '<br>', $var);
    }
    return $var;
}

function htmlquotes($var){
    return htmlentities($var,ENT_QUOTES, 'UTF-8');
}

function html_value($var){
    return htmlspecialchars($var, ENT_QUOTES);
}

function accent2html($str){
	//return strtr($chaine,
	//   'àâäåãáÂÄÀÅÃÁæÆçÇéèêëÉÊËÈïîìíÏÎÌÍñÑöôóòõÓÔÖÒÕùûüúÜÛÙÚÿ',
	//   'aaaaaaaaaaaaaacceeeeeeeeiiiiiiiinnoooooooooouuuuuuuuy');
	$str = htmlentities($str, ENT_QUOTES, 'UTF-8');
	$str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde|cedil);/', '&amp;$1$2;',$str);
	//$var = str_replace(chr(153),'<sup>TM</sup>', $var);
	$str = str_replace(chr(153),'&amp;trade;', $str);
	$str = str_replace('&reg;', '&amp;reg;', $str);
	return html_entity_decode($str);
}

$_cycle_datas = array();
function cycle(){
    global $_cycle_datas;
    $args = func_get_args();
    if (count($args) == 1){
        $name = $args[0];
        $args = explode(',',$name);
    }else{
        $name = implode($args);
    }
    $nb_args = count($args);
    if(array_key_exists($name, $_cycle_datas)){
        $id = $_cycle_datas[$name] + 1;
        if ($id >= $nb_args) $id=0;
    }else{
        $id = 0;
    }
    $_cycle_datas[$name] = $id;
    return $args[$id];
}

function html_select($name, $values, $selected=null, $attribute=null){
    $res = '<select name="'.$name.'">';
    foreach($values as $key=>$value){
        if (!is_null($attribute)) $value = $value->{$attribute};
        $res .= '<option value="'.$key.'"'.($key==$selected?' selected':'').'>'.html($value).'</option>';
    }
    return $res.'</select>';
}

class zyfra_template{
    private $template;
    protected $template_path = '';
    private $vars = array();
    
    function __construct($template, $stack_level=1){
        $this->template = $template;
        $template_file = $this->get_template_file();
        if (!file_exists($template_file)) zyfra_debug::show_warning('Template <b>'.$template_file.'</b> not found.', $stack_level);
    }
    
    public function set_template_path($template_path){
        $this->template_path = $template_path;
        return $this;
    }
       
    public function get_template_file(){
        return $this->template_path.$this->template.'.php';
    }
    
    public function get_template_filename(){
        return $this->template.'.php';
    }
    
    public function set($var_name, $value=''){
        if(is_array($var_name)){
            $this->vars = array_merge($this->vars, $var_name);
        }else{
            $this->vars[$var_name] = $value;
        }
        return $this;
    }
    
    public function assign($var_name, $value=''){ // Used for backward compatibility
        zyfra_debug::depreciated_function();
        return $this->set($var_name, $value);
    }
    
    public function fetch(){
        ob_start();
        extract($this->vars);
        $_tpl_path = $this->template_path;
        require $this->get_template_file();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
?>