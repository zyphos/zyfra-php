<?php
require_once('ZyfraPHP/template.php');
$tpl = new zyfra\template\Template('myfirsttemplate');
$tpl->set_template_path(dirname(__FILE__).'/');
$tpl->set('a', 45);
$tpl->set(['b'=>'c',
              'd'=>7.45]);
echo $tpl->fetch();
