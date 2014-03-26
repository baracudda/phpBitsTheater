<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<div align="left">';
$w .= 'Choose Default Language: '.Widgets::createDropDown('lang_type',$recite->lang_types,$recite->lang_type);
$w .= '<br />';
$w .= $recite->continue_button;
$w .= '</div>';

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,'',false);
print $form_html;

print(str_repeat('<br />',3));
$recite->includeMyFooter();
