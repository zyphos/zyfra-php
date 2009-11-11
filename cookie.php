<?php
/*****************************************************************************
*
*		 Cookie Class
*		 ---------------
*
*		 Class to handle cookie easily. Can store up to +-680KB
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
* v0.0.1	04/11/2009	Creation
*****************************************************************************/


class zyfra_cookie{
    private $name;
    private $max_size = 4096; //Cookies are limited to 4 Ko
    private $data = array();
    private $header = 'ZyfraCookieV0.0.1';
    private $control_len = 48; //2 nb cookie, 6 data len, 40 sha1
    
    function __construct($name){
        $this->name = $name;
        $this->retrieve();
    }
    
    public function store($expire_delay_seconds = -1){
        //Default, never expire
        if ($expire_delay_seconds < 0){
            $expire_delay_seconds = pow(2,31)-1;
        }
		$cookie_max_size = $this->max_size - strlen($this->header);
		$first_cookie_max_size = $cookie_max_size - $this->controle_len;
		$max_total_size = $first_cookie_max_size + 256 * $cookie_max_size; 
        $data = $this->cookie_serialize();
        if (strlen($data) > $max_total_size){
            throw new Exception('Cookie max size is '.
                ((int)$max_total_size/1024).'KB.');
        }
        $nb_cookies = ceil(($max_total_size - $first_cookie_max_size) / 
            $cookie_max_size);
        $expire = time()+$expire_delay_seconds;
        $cookie_str = $this->header.sprintf('%02x%06x', $nb_cookies, 
            strlen($data)).sha1($data).substr($data, 0, $first_cookie_max_size);
        setcookie($this->name, $cookie_str, time() + $expire_delay_seconds,'/');
        $pos = strlen($cookie_str);
        for ($i = 0; $i < $nb_cookies; $i++){
            $cookie_str = $this->header.substr($data, $pos, $cookie_max_size);
            setcookie($this->name.$i, $cookie_str, time() + 
                $expire_delay_seconds,'/');
            $pos += strlen($cookie_str);
        }
    }
    
    public function delete(){
        $this->data = array();
        setcookie($this->name, false,0,'/');
        if (isset($_COOKIE[$this->name])) unset($_COOKIE[$this->name]);
        for($i = 0; $i < 256; $i++){
            setcookie($this->name.$i, false,0,'/');
            if (isset($_COOKIE[$this->name.$i])) 
                unset($_COOKIE[$this->name.$i]);
        }
    }
    
    private function retrieve(){
        $header_len = strlen($this->header);
        if(!isset($_COOKIE[$this->name])) return; //1st check
        $cookie_str = $_COOKIE[$this->name];
        if (substr($cookie_str, 0, $header_len) != $this->header) return; //2nd
        $control = substr($cookie_str, $header_len, $this->control_len);
        sscanf($control,'%02x%06x%40s', $nb_cookies, $data_len, $sha1);
        $data = substr($cookie_str, $header_len + $this->control_len);
        for ($i = 0; $i < $nb_cookies; $i++){
            if(!isset($_COOKIE[$this->name.$i])) return; //3rd check
            $cookie_str = $_COOKIE[$this->name];
            if (substr($cookie_str, 0, $header_len) != $this->header) return;
            $data .= substr($cookie_str, $header_len);
        }
        if ((strlen($data)!=$data_len)||(sha1($data)!=$sha1)) return;
        $this->cookie_unserialize($data);
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