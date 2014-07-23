<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets;
$w = '';

if ($recite->getDirector()->isGuest()) {
	$w .= Widgets::createSubmitButton('button_login',$v->getRes('account/label_login')).': ';
	$w .= Widgets::createTextBox($v->getUsernameKey(),$v->getUsername(),false,10,255)." ";
	$w .= Widgets::createPassBox($v->getPwInputKey(),$v->getPwInput(),false,10,255)."<br />\n";
	$w .= '<a href="'.$v->action_url_register.'">Register</a>'."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
	$w .= $v->getRes('account/label_save_cookie').' '.Widgets::createCheckBox($v->getUseCookieKey(),true)."\n";
	if (empty($v->redirect)) {
		/*
		if (!empty($_SERVER['HTTP_REFERER'])) {
			//requires a bunch of sanitation to prevent attacks... just not using it prevents all such attacks ;)
		} else {
		*/
			$theDirector = $v->getDirector();
			if ($theDirector['lastpagevisited']) {
				$v->redirect = $v->getSiteURL($theDirector['lastpagevisited']);
			}
		//}
		//print('redirect='.$v->redirect); //DEBUG
	}
	$form_html = Widgets::createForm($v->action_url_login,$w,$v->redirect);
	print($form_html);
} else {
	print($recite->getDirector()->account_info['account_name'].' (<a href="'.$v->action_url_logout.'">'.
			$recite->getRes('account/label_logout').'</a>) ');
}
