<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<div align="left">';
$w .= 'Authentication Type: '.Widgets::createDropDown('auth_type',$recite->auth_types,$recite->auth_type);
$w .= '<br />';
$w .= $recite->continue_button;
$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
