<?php
/*****************************************************************************
*
*		 Get Post Class
*		 ---------------
*
*		 Class handle GET and POST fields
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
* zyfra_get_post::sanitize();
* 
* 
*****************************************************************************/

class zyfra_get_post {
    public static function sanitize(){
        // Clean $_POST, $_GET, $_COOKIE fields from magic_quotes_gpc
        global $var_zyfra_get_post_is_sanitized;
        if(!isset($var_zyfra_get_post_is_sanitized)||
          !$var_zyfra_get_post_is_sanitized){
            foreach($_POST as $key=>$value) 
                $_POST[$key] = self::unquote($value);
            foreach($_GET as $key=>$value) $_GET[$key] = self::unquote($value);
            foreach($_COOKIE as $key=>$value) 
                $_COOKIE[$key] = self::unquote($value);
            $var_zyfra_get_post_is_sanitized = true; //Avoid doing this twice
        }
    }

    private static function unquote($data){
        if (is_array($data)){
            foreach($data as &$row) $row = self::unquote($row);
            return $data;
        }
        return stripslashes($data);
    }
}
