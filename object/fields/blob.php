<?php
class BlobField extends Field{// < 65536 bytes (64KB)
    var $widget='text';

    function get_sql_def(){
        return 'BLOB';
    }
}

class TinyBlobField extends BlobField{// < 256 bytes
    function get_sql_def(){
        return 'TINYBLOB';
    }
}

class MediumBlobField extends BlobField{// < 16777216 bytes (16MB)
    function get_sql_def(){
        return 'MEDIUMBLOB';
    }
}

class LongBlobField extends BlobField{// < 4294967296 bytes (4GB)
    function get_sql_def(){
        return 'LONGBLOB';
    }
}
