<?php
use BitsTheater\scenes\Menus as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets;

$w = '<div id="menu">'.$v->renderMenu($v->app_menu)."</div>\n";
print($w);
