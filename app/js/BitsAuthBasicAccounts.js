var BitsAuthBasicAccounts = BaseClass.extend({
	mUrlCreateAccount: null
	,
	mUrlUpdateAccount: null
	,
	mDialogAccount: null
	,
	mUrlToUse: null
	,
	onCreate: function(aUrlCreateAccount, aUrlUpdateAccount) {
		this.mUrlCreateAccount = aUrlCreateAccount;
		this.mUrlUpdateAccount = aUrlUpdateAccount;
	}
	,
	setup: function() {
		this.mDialogAccount = $('#dialog_account');
		$(document).on('click', '#btn_add_account', this.asCB('onAddClick'));
		$(document).on('click', '.btn_edit_account', this.asCB('onEditClick'));
		this.mDialogAccount.on('click', '#btn_save_dialog_add_account', this.asCB('onSaveClick'));
	}
});

BitsAuthBasicAccounts.prototype.resetDialog = function(e) {
	//visibility reset
	$('#list_account_groups',this.mDialogAccount).show();
	$('#empty_text',this.mDialogAccount).hide();
	//value reset
	$('#account_id',this.mDialogAccount).val('-1');
	$('#account_name',this.mDialogAccount).val('');
	$('#email',this.mDialogAccount).val('');
	$('#account_is_active',this.mDialogAccount).prop('checked', true);
	$('#account_password',this.mDialogAccount).val('');
	/*
	//reset parent dropdown to default
	$('#group_parent',this.mDialogAccount).val('');
	$('select option',this.mDialogAccount).prop('disabled',false);
	*/
};

BitsAuthBasicAccounts.prototype.onAddClick = function(e) {
	$('#dialog_title_add',this.mDialogAccount).show();
	$('#dialog_title_edit',this.mDialogAccount).hide();
	$('#row_account_password',this.mDialogAccount).show();
	this.mUrlToUse = this.mUrlCreateAccount;
	this.resetDialog(e);
	this.mDialogAccount.modal('show');
};

BitsAuthBasicAccounts.prototype.onEditClick = function(e) {
	$('#dialog_title_add',this.mDialogAccount).hide();
	$('#dialog_title_edit',this.mDialogAccount).show();
	$('#row_account_password',this.mDialogAccount).hide();
	this.mUrlToUse = this.mUrlUpdateAccount;
	this.resetDialog(e);
	
	var id = e.currentTarget.getAttribute('data-account_id');
	var an = e.currentTarget.getAttribute('data-account_name');
	var ae = e.currentTarget.getAttribute('data-email');
	var ia = e.currentTarget.getAttribute('data-is_active');
	var gl = e.currentTarget.getAttribute('data-groups');
	
	$('#account_id',this.mDialogAccount).val(id);
	$('#account_name',this.mDialogAccount).val(an);
	$('#email',this.mDialogAccount).val(ae);
	//in JS: "0"==false is TRUE whereas if ("0") is FALSE
	$('#account_is_active',this.mDialogAccount).prop('checked', ia!=false);
	if (gl.indexOf(1)>=0) {
		$('#list_account_groups',this.mDialogAccount).hide();
		$('#empty_text',this.mDialogAccount).show();
	} else {
		$('#list_account_groups',this.mDialogAccount).show();
		$('#empty_text',this.mDialogAccount).hide();
	}
	/*
	//reset parent dropdown to default
	$('#group_parent',this.mDialogAccount).val('');
	$('select option',this.mDialogAccount).prop('disabled',false);
	//disable "ourselves" from being selected
	var e = $('select option[value="'+id+'"]',this.mDialogAccount);
	if (e)
		e.attr('disabled','disabled');
	//auto-select current selection
	if (gp) {
		$('select option[value="'+gp+'"]',this.mDialogAccount).attr('selected','selected');
	}
	*/
	
	this.mDialogAccount.modal('show');
};

BitsAuthBasicAccounts.prototype.onSaveClick = function(e) {
	var id = $('#account_id',this.mDialogAccount).val();
	var an = $('#account_name',this.mDialogAccount).val();
	var ae = $('#email',this.mDialogAccount).val();
	var ia = $('#account_is_active',this.mDialogAccount).val();
	//var rc = $('#group_reg_code',this.mDialogAccount).val();
	if (an) {
		$.post(this.mUrlToUse,{
			account_id: id
			,
			account_name: an
			,
			email: ae
			,
			account_password: null 
		})
		.then (function (data) {
			window.location.reload();
		});
	}
}
