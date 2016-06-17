<?php
/*****************************************************************************
*
*		 debug Class
*		 ---------------
*
*		 Debug functions
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

/*
 * Usage:
 * zyfra_debug::printr('My text'); 
 */
 
class zyfra_debug{
    static function printr($var){
        echo '<pre>';
        print_r($var);
        echo '</pre>';
        self::flush();           
    }
    
    static function _print($txt){
        print($txt);
        self::flush();
    }
    
    static function flush(){
        if (ob_get_length()){
            ob_flush();
        }
        flush();
    }
    
    static function print_backtrace($table=true, $avoid_from_self=false){
        $bt = debug_backtrace();
        $src = array_shift($bt);
        if($avoid_from_self){
            $src_file = $src['file'];
            foreach($bt as $t){
                if ($t['file'] == $src_file) return;
            }
        }
        echo 'Backtrace in '.$src['file'].' line '.$src['line'];
        echo $table?"<br/><table bgcolor='grey'>":'<pre>';
        $nbt = count($bt);
        foreach($bt as $t){
            echo ($table?"<tr bgcolor='#FFDDBB'><td>":'').$nbt--.'. '.($table?'</td><td>':'').$t['function'].'(';
            if (isset($t['args'])){
                $args = $t['args'];
                foreach($args as $key=>$value){
                    if (is_object($value)){
                        echo '-obj-';
                    }elseif(is_array($value)){
                    	echo '{';
                    	$first = true;
                    	foreach($value as $k=>$v){
                    		if (!$first) echo ', ';
                    		$first=false;
                    		if (is_string($k)) $k = "'".$k."'";
                    		if (is_string($v)) $v = "'".$v."'";
                    		if (is_object($v)) $v = '-obj-';
                    		echo $k.': '.$v;
                    	}
                    	echo '}';
                    }else{
                        echo $value;
                    }
                    if ($key < count($args)-1) echo ',';
                }
            }
            echo ')'.($table?'</td><td>':'').' in '.$t['file'].' line '.$t['line'].($table?'</td></tr>':"\n");
        }
        echo $table?'</table>':'</pre>';
    }

    static function print_set($title, $content){
        echo '<fieldset style=\'background:#EEE\';><legend style=\'background:#CCC\'><b>'.$title.'</b></legend>'.$content.'</fieldset>';
    }
    
    static function depreciated_function(){
        if (E_WARNING & error_reporting()){
            $back_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
            //print_r($back_trace);
            echo '<b>Warning:</b> <b>'.$back_trace['function'].'</b> is a obsolete function. in <b>'.$back_trace['file'].'</b> on line <b>'.$back_trace['line'].'</b><br>';
        }
    }
}
?>