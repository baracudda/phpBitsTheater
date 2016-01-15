<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets ;
use com\blackmoonit\Strings ;

$w = '' ;

if( $recite->isGuest() )
{ // Draw the login/register/reset form.
	$theSeparator = '&nbsp;&nbsp;|&nbsp;&nbsp;' ;
	$w .= Widgets::createTextBox( $v->getUsernameKey(),
			$v->getUsername(), false,10,255 ) . PHP_EOL
	   .  Widgets::createPassBox( $v->getPwInputKey(),
			$v->getPwInput(), false, 10, 255 ) . PHP_EOL
	   .  Widgets::createSubmitButton( 'button_login',
			$v->getRes( 'account/label_login' ) ) . '<br/>' . PHP_EOL
	   .  '<a href="' . $v->action_url_register . '">'
	   .  $v->getRes( 'account/label_register' ) . '</a>'
	   .  $theSeparator
	   .  '<a href="' . $v->action_url_requestpwreset . '">'
	   .  $v->getRes( 'account/label_requestpwreset' )
	   .  '</a>'
	   .  $theSeparator
	   .  $v->getRes( 'account/label_save_cookie') . '&nbsp;'
	   .  Widgets::createCheckBox( $v->getUseCookieKey(), false ) . PHP_EOL
	   ; 
	if( empty($v->redirect) )
	{
		/*
		if (!empty($_SERVER['HTTP_REFERER'])) {
			//requires a bunch of sanitation to prevent attacks... just not using it prevents all such attacks ;)
		} else {
		*/
			$theDirector = $v->getDirector();
			if( $theDirector['lastpagevisited'] )
			{
				$v->redirect = $v->getSiteURL($theDirector['lastpagevisited']) ;
			}
		//}
		//print('redirect='.$v->redirect); //DEBUG
	}
	$form_html = Widgets::createForm( $v->action_url_login, $w, $v->redirect ) ;
	print($form_html) ;
}
else
{
	$w .= $recite->getDirector()->account_info->account_name
	   .  ' (<a href="' . $v->action_url_logout . '">'
	   .  $recite->getRes( 'account/label_logout' ) . '</a>) '
	   ;
	print($w) ;
}
