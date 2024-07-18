<?php
require_once('ZyfraPHP/template.php');
$tpl = new zyfra_template('myfirsttemplate');
$tpl->set_template_path(dirname(__FILE__).'/');
$tpl->assign('a', 45);
$tpl->assign(['b'=>'c',
              'd'=>7.45]);
echo $tpl->fetch();
