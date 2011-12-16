<?php
function array_get($array, $key, $default=false){
    if (is_array($array) && array_key_exists($key, $array)) return $array[$key];
    return $default;
}

function specialsplitparam($string) {
    $level = 0;       // number of nested sets of brackets
    $field = '';
    $param = '';
    $cur = &$field;
    $levelb = 0;

    for ($i = 0; $i < strlen($string); $i++) {
        switch ($string[$i]) {
            case '(':
                $level++;
                $cur .= '(';
                break;
            case ')':
                $level--;
                $cur .= ')';
                break;
            case '[':
                if ($level == 0) {
                    $cur = &$param;
                    break;
                }
                $cur .= '[';
                $levelb++;
                break;
                // else fallthrough
            case ']':
                if ($level == 0 && $levelb == 0) {
                    return array($field, $param);
                }
                $levelb--;
                $cur .= ']';
                break;
            default:
                $cur .= $string[$i];
        }
    }
    return array($field, $param);
}

function specialsplit($string, $split_var = ',') {
    $level = 0;       // number of nested sets of brackets
    $ret = array(''); // array to return
    $cur = 0;         // current index in the array to return, for convenience

    for ($i = 0; $i < strlen($string); $i++) {
        switch ($string[$i]) {
            case '(':
                $level++;
                $ret[$cur] .= '(';
                break;
            case ')':
                $level--;
                $ret[$cur] .= ')';
                break;
            case $split_var:
                if ($level == 0) {
                    $cur++;
                    $ret[$cur] = '';
                    break;
                }
                // else fallthrough
            default:
                $ret[$cur] .= $string[$i];
        }
    }

    return $ret;
}

function multispecialsplit($string, $split_var = ',') {
    $level = 0;       // number of nested sets of brackets
    $ret = array(''); // array to return
    $cur = 0;         // current index in the array to return, for convenience
    $split_length = strlen($split_var);
    for ($i = 0; $i < strlen($string); $i++) {
        switch ($string[$i]) {
            case '(':
                $level++;
                $ret[$cur] .= '(';
                break;
            case ')':
                $level--;
                $ret[$cur] .= ')';
                break;
            default:
                if ($level == 0){
                if(substr($string, $i, $split_length)==$split_var){
                    $cur++;
                    $ret[$cur] = '';
                    $i += $split_length-1;
                    break;
                }
            }
            $ret[$cur] .= $string[$i];
        }
    }

    return $ret;
}
?>