<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$h = $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$recite->includeMyHeader($h);
$w = '';

$s = $v->getRes('account/msg_pw_nomatch');
//print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('\"'+p1.value+'\"!=\"'+p2.value+'\" $s');} else {p2.setCustomValidity('');} }</script>";
print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('$s');} else {p2.setCustomValidity('');} }</script>";

$w .= '<h2>Account</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
} else {
	$w .= $v->renderMyUserMsgsAsString();
}
$w .= '<table class="db-entry">';

//make sure fields will not interfere with any login user/pw field in header
$pwKeyOld = $v->getPwInputKey().'_old';
$pwKeyNew = $v->getPwInputKey().'_new';
//old pw (required to change anything)
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwinput_old').':</td><td class="db-field">'
		. Widgets::buildPassBox($pwKeyOld)->setValue($v->$pwKeyOld)->setRequired()->setAttr('maxlength', 120)
				->setPlaceholder($v->getRes('account/placeholder_pwinput'))->render()
		. "</td></tr>\n";
//username
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_name').':</td><td class="db-field">'.
		$v->ticket_info->account_name."</td></tr>\n";
//email
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_email').':</td><td class="db-field">'
		. Widgets::buildEmailBox('email')->setRequired()->setSize(40)->setValue($v->ticket_info->email)
				->setPlaceholder($v->getRes('account/placeholder_email'))->render()
		. "</td></tr>\n";
//pw
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwinput_new').':</td><td class="db-field">'
		. Widgets::buildPassBox($pwKeyNew)->setValue($v->$pwKeyNew)->setAttr('maxlength', 120)
				->render()
		. "</td></tr>\n";
$chkpwJs = "checkPassword(document.getElementById('{$pwKeyNew}'), this);";
$w .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwconfirm').':</td><td class="db-field">'
		. Widgets::buildPassBox('password_confirm')->setValue($v->password_confirm)->setAttr('maxlength', 120)
				->setAttr('onfocus', $chkpwJs)->setAttr('oninput', $chkpwJs)->render()
		. "</td></tr>\n";

//Submit button
$w .= '<tr><td class="db-field-label"></td><td class="db-field">'
		. Widgets::buildSubmitButton('button_modify',$v->getRes('account/label_modify'))
				->addClass('btn-primary')->render()
		. "</td></tr>\n";

$w .= "</table>\n";

$w .= Widgets::createHiddenPost('ticket_name',$v->ticket_info->account_name);
$w .= Widgets::createHiddenPost('ticket_email',$v->ticket_info->email);
$w .= Widgets::createHiddenPost('post_key', $v->post_key);

$theForm = Widgets::buildForm($recite->action_modify)->setName($recite->form_name)
		->setRedirect($v->redirect)->append($w);
print($theForm->render());
print(str_repeat('<br />',3));
$recite->includeMyFooter();
