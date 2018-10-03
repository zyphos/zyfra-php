<?php
/*****************************************************************************
*
*		 Fake File Class
*		 ---------------
*
*		 Class Cfake_file_memory to emulate a file in memory.
*		 Class Cfake_file to emulate a file in memory, and write it to disk
*				if a thresold is reach. Usefull if you don't have enough memory.
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
* 
*****************************************************************************/

/*****************************************************************************
* Revisions
* ---------
* 
* v0.01	20/10/2009	Creation
*****************************************************************************/


class zyfra_fake_file_memory{
    protected $data;
    protected $ptr;
    
    public function __construct($init_data = ""){
        $this->data = $init_data;
        $this->ptr = 0;
    }
    
    public function write($data, $length = -1){
        if ($length >= 0) $data = substr($data, 0, $length);
        if($this->ptr == strlen($this->data)){
            $this->data .= $data;
            $this->ptr = strlen($this->data);
        }else{
            if ((strlen($this->data)-$this->ptr-strlen($data)) <= 0){
                $this->data = substr($this->data, 0, $this->ptr).$data;
                $this->ptr = strlen($this->data); 
            }else{
                $this->data = substr($this->data, 0, $this->ptr).$data.
                    substr($this->data, -(strlen($this->data)-$this->ptr
                    -strlen($data)));
                $this->ptr += strlen($data);    
            }
        }
        return strlen($data);
    }

    public function read($length = -1){
        if ($this->eof()) return "";
        if ($length < 0){
            $result = substr($this->data, $this->ptr);
            
        }else{
            $result = substr($this->data, $this->ptr, $length);
        }
        $this->ptr += strlen($result);  
        return $result;
    }
    
    public function eof(){
        return $this->ptr == strlen($this->data);
    }
    
    public function seek($offset, $whence = -1){
        /* $whence: 
         * -1, from the beginning
         * 0, from current position
         * 1, from the end
         */
        switch($this->sign($whence)){
            case -1:
                $new_ptr = $offset;
                break;
            case 0:
                $new_ptr = $this->ptr + $offset;
                break;
            case 1:
                $new_ptr = strlen($this->data) + $offset;
                break;
        }
        if ($new_ptr < 0) $new_ptr = 0;
        if ($new_ptr > strlen($this->data)) $new_ptr = strlen($this->data);
        $this->ptr = $new_ptr;
        return 0;
    }
    
    public function tell(){
        return $this->ptr;
    }
    
    public function rewind(){
        $this->ptr = 0;
        return true;
    }
    
    public function is_physic(){
        return false;
    }
    
    protected function sign($nb){
        if ($nb < 0) {
            return -1;
        }else if ($nb > 0){
            return 1;
        }
        return 0;
    } 
}

class zyfra_fake_file extends zyfra_fake_file_memory{
    private $max_mem_size; // from memory to file if size is reached
    private $in_memory = true;
    private $mode; //rb, wb
    private $filename;
    private $is_tmp_file = false;
    private $fhandle = NULL;
    
    function __construct($filename, $mode, $max_mem_size = 1048576){
        $this->max_mem_size = $max_mem_size;
        if (trim($filename) == "") $this->is_tmp_file = true; 
        $this->filename = $filename;
        $this->mode = $mode;
        parent::__construct('');
        if (($this->get_file_size() > 0) && ($this->readable())){
            $this->open_file();
        }
    }
    
    public function write($data,$length = -1){
        if (!$this->writeable()) throw new Exception('File "'.
            $this->filename.'" isn\'t open in write mode.');
        if ($this->in_memory()){
            if ($this->len_after_write($data) > $this->max_mem_size){
                //Switch to hardisk file
                $this->open_file();
                $written_nb = fwrite($this->fhandle,$this->data);
                if ($written_nb === FALSE) throw new Exception(
                		'Can\'t write to file "'.$this->filename.'".'); 
                $this->data = '';
            }else{
                if (stripos($this->mode,'a')){
                    $this->ptr = strlen($this->data);
                }
                return parent::write($data, $length);
            }
        }
        if ($length >= 0){
            return fwrite($this->fhandle, $data, $length);
        }
        return fwrite($this->fhandle, $data);
    }
    
    public function read($length = -1){
        if (!$this->readable()) throw new Exception('File "'.
            $this->filename.'" isn\'t open in read mode.');
        if ($this->in_memory()) return parent::read($length);
        if ($length < 0) {
            $data = '';
            while(!feof($this->fhandle)){
                $data .= fread($this->fhandle, 8192);
            }
            return $data;
        }
        return fread($this->fhandle, $length);
    }
    
    public function eof(){
        if ($this->in_memory()) return parent::eof();            
        return feof($this->fhandle);
    }
    
    public function seek($offset, $whence = -1){
        /* $whence: 
         * -1, from the beginning
         * 0, from current position
         * 1, from the end
         */
        if ($this->in_memory()) return parent::seek($offset, $whence);
        switch($this->sign($whence)){
            case -1: return fseek($fhandle,$offset, SEEK_SET);
            case 0: return fseek($fhandle,$offset, SEEK_CUR);
            case 1: return fseek($fhandle,$offset, SEEK_END);
        }
    }
    
    public function tell(){
        if ($this->in_memory()) return parent::tell();
        return ftell($this->fhandle);
    }
    
    public function rewind(){
        if ($this->in_memory()) return parent::rewind();
        return rewind($this->fhandle);
    }
    
    public function tmp_file(){
        return $this->is_tmp_file;
    }
    
    public function is_physic(){
        // return true if the file is stored physically on disk.
        return !is_null($this->fhandle);
    }
    
    public function get_filename(){
        return $this->filename;
    }
    
    function __destruct(){
        if(!$this->in_memory()){
            fclose($this->fhandle);
            $this->fhandle = NULL;
        }else{
            if (!$this->tmp_file() && $this->writeable()){
                $this->open_file();
                fwrite($this->fhandle, $this->data);
                fclose($this->fhandle);
                $this->fhandle = NULL;
            }
        }
    }
    
    protected function get_tmp_file(){
        $tmp_filename = tempnam(sys_get_temp_dir(), "ff");
        if ($tmp_filename === FALSE){
            throw new Exception('Can\'t open temporary file.'); 
        }
        return $tmp_filename;
    }
    
    protected function get_file_size(){
        if (!is_file($this->filename)) return 0;
        return filesize($this->filename);
    }
    
    protected function open_file(){
        if (!is_null($this->fhandle)) throw new Exception('File already open "'.
            $this->filename.'".');
        if ($this->is_tmp_file){
            $this->filename = $this->get_tmp_file();
        }
        $this->fhandle = fopen($this->filename, $this->mode);
        if ($this->fhandle === FALSE) {
            $this->fhandle = NULL;
            throw new Exception('Can\'t open file "'.
                $this->filename.'" in '.$this->mode.' mode.');
        }
    }
    
    protected function len_after_write($data){
        if(($this->ptr == strlen($this->data))||(stripos($this->mode,'a'))){
            return $this->ptr + strlen($data);
        }else{
            if ((strlen($this->data)-$this->ptr-strlen($data))<=0){
                return $this->ptr + strlen($data);
            }else{
                return strlen($this->data);
            }
        }
    }
    
    protected function readable(){
        return ((stripos($this->mode, 'r') !== FALSE)||
          (stripos($this->mode, '+') !== FALSE));
    }
    
    protected function writeable(){
        return ((stripos($this->mode, 'w') !== FALSE)||
          (stripos($this->mode, '+') !== FALSE));
    }
    
    protected function in_memory(){
        return is_null($this->fhandle);
    }
}
?>