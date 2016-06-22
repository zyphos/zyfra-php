<?php
/*****************************************************************************
 *
 *		 http Class
 *		 ---------------
 *
 *		 Functions about HTTP
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

class zyfra_http {
    static function get_file_size($url){
        /* Get file size
         * input:
         * - $url: string, url of the target ie: 'http://www.google.com/thepath/here/myfile.txt'
         * output:
         *  - integer: size of file in byte
         */
        if(preg_match("/(?:http:\/\/)([^\/]*)(.*)/",$url,$data)<1) {
            throw new Exception('Can\'t retrieve server name from provided URL.');
            return 0;
        }
        $serverName = $data[1];
        $filePath = $data[2];
        $fs = fsockopen($serverName, 80, $errno, $errstr, 15);
        fputs($fs, "HEAD $filePath HTTP/1.0\r\n" );
        fputs($fs, "Host: ".$serverName."\r\n");
        fputs($fs, "Connection: close\r\n\r\n" );
        $filesize = 0;
        while ($line = fgets($fs,1024)) {
            if (substr($line, 0, 16) == "Content-Length: " ){
                $filesize = trim(substr($line, 16));
                fclose($fs);
                break;
            }
        }
        return $filesize;
    }


    static function flush(){
        //Flush output to browser. Send all data to browser
        if (ob_get_length()){
            ob_flush();
        }
        flush();
    }

    static function scriptify($html_with_script, $html_without_script = ''){
        /* Show HTML code only if javascript is supported
         * input:
         * - $html_with_script: string, HTML content to show if script is active
         * - $html_without_script: string, HTML content to show in other case
         * output:
         * - string: the scriptified HTML code
         */
        $html_out = '';
        if ($html_with_script != ''){
            $html_out .= '<script type="text/javascript">document.write("'.
            str_replace(array('"',"\n"),array('\"',''),$html_with_script).
            	'");</script>';
        }
        if ($html_without_script != ''){
            $html_out .= '<noscript>'.$html_without_script.'</noscript>';
        }
        return $html_out;
    }
    
    static function force_auto_reload($html_content){
        /* Force the client web browser to reload of all .css and .js when 
         * they are modified.
         * 
         * input:
         * - $html_content: string, HTML content to scan
         * output:
         * - string: HTML with replaced .css and .js filename
         * You must add this to .htaccess:
         * RewriteEngine on
         * RewriteRule ^(.*)\.[\d]{10}\.(css|js)$ $1.$2 [L]
         */
        return preg_replace_callback("/[a-z0-9.\/\_-]+(?:\.css|\.js)/i", 'zyfra_http::_threat_url_force_reload', $html_content);
    }
    
    static function _threat_url_force_reload($matches){
        $url = &$matches[0];
        if ($url[0]=='/'){
            $filename = $_SERVER['DOCUMENT_ROOT'].$url;
        }else{
            $filename = dirname($_SERVER['SCRIPT_FILENAME']).'/'.$url;
        }
        if(!file_exists($filename)){
            return $url;
        }
        $mtime = filemtime($filename);
        return preg_replace('{\\.([^./]+)$}', ".$mtime.\$1", $url);
    }
    
    static function split_url_data($url){
    	/*
    	 * Input: url to be parsed
    	 * Output: array(url, data)
    	 * 
    	 * Ie:
    	 * split_url_data('test.php?data=test&activated')
    	 * output:
    	 * array('test.php', array('data'=>'test', 'activated'=>''))
    	 */
    	$res = explode('?', $url, 2);
    	if (count($res)==1) return array($url, array());
    	$key_values = explode('&', $res[1]);
    	$values = array();
    	foreach($key_values as $key_value){
    		$key_val = explode('=', $key_value, 2);
    		if (count($key_val) > 1){
    			list($key, $value) = $key_val;
    			$values[$key] = urldecode($value);
    		}else{
    			$values[$key_value] = '';
    		}
    	}
    	return array($res[0], $values);
    }
    
    static function join_url_data($url, $data){
    	/*
    	 * Ie:
    	 * join_url_data('test.php', array('data'=>'test','activated'=>''))
    	 * output:
    	 * 'test.php?data=test&activated'
    	 */
    	if (count($data) == 0) return $url;
    	$new_data = array();
    	foreach($data as $key=>$value){
    		$new_data[] = $key.'='.urlencode($value);
    	}
    	return $url.'?'.implode('&', $new_data);
    }
    
    static function redirect($url){
    	header("location:".$url);
    	exit();
    }
    
    static function add_session2url($url){
    	$session_name = session_name();
    	$session_id = session_id();
    	list($new_url, $data) = self::split_url_data($url);
    	if (isset($data[$session_name])) unset($data[$session_name]);
    	$new_data = array($session_name=>$session_id) + $data;
    	return self::join_url_data($new_url, $new_data);
    }
}
?>