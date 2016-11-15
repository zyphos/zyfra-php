<?php
class BlobField extends Field{ // < 65536 bytes
    var $widget='text';

    function get_sql_def(){
        return 'BLOB';
    }    
}

class TinyBlobField extends BlobField{ // < 256 bytes
    function get_sql_def(){
        return 'TINYBLOB';
    }
}

class MediumBlobField extends BlobField{// <
    function get_sql_def(){
        return 'MEDIUMBLOB';
    }
}

class LongBlobField extends BlobField{// <
    function get_sql_def(){
        return 'LONGBLOB';
    }
}
