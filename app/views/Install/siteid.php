<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<table class="data-entry"><tr>';
$w .= '<td class="data-label">'.$v->getRes('install/label_site_id').': </td>';
$w .= '<td>'.Widgets::createTextBox('site_id',$v->site_id,true,60)."</td>\n";
$w .= '</tr></table>';
$w .= $v->getRes('install/desc_site_id')."<br />\n";
$w .= "<br />\n";
$w .= $v->continue_button;

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
