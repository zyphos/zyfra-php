<?php
/*****************************************************************************
*
*         Send Data Class
*         ---------------
*
*         Class to send all kind of data over internet, between PHP, even big one.
*         All datas are crypted.
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
* to send:
* $sd = new zyfra_send_data();
* $sd->send_data("http://myhost.tld/myurl.php", $data);
*
* to receive:
* $rd = new zyfra_send_data();
* $data = $rd->get_data();
*****************************************************************************/

/*****************************************************************************
* Revisions
* ---------
*
* v0.01    19/10/2009    Creation
*****************************************************************************/

include_once 'fake_file.php';

class zyfra_send_data{
    //private $crypt_key = "Hello world !"; // Encryption key, deprecated since PHP 7.2
    private $crypt_key = null;
    // File first bytes limited to 20 chars !!!
    protected $file_header = "SendData_v0.1";
    protected $send_filename = ''; // Local filename for storing data to be sent
    protected $post_field_prefix = 'send_data_'; // Prefix
    private $file2send = NULL;
    private $current_url = '';
    private $has_content = false;
    public $logging = false;
    private $cnx;

    function __construct(){
        $cnx = curl_init();
        curl_setopt($cnx, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($cnx, CURLOPT_BINARYTRANSFER, TRUE);
        curl_setopt($cnx, CURLOPT_POST, TRUE);
        curl_setopt($cnx, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->cnx = $cnx;
    }

    function __destruct(){
        curl_close($this->cnx);
    }

    public function send(&$data, $url = NULL, $wait = FALSE){
        if ($this->has_content && $url != $this->current_url)
            return $this->close_and_send_file();
        $this->current_url = $url;
        if (is_null($this->file2send)){
            $this->file2send = new zyfra_fake_file($this->send_filename, 'wb+');
            $file_header = rtrim($this->file_header);
            $file_header = str_pad($file_header,20);
            $this->file2send->write($file_header, 20);
            $this->has_content = true;
        }
        $this->write_block($this->file2send, $data);
        if (!$wait) return $this->close_and_send_file();
        return NULL;
    }

    public function get_data($data = NULL){
        $post_field_data = $this->post_field_prefix."data";
        $post_field_file = $this->post_field_prefix."fname";
        if (!is_null($data)){
            $ff = new zyfra_fake_file_memory($data);
            $ff->rewind();
        }elseif(isset($_POST[$post_field_data])){
            $data = $_POST[$post_field_data];
            $ff = new zyfra_fake_file_memory($data);
        }else if(isset($_FILES[$post_field_file])){
            $ff = new zyfra_fake_file(
                $_FILES[$post_field_file]['tmp_name'],'rb');
        }else{
            return NULL;
        }
        $ff->rewind();
        $file_header = $ff->read(20);
        if(rtrim($file_header) != rtrim($this->file_header)){
            $ff->rewind();
            $data = $ff->read();
            if ($ff->is_physic()){
                $filename = $ff->get_filename();
                unlink($filename);
            }
            unset($ff);
            //Not send_data !
            return $data;
        }
        $datas = [];
        while(!$ff->eof()){
            $datas[] = $this->read_block($ff);
        }
        unset($ff);
        return $datas;
    }


    public function set_crypt_key($key){
        $this->crypt_key = $key;
    }

    public function set_send_filename($send_filename){
        $this->send_filename = $send_filename;
    }

    public function set_file_header($file_header){
        $this->file_header = $file_header;
    }

    protected function log($msg){
        if ($this->logging){
            echo '[log]: '.$msg;
        }
    }

    private function write_block($file, &$data){
        $block_data = $this->make_str($data); //Convert to a string
        unset($data); //Free up some memory
        //Write it to file
        //1.header

        $block_size = strlen($block_data);
        $block_crc32 = crc32($block_data);
        $block_header = pack("NN",$block_size,$block_crc32);
        $file->write($block_header,8);
        $file->write($block_data,$block_size);
        unset($block_data); //Free memory
    }

    private function read_block($file){
        $block_header = $file->read(8);
        if($file->eof()) return NULL;
        $block_header_array = unpack("Nsize/Ncrc",$block_header);
        $block_size = $block_header_array["size"];
        if($block_size>10485760){
            throw new Exception('Error blocksize > 10MB (read_block)');
        }
        $block_crc32 = $block_header_array["crc"];
        $block_data = $file->read($block_size);
        //Check data integrity
        $calc_len = strlen($block_data);
        $calc_crc32 = crc32($block_data);
        if(($calc_len!=$block_size)||
          ($calc_crc32!=$block_crc32)){
            //Bad data !
            $msg = 'Data corrupted (read_block)';
            if ($calc_len!=$block_size) $msg .= ' len: '.$calc_len.' <> '.$block_size;
            if ($calc_crc32!=$block_crc32) $msg .= ' crc32: '.$calc_crc32.' <> '.$block_crc32;
            throw new Exception($msg);
        }
        //Block ok
        $data = $this->unmake_str($block_data);
        unset($block_data);
        return $data;
    }

    private function make_str(&$obj){
        $data_str = serialize($obj);
        $data_gz = gzcompress($data_str,9);
        unset($data_str);
        if (is_null($this->crypt_key)) return $data_gz;
        return $this->crypt($data_gz);
    }

    private function unmake_str(&$txt){
        if (is_null($this->crypt_key)){
            $data_gz = &$txt;
        }else{
            $data_gz = $this->decrypt($txt);
        }
        $data_str = gzuncompress($data_gz);
        unset($data_gz);
        return unserialize($data_str);
    }

    private function crypt($txt){
        $td = mcrypt_module_open(MCRYPT_TripleDES, "", MCRYPT_MODE_ECB, ""); // deprecated since PHP 7.2
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $this->crypt_key, $iv);
        $data_crypt = mcrypt_generic($td, $txt);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $data_crypt;
    }

    private function decrypt($txt){
        $td = mcrypt_module_open(MCRYPT_TripleDES, "", MCRYPT_MODE_ECB, ""); // deprecated since PHP 7.2
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $this->crypt_key, $iv);
        $data_crypt = mdecrypt_generic($td, $txt);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $data_crypt;
    }

