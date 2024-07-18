<?php
/*****************************************************************************
*
*         Repair serialized class
*         ---------------
*
*         Repair corrupted serialized object
*
*    Copyright (C) 2014 De Smet Nicolas (<http://ndesmet.be>).
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

/*
 * Usage:

require_once('ZyfraPHP/repair_serialized.php');
$r = new RepairSerialize;
$new_object = $r->unserialize($my_badly_serialized_object);

*
*/
class RepairSerialize{
    public function unserialize($txt){
        $this->txt = $txt;
        $this->strlen = strlen($txt);
        $this->pos = 0;
        return $this->_parse();
    }

    private function _parse($back_ref=null){
        if($this->pos >= $this->strlen) return $obj;
        $c = $this->txt[$this->pos++];
        if(is_null($back_ref)) $back_ref = [];
        $obj = null;
        switch($c){
            case 'N':
                $obj = null;
                $this->pos += 1;
                break;
            case 'b':
                $obj = $this->_parse_boolean();
                break;
            case 'i':
                $obj = $this->_parse_integer();
                break;
            case 'd':
                $obj = $this->_parse_float();
                break;
            case 's':
                $obj = $this->_parse_string();
                break;
            case 'a':
                $obj = $this->_parse_array($back_ref);
                break;
            case 'O':
                $obj = $this->_parse_object($back_ref);
                break;
            default:
                $delta = 20;
                echo 'pos['.$this->pos.'] '. substr($this->txt, $this->pos-$delta-1, $delta).'<strong style="color:red;">'.$c.'</strong>'.substr($this->txt, $this->pos, $delta).'<br>';
                echo('Unknown type ['.$c.']');
                echo $this->_render_backref($back_ref);
                break;
        }
        return $obj;
    }

    private function _parse_boolean(){
        if ($this->txt[$this->pos++] != ':') return;
        switch($this->txt[$this->pos++]){
            case '1':
                $this->pos++;
                return true;
            case '0':
                $this->pos++;
                return false;
        }
    }

    private function _parse_integer($ending_char = ';'){
        $size = '';
        if ($this->txt[$this->pos++] != ':') return;
        while($this->pos < $this->strlen){
            $c = $this->txt[$this->pos++];
            if ($c == $ending_char){
                return (int)$size;
            }
            $size .= $c;
        }
        return (int)$size;
    }

    private function _parse_float($ending_char = ';'){
        $size = '';
        if ($this->txt[$this->pos++] != ':') return;
        while($this->pos < $this->strlen){
            $c = $this->txt[$this->pos++];
            if ($c == $ending_char){
                return (float)$size;
            }
            $size .= $c;
        }
        return (float)$size;
    }

    private function _get_size(){
        $res = $this->_parse_integer(':');
        if (is_null($res)) echo 'Bad getsize: '.$this->_render_backref();
        return $res;
    }

    private function _parse_string(){
        $res = '';
        $size = $this->_get_size();
        if (is_null($size)) return;
        $this->pos++; // "
        $nb_char = 0;
        while($this->pos < $this->strlen && $nb_char < $size){
            $c = $this->txt[$this->pos++];
            $nb_char++;
            $res .= $c;
        }
        while($this->pos < ($this->strlen-1) && $this->txt[$this->pos++] != '"'){ // ";
        }
        $this->pos++;
        return $res;
    }

    private function _parse_array($back_ref){
        $size = $this->_get_size();
        if (is_null($size)) throw new Exception('Null size');
        $this->pos++;
        $res = [];
        $i = 0;
        while ($i<$size && $this->pos < $this->strlen){
            $new_back_ref = array_merge($back_ref,['[]']);
            $key = $this->_parse($new_back_ref);
            $new_back_ref = array_merge($back_ref,['['.$key.']=']);
            $res[$key] = $this->_parse($new_back_ref);
            $i++;
        }
        $this->pos++;
        return $res;
    }

    private function _parse_object($back_ref){
        $obj_name = $this->_parse_string();
        $res = (object)[];
        $this->pos--;
        $size = $this->_get_size();
        if (is_null($size)) return;
        $this->pos++; // {
        $i = 0;
        while ($i<$size && $this->pos < $this->strlen){
            $new_back_ref = array_merge($back_ref,['object('.$obj_name.')']);
            $key = $this->_parse($new_back_ref);
            $new_back_ref = array_merge($back_ref,['object('.$obj_name.')->'.$key]);
            $res->$key = $this->_parse($new_back_ref);
            $i++;
        }
        $this->pos++; // }
        return $res;
    }

    private function _render_backref($backref){
        return 'Backref'. implode('', $backref).'<br>';
    }
}
