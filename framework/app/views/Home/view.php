<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */

$recite->includeMyHeader();
$w = '';

$w .= '<h2>Home Page Content.<h2>'."<br /><br />\n";

print($w);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
