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
    
    function print_backtrace(){
        $bt = debug_backtrace();
        array_shift($bt);
        echo '<pre>';
        $nbt = count($bt);
        foreach($bt as $t){
            echo $nbt--.'. '.$t['function'].'(';
            if (isset($t['args'])){
                $args = $t['args'];
                foreach($args as $key=>$value){
                    if (is_object($value)){
                        echo '-obj-';
                    }else{
                        echo $value;
                    }
                    if ($key < count($args)-1) echo ',';
                }
            }
            echo ') in '.$t['file'].' line '.$t['line']."\n";
        }
        echo '</pre>';
    }
    
    static function print_set($title, $content){
        echo '<fieldset style=\'background:#EEE\';><legend style=\'background:#CCC\'><b>'.$title.'</b></legend>'.$content.'</fieldset>';
    }
}
?>