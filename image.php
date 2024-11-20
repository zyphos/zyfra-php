<?php
namespace zyfra;

class Image{
    var $file_type;
    var $img;

    function __construct($filename){
        $this->img = $this->read_img($filename);
    }

    function get_filetype_from_filename($filename){
        $file_type = pathinfo($filename, PATHINFO_EXTENSION);
        if ($file_type  == 'jpg') $file_type = 'jpeg';
        if (in_array($file_type, ['jpeg','gif','png','webp','avif'])) return $file_type;
        return null;
    }

    function read_img($filename){
        $this->file_type = $this->get_filetype_from_filename($filename);
        switch($this->file_type){
            case 'jpeg':
                $this->file_type = 'jpeg';
                return imagecreatefromjpeg($filename);
            case 'gif':
                return imagecreatefromgif($filename);
            case 'png':
                return imagecreatefrompng($filename);
            case 'webp':
                return imagecreatefromwebp($filename);
            case 'avif':
                return imagecreatefromavif($filename);
        }
        return imagecreatefromstring(file_get_contents($filename));
    }

    function get_size(){
        return array(imagesx($this->img), imagesy($this->img));
    }

    function get_new_size($w, $h, $max_w, $max_h){
        $ar = $w/$h;
        $nar = $max_w / $max_h;
        if ($ar > $nar){
            $nw = $max_w;
            $nh = round($max_w / $ar);
        }else{
            $nh = $max_h;
            $nw = round($max_h * $ar); 
        }
        if ($nw > $w) {
            $nh = $nh * $w / $nw;
            $nw = $w;
        }
        if ($nh > $h) {
            $nw = $nw * $h / $nh;
            $nh = $h;
        }
        return array($nw, $nh);
    }

    function fill_resize($target_w, $target_h){
        // resize image and keep aspect ratio and plot it on a white image
        list($w, $h) = $this->get_size();
        list($nw, $nh) = $this->get_new_size($w, $h, $target_w, $target_h);
        $nim = imagecreatetruecolor($target_w, $target_h);
        $color = imagecolorallocate($nim, 255, 255, 255);
        imagefill($nim, 0, 0, $color);
        $nx = round(($target_w - $nw)/2);
        $ny = round(($target_h - $nh)/2);
        imagecopyresampled($nim, $this->img, $nx, $ny, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($this->img);
        $this->img = $nim;
    }

function resize($max_w, $max_h){
        // resize image and keep aspect ratio
        list($w, $h) = $this->get_size();
        list($nw, $nh) = $this->get_new_size($w, $h, $max_w, $max_h);
        $nim = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($nim, $this->img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($this->img);
        $this->img = $nim;
    }

    function rotate_h(){
        list($w, $h) = $this->get_size();
        if ($w >= $h) return;
        $this->img = imagerotate($this->img, 90, 0);
    }

    function save($filename = null, $file_type = null){
        if (is_null($file_type) && !is_null($filename)) $file_type = $this->get_filetype_from_filename($filename);
        if (is_null($file_type)) $file_type = $this->file_type;
        switch($file_type){
            case 'jpeg':
                @imagejpeg($this->img, $filename, 90);
                break;
            case 'gif':
                @imagegif($this->img, $filename);
                break;
            case 'png':
                @imagepng($this->img, $filename);
                break;
            case 'webp':
                @imagewebp($this->img, $filename);
                break;
            case 'avif':
                @imageavif($this->img, $filename);
                break;
        }
    }

    function show($file_type = null){
        if (is_null($file_type)) $file_type = $this->file_type;
        header('Content-Type: image/'.$file_type);
        $this->save(null, $file_type);
        die();
    }

    function __destruct(){
        imagedestroy($this->img);
    }
}

function auto_gen_image_and_display($img_base_uri, $original_img_path, $target_img_path, $missing_img_dir_log=null){
    /* Automatic generate image and store them if storage path exist then display it, can be used in 404 response
       $img_base_uri : Absolute image uri (in website) ie: /images
       $original_img_path : Absolute path where original (full size) images are stored ie: /var/www/original_images
       $target_img_path : Absolute path where generated images will be stored ie: dirname(__FILE__).'/images'
       $missing_img_dir_log : Absolute filename to store missing storage directory ie: sys_get_temp_dir() . '/' . '404_missing_img_dir.log'

       Supported dirs:
       /r/<width>x<height>   Resize image to fit target max width and max height ie: /images/r/600x400/my_pict.avif
       /hr/<width>x<height>   Rotate 90 degrees if the image height is bigger than width then resize image to fit target max width and max height ie: /images/hr/600x400/my_pict.avif
       /rf/<width>x<height>  Resize image to fit target width and height then add white background to fill space. ie: /images/rf/600x400/my_pict.avif
    */
    $uri = $_SERVER['REQUEST_URI'];
    if (substr($uri, 0, strlen($img_base_uri)) == $img_base_uri){ // && $is_from_matedex_website
        $cmd = substr($uri, strlen($img_base_uri));
        $resize_cmd = explode('/', $cmd);
        if (count($resize_cmd) != 3){
            die('Invalid options');
        }
        list($type_of_resize, $size, $filename) = $resize_cmd;
        list($width, $height) = explode('x', $size);
        $src_filename = $original_img_path.'/'.$filename;
        $filename_wo_ext = pathinfo($filename, PATHINFO_FILENAME);
        if (!file_exists($src_filename)) $src_filename =  $original_img_path.'/'.$filename_wo_ext.'.jpg';
        if (!file_exists($src_filename)) $src_filename =  $original_img_path.'/'.$filename_wo_ext.'.png';
        if (!file_exists($src_filename)) $src_filename =  $original_img_path.'/'.$filename_wo_ext.'.gif';
        if (!file_exists($src_filename)) $src_filename =  $original_img_path.'/'.$filename_wo_ext.'.jpeg';
        if (!file_exists($src_filename)) $src_filename =  $original_img_path.'/'.$filename_wo_ext.'.jfif';
        if (file_exists($src_filename)){
            $img = new Image($src_filename);
            if ($type_of_resize == 'r'){
                $img->resize($width, $height);
            }elseif ($type_of_resize == 'hr'){
                $img->rotate_h();
                $img->resize($width, $height);
            }elseif($type_of_resize == 'rf'){
                $img->fill_resize($width, $height);
            }else{
                return;
            }
            http_response_code(200);
            //header($_SERVER["SERVER_PROTOCOL"].' 200 OK');
            $dir_path = $target_img_path.'/'.$type_of_resize.'/'.$size;
            if (file_exists($dir_path) && is_dir($dir_path)){
                $img->save($dir_path.'/'.$filename);
                $file_type = $img->get_filetype_from_filename($filename);
                $img->show($file_type);
            }else{
                if (!is_null($missing_img_dir_log)){
                    if (file_exists($missing_img_dir_log)){
                        $lines = file($missing_img_dir_log, FILE_IGNORE_NEW_LINES);
                    }else{
                        $lines = [];
                    }
                    if (!in_array($dir_path, $lines)){
                        $f_handle = fopen($missing_img_dir_log, 'a');
                        fwrite($f_handle, $dir_path."\n");
                        fclose($f_handle);
                    }
                }
                $img->show();
            }
            die();
        }
    }
}
