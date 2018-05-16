<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use \com\blackmoonit\Widgets ;

$recite->includeMyHeader() ;

$w = '<h2>' . $v->getRes('account/title_request_pwd_reset') . '</h2>' . PHP_EOL;
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">' . $v->err_msg . '</span>' . PHP_EOL ;
	unset( $v->err_msg ) ;
} else {
	$w .= $v->renderMyUserMsgsAsString();
}
print($w);

$form = '<table class="db-entry">' . PHP_EOL
      . ' <tr>' . PHP_EOL
      . '  <td class="db-field-label">'
      .     $v->getRes('account/label_email')
      .   '</td>' . PHP_EOL
      . '  <td class="db-field">'
      . Widgets::buildEmailBox( 'send_to_email' )->setRequired()
      		->setPlaceholder( $v->getRes('account/placeholder_email') )->render()
      .   '</td>' . PHP_EOL
      . '  <td class="db-field-label">'
	  . Widgets::buildSubmitButton('button_request_pwd_reset', $v->getRes('account/label_submit'))
			->addClass('btn-primary')->render()
      .   '</td>' . PHP_EOL
      . ' </tr>' . PHP_EOL
      . '</table>'
      . Widgets::buildHoneyPotInput('requested_by')->render()
      ;
$w = Widgets::buildForm($v->action_url_requestpwreset . '/proc')->setName($v->form_name)
		->setRedirect($v->redirect)->append($form)->render();
print( $w ) ;

$w = '<span id="helpBlock" class="help-block">'
		. $v->getRes( 'account/help_request_pwd_reset' )
		. '</span>'
		;
print( $w ) ;

print( str_repeat( '<br />' . PHP_EOL, 3 ) );
$recite->includeMyFooter() ;
