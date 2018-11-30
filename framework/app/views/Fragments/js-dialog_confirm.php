<meta itemprop="<?php
	if ( !empty($v->dialog_itemprop) ) {
		print($v->dialog_itemprop);
	}
	else {
		print('js-dialog_confirm');
	}
?>" data-bootbox_dialog_argument="<?php
	$o = new \StdClass();
	if (!empty($v->dialog_title))
		$o->title = $v->dialog_title;
	$o->size = 'small';
	//$o->backdrop = true;
	$o->buttons = new \StdClass();
	$o->buttons->cancel = new \StdClass();
	$o->buttons->cancel->label = (!empty($v->dialog_cancel_label))
		? $v->dialog_cancel_label : $v->getRes('generic/label_button_cancel');
	$o->buttons->cancel->className = (!empty($v->dialog_cancel_class))
		? $v->dialog_cancel_class : 'btn-default';
	$o->buttons->success = new \StdClass();
	$o->buttons->success->label = (!empty($v->dialog_success_label))
		? $v->dialog_success_label : $v->getRes('generic/label_button_ok');
	$o->buttons->success->className = (!empty($v->dialog_success_class))
		? $v->dialog_success_class : 'btn-success';
	if ( !empty($v->dialog_message) ) {
		$o->message = $v->dialog_message;
	}
	print(htmlentities(json_encode($o)));
?>">
