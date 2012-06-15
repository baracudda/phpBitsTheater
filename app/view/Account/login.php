<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<h2>Login</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= '<table class="data-entry">';

$w .= '<tr><td class="data-label">'.$v->getRes('account/label_name').':</td><td class="data-field">'.
		Widgets::createTextBox($v->getUsernameKey(),$v->getUsername())."</td></tr>\n";
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_pwinput').':</td><td class="data-field">'.
		Widgets::createPassBox($v->getPwInputKey(),$v->getPwInput())."</td></tr>\n";
$w .= '<tr><td class="data-label"></td><td class="data-field">'.
		Widgets::createSubmitButton('button_login',$v->getRes('account/label_login'));
		
$w .= "</table>\n";
$w .= '<a href="'.$v->_config['auth/register_url'].'">Register</a>'." <br/>\n";

$form_html = Widgets::createForm($recite->action_login,$w,$v->redirect);
print $form_html;

$recite->includeMyFooter();
