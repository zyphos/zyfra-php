<?php
/*****************************************************************************
 *
 *         i18n Class
 *         ---------------
 *
 *         Internalization class
 *         Pass trought UTF8, ISO-8859-15, ISO-8859-1 problem
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


class zyfra_i18n{
    var $euro;
    var $degree;

    function __construct(){
        $this->euro = $this->unichr(128);
        $this->degree = html_entity_decode("&deg;");
    }

    function unichr($u) {
        return mb_convert_encoding('&#' . intval($u) . ';', mb_internal_encoding(), 'HTML-ENTITIES');
        //'UTF-8'
    }

    public static function remove_accent($str){
        /* Remove accent and special character from string
         * Input:
         * - $str: The string to be parse
         * Output:
         * - string, without any accent or special character
         */
        if(is_array($str)){
            foreach($str as $key=>$value){
                $str[$key] = self::remove_accent($value);
            }
        }
        if(!is_string($str)) return $str;
        $str = htmlentities($str);
        $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/', '$1',
            $str);
        $str = str_replace("&amp;"," and ",$str);
        return html_entity_decode($str);
    }
}
