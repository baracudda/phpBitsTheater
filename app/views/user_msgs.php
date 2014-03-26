<?php
//print('USER MSGS HERE BEGIN<BR>');
$theUserMsgs = $v->getUserMsgs();
//print($v->debugStr($theUserMsgs));
if (!empty($theUserMsgs)) {
	$w = '<div id="container-user-msgs"><ul class="user-msgs">';
	foreach((array) $theUserMsgs as $theUserMsg) {
		$w .= '<li class="'.$theUserMsg['msg_class'].'">'.$theUserMsg['msg_text'].'</li>'."\n";
	}
	$w .= '</ul></div>'."\n";
	print($w);
	$v->clearUserMsgs();
}
//print('USER MSGS HERE END<BR>');
