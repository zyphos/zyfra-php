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

namespace zyfra\human;

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

function date($the_date){
    $date_array = explode('-', $the_date);
    $date_array = array_reverse($date_array);
    return implode('/', $date_array);
}

function date2text($the_date, $language='en'){
    // Return human readable txt date
    // Input: $the_date = 'YYYY-MM-DD'
    // ie: date2text('2018-04-25', 'en') => '25th of April 2018'
    list($year, $month, $day) = explode('-', $the_date);
    $month = (int)$month;
    $day = (int)$day;
    
    if($language == 'fr'){
        $months = [1=>'janvier',
                   2=>'f&eacute;vrier',
                   3=>'mars',
                   4=>'avril',
                   5=>'mai',
                   6=>'juin',
                   7=>'juillet',
                   8=>'o&ucirc;t',
                   9=>'septembre',
                   10=>'octobre',
                   11=>'novembre',
                   12=>'d&eacute;cembre',
                  ];
        return $day.' '.$months[$month].' '.$year;
    }elseif($language == 'nl'){
        $months = [1=>'januari',
                   2=>'februari',
                   3=>'maart',
                   4=>'april',
                   5=>'mei',
                   6=>'juni',
                   7=>'juli',
                   8=>'augustus',
                   9=>'september',
                   10=>'oktober',
                   11=>']="november',
                   12=>'december',
                    ];
        return $day.' '.$months[$month].' '.$year;
    }
    $months = [1=>'January',
                    2=>'February',
                    3=>'March',
                    4=>'April',
                    5=>'May',
                    6=>'June',
                    7=>'July',
                    8=>'August',
                    9=>'September',
                    10=>'October',
                    11=>'November',
                    12=>'December',
    ];
    switch($day){
        case 1:
        case 21:
        case 31:
            $day .= 'st';
            break;
        case 2:
        case 22:
            $day .= 'nd';
            break;
        case 3:
        case 23:
            $day .= 'rd';
            break;
        default:
            $day .= 'th';
    }
    return $day.' of '.$months[$month].' '.$year;
}
