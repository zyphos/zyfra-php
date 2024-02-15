<?php
/*****************************************************************************
*
*		 CSV Class
*		 ---------------
*
*		 Class to handle CSV file. Comma-separated values 
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
* $my_csv = new zyfra_csv('my_file.csv');
* $my_csv->get_array(); //Return a bulk array of parsed CSV
* 
*****************************************************************************/

class zyfra_csv{
    private $text_delimiter;
    private $field_delimiter;
    private $bulk_array = array();
    private $filename = '';
    private $nb_columns = 0; 
    
    function __construct($filename_data, $text_delimiter = '"', $field_delimiter = ','){
        $this->text_delimiter = $text_delimiter; //Keep config
        $this->field_delimiter = $field_delimiter;
        if (is_file($filename_data)){
            $this->filename = $filename_data;
            $filename_data = file_get_contents($filename_data);
        }
        $this->bulk_array = $this->parse($filename_data);
        $this->count_columns();
    }
    
    private function parse($txt){
        /* This function return an array of the CSV without any threatment
         * The parse method is described at:
         * http://en.wikipedia.org/wiki/Comma-separated_values
         */
        $text_delimiter = $this->text_delimiter;
        $field_delimiter = $this->field_delimiter;
        $txt = str_replace("\r\n","\n",$txt); //Remove Windows double returns
        $rows = array();
        $cols = array();
        $inside_text = false; //True when we are inside a text field
        $field = '';
        for($i=0;$i < strlen($txt); $i++){
            if (substr($txt, $i, 2) == ($text_delimiter . $text_delimiter)){
                // "" means "
                $field .= $text_delimiter;
                $i++; //Skip next character
                continue;                
            }
            $char = substr($txt, $i, 1);
            switch($char){
                case $text_delimiter:
                    if ($inside_text){
                        $inside_text = false;
                    }else{
                        $field = '';
                        $inside_text = true;
                    }
                    break;
                    
                case $field_delimiter:
                    if($inside_text){
                        $field .= $field_delimiter;
                    }else{
                        $cols[] = $field;
                        $field = '';
                    }
                    break;
                   
                case "\n":
                    if($inside_text){
                        $field .= $char;
                    }else{
                        $cols[] = $field;
                        $rows[] = $cols;
                        $cols = array();
                        $field = '';
                    }
                    break;
                    
                default:
                    $field .= $char;
            }
        }
        return $rows;
    }
    
    private function count_columns(){
        // Count the number of columns in the bulk_array
        foreach($this->bulk_array as $row){
            $nb_col = count($row);
            if ($nb_col > $this->nb_columns) $this->nb_columns = $nb_col;
        }
    }
    
    private function generate(){
        // This function generate a CSV content from $this->bulk_array
        $txt = '';
        foreach($this->bulk_array as $row){
            foreach($row as &$col){
                $col = str_replace($this->text_delimiter, $this->text_delimiter.
                   $this->text_delimiter, $col);
                if(strpos($col, $this->field_delimiter) !== false){
                    $col = $this->text_delimiter . $col . $this->text_delimiter;
                }
            }
            $txt .= implode($this->field_delimiter, $row);
            $txt .= "\n";  
        }
        return $txt;
    }
    
    function save_file($filename = ''){
        if ($filename == ''){
            $filename = $this->filename;
        }
        $f = fopen($filename, 'w');
        fwrite($f, $this->generate());
        fclose($f);
    }
    
    function get_array(){
        return $this->bulk_array;
    }
    
    function get_nb_columns(){
        return $this->nb_columns;
    }
}
