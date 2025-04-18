<?php
/*****************************************************************************
*
*         Context class
*         ---------------
*
*         Context handler
*
*    Copyright (C) 2013 De Smet Nicolas (<http://ndesmet.be>).
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
require_once('ZyfraPHP/context.php');

$context = new \zyfra\Context();

$visible = $context->visible;
// Return false if visible is not set

$visible = $context->visible(true);
// Return true if visible is not set


//you could set default values
$context = new \zyfra\Context(array('visible'=>true, 'transaction_id'=>1));

//or this way
class MyContext extends \zyfra\Context{
    var $visible = true;
    var $transaction_id = 1;
}
$context = new MyContext();


$a = $context->visible; // return true
$a = $context->transaction_id; // return 1
$a = $context->visible(false); // return true
$a = $context->color(4); // return 4
$a = $context->color; // return false

//Context support multiple inheritance of other context or array
$context2 = new MyContext($context, ['property'=>4]);
*/

namespace zyfra;

class Context{
    private $_values;

    public function __construct(){
        $this->_values = [];
        foreach(func_get_args() as $default){
            if (is_object($default)){
                $new_default = get_object_vars($default);
                if (method_exists($default, '_get_all_values')){
                    $new_default = array_merge($new_default, $default->_get_all_values());
                }
                $default = $new_default;
                unset($new_default);
            }
            if (is_array($default)){
                foreach ($default as $name=>$value) {
                    if ($name == '_values') continue; // skip _values
                    $this->$name = $value;
                }
            }
        }
    }

    public function __set($name, $value){
        $this->_values[$name] = $value;
    }

    public function __get($name){
        if (array_key_exists($name, $this->_values)) return $this->_values[$name];
        return false;
    }

    public function __isset($name){
        return array_key_exists($name, $this->_values) && !is_null($this->_values[$name]);
    }

    public function __call($name, $arguments){
        if (property_exists($this, $name)) return $this->$name;
        if (array_key_exists($name, $this->_values)) return $this->_values[$name];
        if (isset($arguments[0])) return $arguments[0];
        return false;
    }

    public function _get_all_values(){
        return $this->_values;
    }
}
