<?php
use com\blackmoonit\Widgets;
//fix bootstrap button location on dialog
print( $v->createCssTagBlock('td label{margin-bottom:initial;}') );
//fix checkbox label that is not truely a label (bootstrap override)
print( $v->createCssTagBlock('label.for-checkbox{font-weight:normal;}') );
//prevent new account pw input from pw keepers
print( $v->createCssTagBlock('#account_password{background-image:none !important;}') );
?>
<div class="modal fade" id="dialog_account">
<div class="modal-dialog "><!-- modal-lg -->
<div class="modal-content">
  <div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h4 id="dialog_title_add" class="modal-title"><?php print($v->getRes('account/label_dialog_title_account_add'));?></h4>
	<h4 id="dialog_title_edit" class="modal-title"><?php print($v->getRes('account/label_dialog_title_account_edit'));?></h4>
  </div>
  <div class="modal-body">
  	<div class="form-account">
  		<input type="hidden" name="account_id" id="account_id" />
  		<table>
  		<tr>
  			<td style="padding:1em;width:5em;text-align:right">
  				<label><?php print($v->getRes('account/colheader_account_name'));?>:</label>
  			</td>
  			<td style="padding:1em"><?php
  				print( Widgets::buildTextBox('account_name')->setRequired()->setSize(40)
  						->setAttr('autocomplete', 'off')
  						->render()
  				);
  			?></td>
  		</tr>
  		<tr>
	  		<td style="padding:1em;width:5em;text-align:right">
	  			<label><?php print($v->getRes('account/colheader_email'));?>:</label>
	  		</td>
	  		<td style="padding:1em"><?php
  				print( Widgets::buildEmailBox('email')->setRequired()->setSize(40)
  						->setAttr('autocomplete', 'off')
  						->render()
  				);
	  		?></td>
  		</tr>
  		<tr id="row_account_password">
  			<td style="padding:1em;width:5em;text-align:right">
  				<label><?php print($v->getRes('account/label_pwinput'));?>:</label>
  			</td>
  			<td style="padding:1em;"><?php
  				print( Widgets::buildPassBox('account_password')->setSize(40)
  						->setAttr('autocomplete', 'new-password')
  						->render()
  				);
	  		?></td>
  		</tr>
  		<tr>
  			<td style="padding:1em;width:5em;text-align:right">
  				<label><?php print($v->getRes('account/colheader_account_is_active'));?>:</label>
  			</td>
  			<td style="padding:1em"><input type="checkbox" id="account_is_active" name="account_is_active"
  			<?php if( !$this->isAllowed( 'accounts', 'activate' ) ): ?>disabled<?php endif; ?>
  			></td>
  		</tr>
  		<tr>
  		  <td style="padding:1em;width:5em;text-align:right;vertical-align:top;">
  		  	<label><?php print($v->getRes('account/label_dialog_auth_groups')); ?>:</label>
  		  </td>
  		  <td style="padding:1em"><div id="list_account_groups">
  		  	<?php
		  		foreach ($v->auth_groups as $gid => $row) {
					if ($gid>1) {
						print('<label class="for-checkbox">'
								. '<input type="checkbox" name="account_group_ids[]" value="'
								. $gid . '" /> '
								. htmlentities($row['group_name']) . '</label><br />' . PHP_EOL
						);
					}
				}
	  		?></div>
			<div id="empty_text"><?php print($v->getRes('auth_groups/group_names/1'));?></div>
  		  </td>
  		</tr>
  		</table>
  		<br>
  	</div>
  </div>
  <div class="modal-footer"><?php
  	print( Widgets::buildButton('btn_close_dialog_account')->addClass('btn-default')
  			->setDataAttr('dismiss', 'modal')
  			->append( $v->getRes('generic/label_button_cancel') )->render()
  	);
  	print( Widgets::buildButton('btn_save_dialog_account')->addClass('btn-primary')
  			->setDataAttr('dismiss', 'modal')
  			->append( $v->getRes('generic/save_button_text') )->render()
  	);
  ?></div>
</div><span title=".modal-content" ARIA-HIDDEN></span>
</div><span title=".modal-dialog" ARIA-HIDDEN></span>
</div><span title=".modal #dialog_account" ARIA-HIDDEN></span>
