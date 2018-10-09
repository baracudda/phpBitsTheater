<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets ;

print('v'.$v->getRes('website/version').'<br />');
$w = '' ;

if( $recite->isGuest() )
{ // Draw the login/register/reset form.
	$theSeparator = '&nbsp;&nbsp;|&nbsp;&nbsp;' ;
	$w .= Widgets::buildTextBox( $v->getUsernameKey() )->setSize( 20 )
			->setPlaceholder( $v->getRes( 'account/placeholder_autharea_username' ) )
			->render() . PHP_EOL
	   .  '&nbsp;&nbsp;'
	   .  Widgets::buildPassBox( $v->getPwInputKey() )->setSize( 20 )
			->setPlaceholder( $v->getRes( 'account/placeholder_autharea_password' ) )
			->render() . PHP_EOL
	   .  '&nbsp;&nbsp;'
	   .  Widgets::buildSubmitButton( 'button_login', $v->getRes( 'account/label_login' ) )
	   		->addClass('btn-primary')->addClass('btn-sm')->render()
	   .  '<br/>' . PHP_EOL
	   .  '<br/>' . PHP_EOL
	   .  '<a href="' . $v->action_url_register . '">'
	   .  $v->getRes( 'account/label_register' ) . '</a>'
	   .  $theSeparator
	   .  '<a href="' . $v->action_url_requestpwreset . '">'
	   .  $v->getRes( 'account/label_requestpwreset' )
	   .  '</a>'
	   .  $theSeparator
	   .  '<label>' . $v->getRes( 'account/label_save_cookie') . '&nbsp;'
	   .  '  <input style="vertical-align: text-bottom;" type="checkbox"'
	   .  '   name="' . $v->getUseCookieKey() . '" checked>'
	   .  '</label>'
	   . PHP_EOL ;
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
	$theForm = Widgets::buildForm($v->action_url_login)->setRedirect($v->redirect)->append($w);
	print( $theForm->render() ) ;
}
else
{
	$w .= $recite->getDirector()->account_info->account_name
	   .  ' (<a href="' . $v->action_url_logout . '">'
	   .  $recite->getRes( 'account/label_logout' ) . '</a>) '
	   ;
	print($w) ;
}
