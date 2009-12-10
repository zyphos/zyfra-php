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
    
    static function scriptify($html){
        /* Show HTML code only if script are supported
         * input:
         * - $html: string, HTML content to show 
         * output:
         * - string: the scriptified HTML code
         */
        return '<script type="text/javascript">document.write("'.
            str_replace(array('"',"\n"),array('\"',''),$html).
            '");</script>';
    }
}
?>