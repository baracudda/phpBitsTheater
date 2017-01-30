<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= '<h2>Login</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
} else {
	$w .= $v->renderMyUserMsgsAsString();
}
$w .= '<table class="db-entry">' ;

$w .= '<tr><td class="db-field-label">'
    . $v->getRes('account/label_name')
    . ':</td><td class="db-field">'
    . Widgets::buildTextBox( $v->getUsernameKey() )->setValue( $v->getUsername() )
    		->setPlaceholder( $v->getRes('account/placeholder_name') )->renderInline()
    . '</td></tr>' . PHP_EOL
    ;
$w .= '<tr><td class="db-field-label">'
    . $v->getRes('account/label_pwinput')
    . ':</td><td class="db-field">'
    . Widgets::buildPassBox( $v->getPwInputKey() )->setValue( $v->getPwInput() )
    		->setPlaceholder( $v->getRes('account/placeholder_pwinput') )->renderInline()
    . '</td></tr>' . PHP_EOL
    ;
$w .= '<tr><td class="db-field-label"></td><td class="db-field">' . PHP_EOL
    . '    '
    . Widgets::createSubmitButton( 'button_login',
    		$v->getRes('account/label_login') ) . PHP_EOL
    . '    <a class="btn btn-primary" id="btn_Register" href="'
    . $v->action_url_register . '">'
    . $v->getRes('account/label_register') . '</a>' . PHP_EOL
    . '    <a class="btn btn-primary" id="btn_PasswordReset" href="'
    . $v->action_url_requestpwreset . '">'
    . $v->getRes('account/label_requestpwreset') . '</a>' . PHP_EOL
    . '</td></tr>' . PHP_EOL
    ;
$w .= "</table>\n" ;

$form_html = Widgets::createForm($recite->action_url_login,$w,$v->redirect) ;
print( $form_html ) ;
print( str_repeat( '<br/>', 3 ) ) ;
$recite->includeMyFooter() ;