    private function post($post_url, $is_file, $fname_data){
        if (($post_url != '')&&(!is_null($post_url))){
            $post_data = [];
            if ($is_file){
                if (!function_exists('curl_file_create')){
                    $post_data[$this->post_field_prefix.'fname'] = "@$fname_data"; //below php 5.5
                }else{
                    $post_data[$this->post_field_prefix.'fname'] = curl_file_create($fname_data); //php 5.5 and above
                }
            }else{
                $post_data[$this->post_field_prefix.'data'] = $fname_data;
            }
            curl_setopt($this->cnx, CURLOPT_URL, $post_url);
            curl_setopt($this->cnx, CURLOPT_POSTFIELDS, $post_data);
            // perform post
            $this->log('Sending data...');
            $result = curl_exec($this->cnx);
            if ($result === false){
                throw new Exception('Can not post with Curl @ '.$post_url.' Error: '.curl_error($this->cnx));
            }
            return $result;
        }else{
            header("Content-Type: application/octet-stream; ");
            header("Content-Transfer-Encoding: binary; ");
            if ($is_file){
                header("Content-Length: " . filesize($fname_data) ."; ");
                $fh = fopen($fname_data, 'rb');
                if (is_null($fh)) throw new Exception('Can\'t open file '.$fname_data.'.');
                while (!feof($fh)){
                    set_time_limit(0);
                    print(fread($fh, 1024*8));
                    flush();
                    ob_flush();
                }
            }else{
                header("Content-Length: " . strlen($fname_data) ."; ");
                print($fname_data);
                flush();
                ob_flush();
            }
            return NULL;
        }
    }

    private function close_and_send_file(){
        if (is_null($this->file2send)) return;
        if($this->file2send->is_physic()){
            $filename = $this->file2send->get_filename();
            $is_tmp_file = $this->file2send->tmp_file();
            unset($this->file2send);
            $this->file2send = NULL;
            $this->has_content = FALSE;
            $rdata = $this->post($this->current_url, true, $filename);
            if ($is_tmp_file){
                unlink($filename); //Delete file if temporary file
            }else{
                $fh = fopen($filename, 'wb'); //Reset file size to 0
                fclose($fh);
            }
        }else{
            $this->file2send->rewind();
            $rdata = $this->post($this->current_url, false,
                $this->file2send->read());
            unset($this->file2send);
            $this->file2send = NULL;
            $this->has_content = FALSE;
        }
        return $this->get_data($rdata);
    }
}
