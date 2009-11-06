<?php 
/*****************************************************************************
*
*		 human Class
*		 ---------------
*
*		 Human Interface
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

class zyfra_human{
    function hr2bin($hr){
        //Make human readable to Binary size
        //eg: $hr = 14.68M
        //result: 15392074
        preg_match("/(\d*\.?\d)(K|M|G|T)?/",$hr,$matches);
        if (count($matches)< 1) return 0;
        if (count($matches)< 2) return $matches[1]; 
        $base = $matches[1];
        switch($matches[2]){
            case "T":
                return $base * 1099511627776;
                break;
            case "G":
                return $base * 1073741824;
                break;
            case "M":
                return $base * 1048576;
                break;
            case "K":
                return $base * 1024;
            break;
        }
        return $base;
    }
    
    function bin2hr($size){
        //Make Binary to human readable size
        //eg: $size = 15392074
        //result: 14.68M
        $sizes = array(1024=>"K",1048576=>"M",1073741824=>"G");
        krsort($sizes);
        foreach($sizes as $lvl=>$lvlName){
            if($size>=$lvl){
                return round($size / $lvl,2).$lvlName;
            }
        }
        return $size;
    }
}
?>