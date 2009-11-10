<?php
/*****************************************************************************
*
*		 Language Class
*		 ---------------
*
*		 User language detection and handling class
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

class zyfra_language{
    private $default = 'en';
    protected $languages;
    
    protected function get_all_languages(){
        //Need to be override by your one method.
        $this->languages = array();
        $i = 1;
        $this->languages['en'] = (object)array('id'=>$i++, 'name'=>'english');
        $this->languages['fr'] = (object)array('id'=>$i++, 'name'=>'francais');
        $this->languages['nl'] = 
            (object)array('id'=>$i++, 'name'=>'nederlands');
        $this->languages['de'] = (object)array('id'=>$i++, 'name'=>'deutsch');
    }
    
    protected function get_cookie(){
        //To be override
        return '';
    }
    
    public function auto_detect(){
        if (($lang = $this->check_get()) !== false) return $lang;
        if (($lang = $this->check_post()) !== false) return $lang;
        if (($lang = $this->check_session()) !== false) return $lang;
        if (($lang = $this->check_cookie()) !== false) return $lang;
        if (($lang = $this->check_navigator()) !== false) return $lang;
        //Really no hint...
        return $this->default; 
    }
    
    public function set_default($abv){
        $abv = strtolower($abv);
        if ($this->is_a_language($abv)) $this->default = $abv;
    }
    
    public function is_a_language($abv){
        $abv = strtolower($abv);
        if (!is_array($this->languages)) $this->get_all_languages();
        return isset($this->languages[strtolower($abv)]);
    }
    
    public function get_id($abv){
        if(!$this->is_a_language($abv)) $this->languages[$this->default]->id;
        return $this->languages[strtolower($abv)]->id;
    }
    
    public function get_name($abv){
        if(!$this->is_a_language($abv)) $this->languages[$this->default]->name;
        return $this->languages[strtolower($abv)]->name;
    }
    
    public function get_correct_abv($abv){
        if (trim($abv)=='') return false;
        if($this->is_a_language($abv)){
            return strtolower($abv);
        }else{
            return $this->default;
        }
    }
    
    private function check_get(){
        if(isset($_GET['lang']))
            return $this->get_correct_abv($_GET['lang']);
        return false;
    }
    
    private function check_post(){
        if(isset($_POST['lang'])) 
            return $this->get_correct_abv($_POST['lang']);
        return false;
    }
    
    private function check_session(){
        if(isset($_SESSION['lang']))
            return $this->get_correct_abv($_SESSION['lang']);
        return false;
    }
    
    private function check_cookie(){      
        return $this->get_correct_abv($this->get_cookie());
    }
    
    private function check_navigator(){
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
            $accept_languages = explode(",",$_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach($accept_languages as $accept_language){
                $language = array_shift(explode(';',$accept_language));
                preg_match('/([A-Za-z]*)-/', $accept_language, $match, 0, 0);
                if (isset($match[1])){
                    $abv = $match[1];
                }else{
                    $abv = $language;
                }
                if($this->is_a_language($abv)) 
                    return $this->get_correct_abv($abv);
            }
        }
        return false;
    }
}
?>