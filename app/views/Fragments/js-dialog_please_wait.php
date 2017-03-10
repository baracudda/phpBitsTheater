<meta itemprop="js-dialog_please_wait" data-bootbox_dialog_argument=<?php
	$o = new \StdClass();
	$o->message = '<img width="90%" class="center-block" src="'
		. $v->getRes('generic/imgsrc/please_stand_by.png')
		. '">'
	;
	$o->closeButton = false;
	print( '"'.htmlentities(json_encode($o)).'"' );
?>>
