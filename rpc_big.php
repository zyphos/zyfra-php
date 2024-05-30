<?php 
/*****************************************************************************
*
*		 RPC big Class
*		 ---------------
*
*		 Class that provide Remote Procedure Call with big data parameters.
*		 All datas are crypted.
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
* 
* Server:
* zyfra_rpc_big::set_crypt_key('my secret key');
* class MyClass extends zyfra_rpc_big{
* 	function __construct(){
* 		parent::__construct();
* 	}
* 
* 	function rpc_myFx($a, $b ,$c){
* 		return $a + $b + $c;
* 	}
* 
*   function no_rpc(){
*   	echo "No RPC !";
*   }
* }
* 
* Client:
* zyfra_rpc_big::set_crypt_key('my secret key');
* $result = zyfra_rpc_big::send_rpc($server_url, 'myFx', array($a, $b, $c));
* 
* Multi-query:
* $result = zyfra_rpc_big::send_rpc($server_url, array(array('myFx', array($a, $b, $c)), array('fx2', NULL));
* $result[0] = MyFx result
* $result[1] = fx2 result
* 
* if $server_url is an object, the request will be send to the script itself.
* 
* the no_rpc method is called if there isn't any rpc call asked.
*
*****************************************************************************/

/*****************************************************************************
* Revisions
* ---------
* 
* v0.01	23/10/2009	Creation
*****************************************************************************/

include_once 'send_data.php';

class zyfra_STRUCT_rpc{
    var $fx;
    var $params;
    
    function __construct($fx){
        $this->fx = $fx;
    }
}

class zyfra_rpc_big{
    private static $file_header = 'rpc_big v0.01';
    //private static $crypt_key = 'Hello world !'; // Removed from PHP 7.2
    private static $crypt_key = null;
    private $is_rpc = false;
    private static $log_error_file = '';
    
    function __construct(){
        $sd = new zyfra_send_data();
        $sd->set_file_header(self::$file_header);
        $sd->set_crypt_key(self::$crypt_key);
        // Check for RPC
        $obj_array = $sd->get_data();
        if (is_array($obj_array)){
            $this->is_rpc=true;
            foreach($obj_array as $key=>$obj){
                // Send back responses
                $result = $this->dispatch_rpc($obj);
                $sd->send($result, NULL, count($obj_array)!=($key+1));
            }
        }else{
            $this->no_rpc();
        }
        unset($sd);
    }
    
    public function is_rpc_call(){
        return $this->is_rpc;
    }
    
    public function set_rpc_error_file($filename){
        self::$log_error_file = $filename;
    }
    
    private static function throw_exception($msg){
        if(self::$log_error_file!=''){
            $fp = fopen(self::$log_error_file,'a');
            fwrite($fp, gmdate('Y-m-d H:i:s').' - '.$msg."\n");
            fclose($fp);
        }
        throw new Exception($msg);
    }
    
    public static function send_rpc($url, $fx_name, $params = NULL){
        if ($url instanceof zyfra_rpc_big){
            $url = self::get_self_url();
        }
        $sd = new zyfra_send_data();
        $sd->set_file_header(self::$file_header);
        $sd->set_crypt_key(self::$crypt_key);
        if (is_array($fx_name)){
            foreach($fx_name as $key=>$row){
                $rpc = new zyfra_STRUCT_rpc($row[0]);
                if (!is_null($row[1]) && !is_array($row[1])) self::throw_exception('RPC params should be a array for rpc function '.$row[0]);
                $rpc->params = $row[1];
                $results = $sd->send($rpc, $url, count($fx_name)!=($key+1));
            }
        }else{
            if (!is_null($params) && !is_array($params)) self::throw_exception('RPC params should be a array for function '.$fx_name);
            $rpc = new zyfra_STRUCT_rpc($fx_name);
            $rpc->params = $params;
            $results = $sd->send($rpc, $url);
        }
        if (is_array($results)){
            if (count($results) > 1){
                return $results;   
            }else{
                return $results[0];
            }
        }else{
            echo '<b>Remote answer:</b><br>';
            echo '<textarea rows=50 style="width:100%;">'.$results.'</textarea>';
            self::throw_exception('RPC response should be an array ('.$results.')');
        }    
    }
    
    private function dispatch_rpc($rpc){
        if(!is_object($rpc)) return NULL;
        if ($rpc instanceof zyfra_STRUCT_rpc){
            $rpc_fx_name = 'rpc_'.$rpc->fx;
            if (method_exists($this, $rpc_fx_name)){
                if (is_null($rpc->params)) $rpc->params = array();
                return call_user_func_array(array($this, $rpc_fx_name), $rpc->params);
            }else{
                self::throw_exception("RPC method doesn't exists (".$rpc_fx_name.')');
            }
        }else{
            return NULL;
        }
    }
    
    protected function no_rpc(){
        //To be overrided
        $this->show_rpc_methods();
    }
    
    protected function get_rpc_methods(){
        $methods = get_class_methods($this);
        $rpc_methods = array();
        foreach ($methods as $method){
            if (substr($method, 0, 4) == 'rpc_'){
                $rpc_methods[] = substr($method, 4);
            } 
        }
        return $rpc_methods;
    }
    
    protected function show_rpc_methods(){
        $rpc_methods = $this->get_rpc_methods();
        print '<h2>RPC Methods</h2>';
        print '<ul>';
        foreach($rpc_methods as $rpc_method){
            print '<li><b>'.$rpc_method.'</b>()</li>';
        }
        print '</ul>';
    }
    
    public function get_self_url(){
        return 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
    }
    
    public static function set_file_header($header){
        self::$file_header = $header;
    }
    
    public static function set_crypt_key($key){
        self::$crypt_key = $key;
    }
}
