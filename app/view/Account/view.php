<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();
$s = $v->getRes('account/msg_pw_nomatch');
//print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('\"'+p1.value+'\"!=\"'+p2.value+'\" $s');} else {p2.setCustomValidity('');} }</script>";
print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('$s');} else {p2.setCustomValidity('');} }</script>";

$w = '<h2>Account</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= '<table class="data-entry">';

//make sure fields will not interfere with any login user/pw field in header
$pwKeyOld = $v->getPwInputKey().'_old';
$pwKeyNew = $v->getPwInputKey().'_new';
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_pwinput_old').':</td><td class="data-field">'.
		Widgets::createPassBox($pwKeyOld,$v->$pwKeyOld,false,60,120)."</td></tr>\n";
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_name').':</td><td class="data-field">'.
		$v->ticket_info['account_name']."</td></tr>\n";
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_email').':</td><td class="data-field">'.
		Widgets::createEmailBox('email',$v->ticket_info['email'])."</td></tr>\n";
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_pwinput_new').':</td><td class="data-field">'.
		Widgets::createPassBox($pwKeyNew,$v->$pwKeyNew,false,60,120)."</td></tr>\n";
$chkpwJs = "checkPassword(document.getElementById('{$pwKeyNew}'), this);";
$js = "onfocus=\"{$chkpwJs}\" oninput=\"{$chkpwJs}\"";
$w .= '<tr><td class="data-label">'.$v->getRes('account/label_pwconfirm').':</td><td class="data-field">'.
		Widgets::createPassBox('password_confirm',$recite->password_confirm,false,60,120,$js)."</td></tr>\n";
$w .= '<tr><td class="data-label"></td><td class="data-field">'.
		Widgets::createSubmitButton('button_modify',$v->getRes('account/label_modify'));
		
$w .= "</table>\n";

$w .= Widgets::createHiddenPost('ticket_num',$v->ticket_info['account_id']);
$w .= Widgets::createHiddenPost('ticket_email',$v->ticket_info['email']);
$form_html = Widgets::createHtmlForm($recite->form_name,$recite->action_modify,$w,$v->redirect,false);
print $form_html;

$recite->includeMyFooter();
