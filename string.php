<?php
/*****************************************************************************
*
*		 String Class
*		 ---------------
*
*		 Class to manipulate strings 
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

class zyfra_string {
    public static function trim($txt){
        // Trim function that can handle array
        if (is_array($txt)||is_object($txt)){
            foreach($txt as &$row) {
                $row = self::trim($row);
            }
        }
        if(is_string($txt)) return trim($txt);
        return $txt;
    }
}
