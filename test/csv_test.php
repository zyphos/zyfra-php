<?php
include_once '../csv.php';
include_once '../debug.php';
$csv = new zyfra_csv('test.csv');
echo '<table border="1">';
foreach ($csv->get_array() as $row){
    echo '<tr>';
    foreach ($row as $col){
        echo '<td>'.htmlentities($col).'</td>';
    }
    echo '</tr>';
}
echo '</table>';
?>