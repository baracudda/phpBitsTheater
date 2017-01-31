<?php
use com\blackmoonit\Widgets;
?>
<div class="modal fade" id="dialog_group">
<div class="modal-dialog "><!-- modal-lg -->
<div class="modal-content">
  <div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h4 id="dialog_title" class="modal-title">Add Role</h4>
  </div>
  <div class="modal-body">
  	<div class="form-group">
  		<input type="hidden" name="group_id" id="group_id" />
  		<label style="width:10em;text-align: right">Name:</label> <?php
  			print( Widgets::buildTextBox('group_name')->setSize(30)->setRequired()
				->setPlaceholder( $v->getRes('auth_groups/placeholder_group_name') )->render() );
  		?><br>
  		<!--
  		<label style="width:10em;text-align: right">Description:</label> <input style="width: 60ch" type="text" id="group_desc" name="group_desc" /><br>
  		<br>
  		-->
  		<label style="width:10em;text-align: right">Parent:</label> <select id="group_parent" name="group_parent" ></select><br>
  		<br>
  		<label style="width:10em;text-align: right">Registration Code:</label> <?php
  			print( Widgets::buildTextBox('group_reg_code')->setSize(40)
  				->setPlaceholder( $v->getRes('auth_groups/placeholder_reg_code') )->render() );
  		?><br>
  		<p style="padding-left:10em">When registering, a user supplying this code will automatically be a member of this role.</p><br>
	</div>
  </div>
  <div class="modal-footer">
	<button type="button" class="btn btn-default" data-dismiss="modal" id="btn_close_dialog_add_group">Cancel</button>
	<button type="button" class="btn btn-primary" data-dismiss="modal" id="btn_save_dialog_add_group">Save</button>
  </div>
</div><span title=".modal-content" ARIA-HIDDEN></span>
</div><span title=".modal-dialog" ARIA-HIDDEN></span>
</div><span title=".modal #dialog_group" ARIA-HIDDEN></span>
