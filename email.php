<?php
/*****************************************************************************
*
*		 email Class
*		 ---------------
*
*		 Functions about email
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
include_once 'debug.php';
$debug = new zyfra_debug;


class zyfra_email{
    
    function getRootBox($boxName){
        //{10.0.0.5:143}user.username.archives => {10.0.0.5:143}user.username
        //{10.0.0.5:143}INBOX.Archives => {10.0.0.5:143}INBOX
        preg_match("/\{[a-z0-9:\.]*\}(user\.[a-zA-Z0-9]*|INBOX)/",$boxName,$matchArray);
        return $matchArray[0];
    }
    
    function getSubFolder($boxName){
        //{10.0.0.5:143}user.username.archives => .archives
        //{10.0.0.5:143}INBOX.Archives => .Archives
        preg_match("/(?:\{[a-z0-9:\.]*\}(?:user\.[a-zA-Z0-9]*|INBOX))([a-zA-Z0-9\.]*)/",$boxName,$matchArray);
        if(!isset($matchArray[1])) return "";
        return $matchArray[1];
    }
    
    function decode($str){
        $str = $this->decodeMimeString($str);
        return $this->ISO_convert($str);
    }
    
    function decode_utf8($str) {
        preg_match_all("/=\?UTF-8\?B\?([^\?]+)\?=/i",$str, $arr);
        for ($i=0;$i<count($arr[1]);$i++){
            $str=ereg_replace(ereg_replace("\?","\?",
            $arr[0][$i]),base64_decode($arr[1][$i]),$str);
        }
        return $str;
    }
    
    function decode_iso88591($str) {
        preg_match_all("/=\?iso-8859-1\?q\?([^\?]+)/i",$str, $arr);
        for ($i=0;$i<count($arr[1]);$i++){
            echo $arr[1][$i];
            $str=ereg_replace("=([A-Z0-9]{2})",base64_decode("\\1"),$arr[1][$i]);
            //$str=ereg_replace(ereg_replace("\?","\?",
            //         $arr[0][$i]),base64_decode($arr[1][$i]),$str);
        }
        return $str;
    }
    
    function ISO_convert($value) {
        return mb_detect_encoding($value." ",'UTF-8,ISO-8859-1') == 'UTF-8' ? utf8_decode($value) : $value;
    }
    
    // Thoses lines below are from php.net
    
    //return supported encodings in lowercase.
    function mb_list_lowerencodings() { $r=mb_list_encodings();
        for ($n=sizeOf($r); $n--; ) { $r[$n]=strtolower($r[$n]); } return $r;
    }

    //  Receive a string with a mail header and returns it
    // decoded to a specified charset.
    // If the charset specified into a piece of text from header
    // isn't supported by "mb", the "fallbackCharset" will be
    // used to try to decode it.
    function decodeMimeString($mimeStr, $inputCharset='utf-8', $targetCharset='utf-8', $fallbackCharset='iso-8859-1') {
        $encodings=$this->mb_list_lowerencodings();
        $inputCharset=strtolower($inputCharset);
        $targetCharset=strtolower($targetCharset);
        $fallbackCharset=strtolower($fallbackCharset);
        
        $decodedStr='';
        $mimeStrs=imap_mime_header_decode($mimeStr);
        for ($n=sizeOf($mimeStrs), $i=0; $i<$n; $i++) {
            $mimeStr=$mimeStrs[$i];
            $mimeStr->charset=strtolower($mimeStr->charset);
            if (($mimeStr == 'default' && $inputCharset == $targetCharset)
            || $mimeStr->charset == $targetCharset) {
                $decodedStr.=$mimStr->text;
            } else {
                $decodedStr.=mb_convert_encoding(
                $mimeStr->text, $targetCharset,
                (in_array($mimeStr->charset, $encodings) ?
                $mimeStr->charset : $fallbackCharset)
                );
            }
        }
        return $decodedStr;
    }
}
