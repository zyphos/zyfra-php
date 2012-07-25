<?php
function array_get($array, $key, $default=false){
    if (is_array($array) && array_key_exists($key, $array)) return $array[$key];
    return $default;
}

function trim_inside($string){
    // Intelligent trim, it doesn't trim quoted content
    $quote = '';
    $r = '';
    $last = true;
    for ($i = 0; $i < strlen($string); $i++) {
        $c = $string[$i];
        switch ($c) {
            case ' ':
            case "\t":
            case "\n":
                if (!$last){
                    $r .= ' ';
                    $last = true;
                    break;
                }
            case "\r":
                if ($quote != '') $r .= $c;
                $last = true;
                break;
            case '"':
            case "'":
                if($quote == $c){
                    $quote = '';
                }elseif($quote == ''){
                    $quote = $c;
                }
            default:
                $last = false;
                $r .= $c;
        }
    }
    return $r;
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
                $levelb++;
                if ($level == 0 && $levelb == 1) {
                    $cur = &$param;
                    break;
                }
                $cur .= '[';
                break;
                // else fallthrough
            case ']':
                if ($level == 0 && $levelb == 1) {
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
    $ignore = '';

    for ($i = 0; $i < strlen($string); $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0)){
            if ($char == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $char;
            }
            $ret[$cur] .= $char;
            continue;
        }elseif ($ignore != ''){
            $ret[$cur] .= $char;
            continue;
        }
        switch ($char) {
            case '(':
            case '[':
                $level++;
                $ret[$cur] .= $string[$i];
                break;
            case ')':
            case ']':
                $level--;
                $ret[$cur] .= $string[$i];
                break;
            case $split_var:
                if ($level == 0) {
                    $cur++;
                    $ret[$cur] = '';
                    break;
                }
                // else fallthrough
            default:
                if ($level == 0) {
                    if (is_array($split_var) && in_array($char, $split_var)){
                        $ret[++$cur] = $char;
                        $ret[++$cur] = '';
                        break;
                    }elseif($char == $split_var){
                        $ret[++$cur] = '';
                        break;
                    }
                }
                $ret[$cur] .= $string[$i];
        }
    }
    return $ret;
}

function specialsplitnotpar($string, $split_var = ',') {
    $level = 0;       // number of nested sets of brackets
    $ret = array(''); // array to return
    $cur = 0;         // current index in the array to return, for convenience
    $ignore = '';

    for ($i = 0; $i < strlen($string); $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0)){
            if ($char == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $char;
            }
            $ret[$cur] .= $char;
            continue;
        }elseif ($ignore != ''){
            $ret[$cur] .= $char;
            continue;
        }
        switch ($char) {
            case '[':
                $level++;
                $ret[$cur] .= $string[$i];
                break;
            case ']':
                $level--;
                $ret[$cur] .= $string[$i];
                break;
            case $split_var:
                if ($level == 0) {
                    $cur++;
                    $ret[$cur] = '';
                    break;
                }
                // else fallthrough
            default:
                if ($level == 0) {
                    if (is_array($split_var) && in_array($char, $split_var)){
                        $ret[++$cur] = $char;
                        $ret[++$cur] = '';
                        break;
                    }elseif($char == $split_var){
                        $ret[++$cur] = '';
                        break;
                    }
                }
                $ret[$cur] .= $string[$i];
        }
    }
    return $ret;
}

function multispecialsplit($string, $split_var = ',', $return_key=false, $key_index = false) {
    //Specialsplit with multi character $split_var
    $level = 0;       // number of nested sets of brackets
    $ret = array(''); // array to return
    if($key_index){
        $cur = '';
    }else{
        $cur = 0;         // current index in the array to return, for convenience
    }
    
    $ignore = '';
    if(!is_array($split_var)) $split_var = array($split_var);
    for ($i = 0; $i < strlen($string); $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0)){
            if ($char == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $char;
            }
            $ret[$cur] .= $char;
            continue;
        }elseif ($ignore != ''){
            $ret[$cur] .= $char;
            continue;
        }
        switch ($char) {
            case '(':
            case '[':
                $level++;
                $ret[$cur] .= $char;
                break;
            case ')':
            case ']':
                $level--;
                $ret[$cur] .= $char;
                break;
            default:
                if ($level == 0){
                    foreach ($split_var as $sv){
                        $split_length = strlen($sv);
                        if(substr($string, $i, strlen($sv))==$sv){
                            if($key_index){
                                $cur = $sv;
                                $ret[$cur] = '';
                            }else{
                                if ($return_key) $ret[++$cur] = $sv;
                                $ret[++$cur] = '';
                            }
                            $i += $split_length-1;
                            break 2;
                        }
                    }
                }
                $ret[$cur] .= $char;
        }
    }
    return $ret;
}
?>