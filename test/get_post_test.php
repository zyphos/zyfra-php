<?php
include_once '../get_post.php'; 
zyfra_get_post::sanitize();
zyfra_get_post::sanitize(); //sanitize is only run once.
print 'Magic:'.get_magic_quotes_gpc().'<br>'; 
?>
<form action='?' method="post"><input type='text' name='data' 
<?php
    if(isset($_POST['data'])){
        print ' value="'.htmlentities($_POST['data']).'" ';
    }
?>
><input type="submit" value=" Send ">
</form>