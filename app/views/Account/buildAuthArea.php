<?php
use \com\blackmoonit\Widgets;

$w = '';
if ($recite->_director->isGuest()) {
	$w .= Widgets::createSubmitButton('button_login',$v->getRes('account/label_login')).': ';
	$w .= Widgets::createTextBox($v->getUsernameKey(),$v->getUsername(),false,10,255)." ";
	$w .= Widgets::createPassBox($v->getPwInputKey(),$v->getPwInput(),false,10,255)."<br />\n";
	$w .= '<a href="'.$v->action_url_register.'">Register</a>'."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
	$w .= $v->getRes('account/label_save_cookie').' '.Widgets::createCheckBox($v->getUseCookieKey(),true)."\n";
	$form_html = Widgets::createForm($v->action_url_login,$w,$v->redirect);
	print($form_html);
} else {
	print($recite->_director->account_info['account_name'].' (<a href="'.$v->action_url_logout.'">'.
			$recite->getRes('account/label_logout').'</a>) ');
}
