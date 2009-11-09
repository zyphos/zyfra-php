<?php
/*****************************************************************************
*
*		 Cookie Class
*		 ---------------
*
*		 Class to handle cookie easily.
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
* $my_cookie = new Ccookie('my_cookie_name');
* $my_cookie->data = 'Hi';
* $my_cookie->txt = array('hi', 1, 'yop');
* $my_cookie->store(100); //Keep cookie stored for 100 seconds, -1 = Infinite
* // Warning !! ->store() method must be called before any text output.
* 
* 
* $my_cookie->delete(); //remove the cookie 
* 
* 
*****************************************************************************/

/*****************************************************************************
* Revisions
* ---------
* 
* v0.01	04/11/2009	Creation
*****************************************************************************/

/*
 * Todo:
 * - Add a header to cookie, to handle only cookie made by this class
 * - Split cookie to handle big size
 */

class zyfra_cookie{
    //todo:
    // add multi cookie split 
    
    private $name;
    private $max_size = 4096; //Cookies are limited to 4 Ko
    private $data = array();
    
    function __construct($name){
        $this->name = $name;
        $this->retrieve();
    }
    
    public function store($expire_delay_seconds = -1){
        //Default, never expire
        if ($expire_delay_seconds < 0){
            $expire_delay_seconds = pow(2,31)-1;
        }
        $data = $this->cookie_serialize();
        if (strlen($data) > $this->max_size){
            throw new Exception('Cookie max size is 4KB.');
        }
        setcookie($this->name, $data, time()+$expire_delay_seconds,'/');
    }
    
    public function delete(){
        $this->data = array();
        setcookie($this->name, false,0,'/');
	      if (isset($_COOKIE[$this->name])) unset($_COOKIE[$this->name]);
    }
    
    private function retrieve(){
        if(isset($_COOKIE[$this->name])){
            $this->cookie_unserialize($_COOKIE[$this->name]);
        }
    }
    
    private function cookie_serialize(){
        return base64_encode(gzcompress(serialize($this->data)));
    }
    
    private function cookie_unserialize($cookie_str){
        $this->data = unserialize(gzuncompress(base64_decode($cookie_str)));
        if (!is_array($this->data)) $this->data = array();
    }  
    
    public function __set($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get($name) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }
    
    public function get_data(){
        return $this->data;
    }

    /**  As of PHP 5.1.0  */
    public function __isset($name) {
        return isset($this->data[$name]);
    }

    public function __unset($name) {
        unset($this->data[$name]);
    }
}
?>