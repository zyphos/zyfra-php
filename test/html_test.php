<?php
include_once '../http.php';
$html = 'html_test.js';
print zyfra_http::force_auto_reload($html);
?>