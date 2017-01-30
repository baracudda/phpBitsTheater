<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets ;
use \com\blackmoonit\Strings ;
$recite->includeMyHeader() ;

$w = '<h2>' . $v->getRes('account/title_request_pwd_reset') . '</h2>' . PHP_EOL;
if( isset( $v->err_msg ) )
{
	$w .= '<span class="msg-error">' . $v->err_msg . '</span>' . PHP_EOL ;
	unset( $v->err_msg ) ;
}
$form = '<table class="db-entry">' . PHP_EOL
      . ' <tr>' . PHP_EOL
      . '  <td class="db-field-label">'
      .     $v->getRes('account/label_email')
      .   '</td>' . PHP_EOL
      . '  <td class="db-field">'
      . Widgets::buildEmailBox( 'send_to_email' )->setRequired()
      		->setPlaceholder( $v->getRes('account/placeholder_email') )->renderInline()
      .   '</td>' . PHP_EOL
      . '  <td class="db-field-label">'
      . Widgets::createSubmitButton( 'button_request_pwd_reset',
      		$v->getRes('account/label_submit') )
      .   '</td>' . PHP_EOL
      . ' </tr>' . PHP_EOL
      . '</table>'
      ;
$w .= Widgets::createHtmlForm( $v->form_name,
		$v->action_url_requestpwreset . '/proc', $form, $v->redirect )
   .  '<p>' . $v->getRes( 'account/help_request_pwd_reset' ) . '</p>'
   .  Strings::eol(2)
   ;

print($w) ;

$recite->includeMyFooter() ;
