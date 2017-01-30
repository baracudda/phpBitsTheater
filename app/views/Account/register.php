<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$s = $v->getRes('account/msg_pw_nomatch');
//print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('\"'+p1.value+'\"!=\"'+p2.value+'\" $s');} else {p2.setCustomValidity('');} }</script>";
print "<script>function checkPassword(p1,p2) { if (p1.value!=p2.value) {p2.setCustomValidity('$s');} else {p2.setCustomValidity('');} }</script>";

$w .= '<h2>Register</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
} else {
	$w .= $v->renderMyUserMsgsAsString();
}

$theForm = Widgets::createHiddenPost('post_key', $v->post_key);
$theForm .= '<table class="db-entry">';
//make sure fields will not interfere with any login user/pw field in header
$userKey = $v->getUsernameKey().'_reg';
$pwKey = $v->getPwInputKey().'_reg';
$theForm .= '<tr><td class="db-field-label">'.$v->getRes('account/label_name').':</td>'
		.'<td class="db-field">'
		.Widgets::buildInputBox($userKey)->setRequired()->setSize(40)->setValue($v->$userKey)
				->setPlaceholder($v->getRes('account/placeholder_name'))->renderInline()
		."</td></tr>\n";
$theForm .= '<tr><td class="db-field-label">'.$v->getRes('account/label_email').':</td>'
		.'<td class="db-field">'
		.Widgets::buildEmailBox('email')->setRequired()->setSize(40)->setValue($v->$email)
				->setPlaceholder($v->getRes('account/placeholder_email'))->renderInline()
		."</td></tr>\n";
$theForm .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwinput').':</td>'
		.'<td class="db-field">'
		.Widgets::buildPassBox($pwKey)->setValue($v->$pwKey)->setRequired()->setAttr('maxlength', 120)
				->setPlaceholder($v->getRes('account/placeholder_pwinput'))->renderInline()
		."</td></tr>\n";
$chkpwJs = "checkPassword(document.getElementById('{$pwKey}'), this);";
$js = "onfocus=\"{$chkpwJs}\" oninput=\"{$chkpwJs}\"";
$theForm .= '<tr><td class="db-field-label">'.$v->getRes('account/label_pwconfirm').':</td>'
		.'<td class="db-field">'
		.Widgets::buildPassBox('password_confirm')->setValue($v->password_confirm)->setRequired()->setAttr('maxlength', 120)
				->setPlaceholder($v->getRes('account/placeholder_pwconfirm'))->renderInline()
		."</td></tr>\n";
$theForm .= '<tr><td class="db-field-label">'.$v->getRes('account/label_regcode').':</td>'
		.'<td class="db-field">'
		.Widgets::buildInputBox('reg_code')->setRequired()->setSize(60)->setValue($v->reg_code)
				->setPlaceholder($v->getRes('account/placeholder_regcode'))->renderInline()
		."</td></tr>\n";
$theForm .= '<tr><td class="db-field-label"></td><td class="db-field">'.
		Widgets::createSubmitButton('button_register',$v->getRes('account/label_submit'));
		
$theForm .= "</table>\n";

$w .= Widgets::createHtmlForm($recite->form_name,$recite->action_url_register,$theForm,$v->redirect,false);
print($w);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
