<?php
use com\blackmoonit\Widgets;
print( $v->createCssTagBlock('td label{margin-bottom:initial}') );
?>
<div class="modal fade" id="dialog_group">
<div class="modal-dialog "><!-- modal-lg -->
<div class="modal-content">
  <div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h4 id="dialog_title_add" class="modal-title"><?php print($v->getRes('auth_groups/label_dialog_title_group_add'));?></h4>
	<h4 id="dialog_title_edit" class="modal-title"><?php print($v->getRes('auth_groups/label_dialog_title_group_edit'));?></h4>
  </div>
  <div class="modal-body">
  	<div class="form-group">
  		<input type="hidden" name="group_id" id="group_id" />
  		<label style="width:10em;text-align: right"><?php print($v->getRes('auth_groups/label_dialog_group_name')); ?>:</label>&nbsp;
  		<?php
  			print( Widgets::buildTextBox('group_name')->setSize(30)->setRequired()
				->setPlaceholder( $v->getRes('auth_groups/placeholder_group_name') )->render()
  			);
  		?><br>
  		<br>
  		<!--
  		<label style="width:10em;text-align: right">Description:</label> <input style="width: 60ch" type="text" id="group_desc" name="group_desc" /><br>
  		<br>
  		-->
  		<label style="width:10em;text-align: right"><?php print($v->getRes('auth_groups/label_dialog_group_parent')); ?>:</label>&nbsp;
  		<select id="group_parent" name="group_parent" ></select><br>
  		<br>
  		<label style="width:10em;text-align: right"><?php print($v->getRes('auth_groups/label_dialog_group_reg_code')); ?>:</label>&nbsp;
  		<?php
  			print( Widgets::buildTextBox('group_reg_code')->setSize(40)
  				->setPlaceholder( $v->getRes('auth_groups/placeholder_reg_code') )->render()
  			);
  		?><br>
  		<p style="padding-left:10em"><span id="helpBlock" class="help-block"><?php
  				print($v->getRes('auth_groups/hint_dialog_group_reg_code'));
  		?></span></p><br>
	</div>
  </div>
  <div class="modal-footer"><?php
  	print( Widgets::buildButton('btn_close_dialog_group')->addClass('btn-default')
  			->setDataAttr('dismiss', 'modal')
  			->append( $v->getRes('generic/label_button_cancel') )->render()
  	);
  	print( Widgets::buildButton('btn_save_dialog_group')->addClass('btn-primary')
  			->setDataAttr('dismiss', 'modal')
  			->append( $v->getRes('generic/save_button_text') )->render()
  	);
  ?></div>
</div><span title=".modal-content" ARIA-HIDDEN></span>
</div><span title=".modal-dialog" ARIA-HIDDEN></span>
</div><span title=".modal #dialog_group" ARIA-HIDDEN></span>
