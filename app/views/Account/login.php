<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<h2>Login</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= '<table class="db-entry">';

$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_name').':</td><td class="db-field">'.
		Widgets::createTextBox($v->getUsernameKey(),$v->getUsername())."</td></tr>\n";
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwinput').':</td><td class="db-field">'.
		Widgets::createPassBox($v->getPwInputKey(),$v->getPwInput())."</td></tr>\n";
$w .= '<tr><td class="db-field-label"></td><td class="db-field">'.
		Widgets::createSubmitButton('button_login',$v->getRes('account/label_login'));
		
$w .= "</table>\n";
$w .= '<a href="'.$v->action_url_register.'">Register</a>'." <br/>\n";

$form_html = Widgets::createForm($recite->action_url_login,$w,$v->redirect);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
