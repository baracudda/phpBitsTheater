<?php
use BitsTheater\Scene; /* @var $v Scene */
?><meta itemprop="admin-js-dialog_update_schema" data-bootbox_dialog_argument=<?php
	$o = new \StdClass();
	//$o->title = $v->getRes('admin/dialog_update_warning_title');
	//$o->size = 'small';
	//$o->backdrop = true;
	$o->message = '<div class="panel panel-warning">'
			. '<div class="panel-heading">'
			. '<h3 class="panel-title"><span class="glyphicon glyphicon-exclamation-sign"></span> '
			. $v->getRes('admin/dialog_update_warning_title')
			. '</h3></div>'
			. '<div class="panel-body">'
			. '<p class="lead"><b>'
			. $v->getRes('admin/dialog_update_warning_msg')
			. '</b></p>'
			. '</div>'
			. '<div class="panel panel-info"><div style="margin:5px" class="panel-heading heading-3">'
			. '<span class="glyphicon glyphicon-question-sign"></span> '
			. $v->getRes('admin/dialog_update_warning_tip')
			. '</div></div>'
			. '</div>'
			;
	$o->buttons = new \StdClass();
	$o->buttons->cancel = new \StdClass();
	$o->buttons->cancel->label = (!empty($v->dialog_cancel_label))
		? $v->dialog_cancel_label : $v->getRes('admin/dialog_update_warning_btn_cancel');
	$o->buttons->cancel->className = (!empty($v->dialog_cancel_class))
		? $v->dialog_cancel_class : 'btn-default';
	$o->buttons->success = new \StdClass();
	$o->buttons->success->label = $v->getRes('admin/dialog_update_warning_btn_update');
	$o->buttons->success->className = (!empty($v->dialog_success_class))
		? $v->dialog_success_class : 'btn-danger';
	print( '"'.htmlentities(json_encode($o)).'"' );
?>>
