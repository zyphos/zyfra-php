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
* 
*****************************************************************************/

function text2url($txt){
    $txt = remove_accent($txt);
    $txt = strtolower($txt);
    return str_replace(array(' ',','),'-', $txt);
}

function html($var){
    return htmlentities($var);
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

class zyfra_template{
    private $template;
    protected $template_path;
    private $vars;
    
    function __construct($template){
        $this->template = $template;
        $this->vars = array();
        $this->template_path='';
    }
    
    function set_template_path($template_path){
        $this->template_path = $template_path;
    }
       
    function get_template_file(){
        return $this->template_path.$this->template.'.php';
    }
    
    function assign($var_name, $value=''){
        if(is_array($var_name)){
            $this->vars = array_merge($this->vars, $var_name);
        }else{
            $this->vars[$var_name] = $value;
        }
    }
    
    function fetch(){
        ob_start();
        foreach($this->vars as $var_name=>$value){
            $$var_name = $value;
        }
        require $this->get_template_file();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
?>