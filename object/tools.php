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
    $str_len = strlen($string);

    for ($i = 0; $i < $str_len; $i++) {
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
    $str_len = strlen($string);
    $split_is_array = is_array($split_var);

    for ($i = 0; $i < $str_len; $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0) && ($i==0 || !($string[$i-1] == "\\"))){
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
                    if ($split_is_array && in_array($char, $split_var)){
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
    $string_len = strlen($string);
    $cur = 0;         // current index in the array to return, for convenience
    $ignore = '';
    $buffer = &$ret[$cur];
    
    if (is_array($split_var)){
        $split_var = array_flip($split_var);
        $split_var_array = true;
    }

    for ($i = 0; $i < $string_len; $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0) && ($i==0 || !($string[$i-1] == "\\"))){
            if ($char == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $char;
            }
            $buffer .= $char;
            continue;
        }elseif ($ignore != ''){
            $buffer .= $char;
            continue;
        }
        switch ($char) {
            case '[':
                $level++;
                $buffer .= $char;
                break;
            case ']':
                $level--;
                $buffer .= $char;
                break;
            case $split_var:
                if ($level == 0) {
                    $cur++;
                    $ret[$cur] = '';
                    $buffer = &$ret[$cur];
                    break;
                }
                // else fallthrough
            default:
                if ($level == 0) {
                    if ($split_var_array && isset($split_var[$char])){
                        $ret[++$cur] = $char;
                        $ret[++$cur] = '';
                        $buffer = &$ret[$cur];
                        break;
                    }elseif($char == $split_var){
                        $ret[++$cur] = '';
                        $buffer = &$ret[$cur];
                        break;
                    }
                }
                $buffer .= $char;
        }
    }
    return $ret;
}

function multispecialsplit($string, $split_var = ',', $return_key=false, $key_index = false) {
    //echo 'string:'.htmlentities($string).'<br>';
    //print_r($split_var);
    //echo '<br>';
    //$back_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
    //echo '<b>Warning:</b>  in <b>'.$back_trace['file'].'</b> on line <b>'.$back_trace['line'].'</b><br>';
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
    $split_vars = array();
    $one_key_found = false; 
    $split_varts = array();
    $str_len = strlen($string);
    foreach ($split_var as $sv){
        if (strlen($sv) <= $str_len && strpos($string, $sv) !== false) $split_varts[$sv] = strlen($sv);
    }
    //echo 'split varts:';
    //print_r($split_varts);
    //echo '<br>';
    if (!count($split_varts)) return array($string); // Stop losing time
    //echo '---ok---<br><br>';
    $ret_cur = &$ret[$cur];
    for ($i = 0; $i < $str_len; $i++) {
        $char = $string[$i];
        if ((($char == '"') || ($char == "'")) && ($level == 0) && ($i==0 || !($string[$i-1] == "\\"))){
            if ($char == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $char;
            }
            $ret_cur .= $char;
            continue;
        }elseif ($ignore != ''){
            $ret_cur .= $char;
            continue;
        }
        switch ($char) {
            case '(':
            case '[':
                $level++;
                $ret_cur .= $char;
                break;
            case ')':
            case ']':
                $level--;
                $ret_cur .= $char;
                break;
            default:
                if ($level == 0){
                    foreach ($split_varts as $sv=>$split_length){
                        if($string[$i]==$sv[0] && substr($string, $i, $split_length)==$sv){
                            if($key_index){
                                $cur = $sv;
                                $ret_cur = &$ret[$cur]; 
                                $ret_cur = '';
                            }else{
                                if ($return_key) $ret[++$cur] = $sv;
                                $ret_cur = &$ret[++$cur];
                                $ret_cur = '';
                            }
                            $i += $split_length-1;
                            break 2;
                        }
                    }
                }
                $ret_cur .= $char;
        }
    }
    return $ret;
}

function r_multi_split_array($string, $split_var = array()) {
    // Reverse multi split with associative array as result
    // Split var only appears once.
    
    $string_len = strlen($string);
    
    $split_var_len = array();
    foreach ($split_var as $sv){
        $sv_len = strlen($sv);
        if ($sv_len <= $string_len && strpos($string, $sv) !== false) {
            $split_var_len[$sv] = strlen($sv);
        }
    }
    if (!count($split_var_len)) return array(''=>$string); // Stop losing time
    $min_len = min($split_var_len);
    
    $level = 0;       // number of nested sets of brackets
    $buffer = '';
    $ignore = '';
    $min_len--;
    $result = array();
    for ($i=$string_len-1; $i >= $min_len; $i--){
        $c = $string[$i];
        if ((($c == '"') || ($c == "'")) && ($level == 0) && ($i==0 || !($string[$i-1] == "\\"))){
            if ($c == $ignore){
                $ignore = '';
            }elseif($ignore == ''){
                $ignore = $c;
            }
            $buffer = $c . $buffer;
            continue;
        }elseif ($ignore != ''){
            $buffer = $c . $buffer;
            continue;
        }
        switch ($c) {
            case '(':
            case '[':
                $level++;
                $buffer = $c . $buffer;
                break;
            case ')':
            case ']':
                $level--;
                $buffer = $c . $buffer;
                break;
            default:
                if ($level == 0){
                    foreach ($split_var_len as $sv=>$sv_len){
                        $i_start = $i - $sv_len + 1;
                        if ($i_start < 0) continue;
                        if($string[$i_start]==$sv[0] && substr($string, $i_start, $sv_len)==$sv){
                            $result[$sv] = $buffer;
                            $buffer = '';
                            $i -= $sv_len;
                            unset($split_var_len[$sv]);
                            if (!count($split_var_len)) break 3;
                            $min_len = min($split_var_len) - 1;
                            break 2;
                        }
                    }
                }
                $buffer = $c . $buffer;
        }
    }
    
    $result[''] = substr($string, 0, $i+1) . $buffer;
    return $result;
}
