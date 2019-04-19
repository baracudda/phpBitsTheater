<?php
use BitsTheater\Scene; /* @var $v Scene */
?><meta itemprop="js-dialog_error" data-bootbox_dialog_argument=<?php
	$o = new \StdClass();
	$o->title = $v->getRes('generic/title_dialog_error');
	$o->size = 'small';
	//$o->backdrop = true;
	$o->buttons = new \StdClass();
	$o->buttons->success = new \StdClass();
	$o->buttons->success->label = $v->getRes('generic/label_button_dismiss_error');
	$o->buttons->success->className = 'btn-danger';
	print( '"'.htmlentities(json_encode($o)).'"' );
?>>
