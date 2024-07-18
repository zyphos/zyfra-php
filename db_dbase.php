<?php
class DbaseFile{
    var $header;
    var $fields;
    var $records;
    var $field_types;

    function __construct($db_filename){
        $this->parse($db_filename);
    }

    function parse_file_types(){
        $this->field_types = [];
        foreach($this->fields as $field){
            $this->field_types[$field['fieldname']] = $field['fieldtype'];
        }
    }

    function parse_data($fieldname, $data){
        if (!array_key_exists($fieldname, $this->field_types)) return $data;
        $data_type = $this->field_types[$fieldname];
        switch($data_type){
            case 'N': //Numeric => int
                return (int)$data;
            case 'D':
                sscanf($data, '%4d%2d%2d', $year, $month, $day);
                return mktime(0, 0, 0, $month, $day, $year);
            case 'L': //Boolean
                return $data == 'T';
        }
        return $data;
    }

    function parse($db_filename) {
        // Skip deleted lines
        $fdbf = fopen($db_filename,'r');
        $this->fields = [];
        $buf = fread($fdbf,32);
        $this->header = unpack( "VRecordCount/vFirstRecord/vRecordLength", substr($buf,4,8));
        $goon = true;
        $unpackString='A1DeletionFlag/';
        while ($goon && !feof($fdbf)) {
            // read fields:
            $buf = fread($fdbf,32);
            if (substr($buf,0,1)==chr(13)) {
                $goon=false;
            } // end of field list
            else {
                $field=unpack( "a11fieldname/A1fieldtype/Voffset/Cfieldlen/Cfielddec", substr($buf,0,18));
                $unpackString.="A$field[fieldlen]$field[fieldname]/";
                array_push($this->fields, $field);
            }
        }
        $this->parse_file_types();
        fseek($fdbf, $this->header['FirstRecord']); // move back to the start of the first record (after the field definitions)
        $records = [];
        for ($i=1; $i<=$this->header['RecordCount']; $i++) {
            $buf = fread($fdbf,$this->header['RecordLength']);
            $record=unpack($unpackString, $buf);
            if ($record['DeletionFlag'] == '*') continue;
            foreach($record as $fieldname=>&$value){
                $value = $this->parse_data($fieldname, $value);
            }
            $records[] = $record;
        } //raw record
        fclose($fdbf);
        $this->records = $records;
    }

    function filter_field($field_name, $value){
        $results = [];
        foreach($this->records as $record){
            if ($record[$field_name] == $value){
                $results[] = $record;
            }
        }
        return $results;
    }
}
