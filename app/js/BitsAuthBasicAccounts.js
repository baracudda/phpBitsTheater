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
		this.mDialogAccount.on('click', '#btn_save_dialog_account', this.asCB('onSaveClick'));
	}
});

BitsAuthBasicAccounts.prototype.getGroupIdArray = function() {
	var theGroupIds = $('input:checkbox:checked',$('#list_account_groups',this.mDialogAccount)).map(function(){
		return this.value;
    }).toArray();
	if (theGroupIds.length<1)
		theGroupIds = undefined;
	return theGroupIds;
}

BitsAuthBasicAccounts.prototype.setGroupIdArray = function(aList) {
	if (aList) {
		var theGroupIds = $('input:checkbox',$('#list_account_groups',this.mDialogAccount)).each(function(){
			if ($.inArray(Number(this.value),aList)<0)
				$(this).prop('checked',false);
			else
				$(this).prop('checked',true);
	    });
	}
}

BitsAuthBasicAccounts.prototype.resetDialog = function() {
	//visibility reset
	$('#list_account_groups',this.mDialogAccount).show();
	$('#empty_text',this.mDialogAccount).hide();
	//value reset
	$('#account_id',this.mDialogAccount).val('-1');
	$('#account_name',this.mDialogAccount).val('');
	$('#email',this.mDialogAccount).val('');
	$('#account_is_active',this.mDialogAccount).prop('checked', true);
	$('#account_password',this.mDialogAccount).val('');
	this.setGroupIdArray(null);
};

BitsAuthBasicAccounts.prototype.onAddClick = function(e) {
	e.preventDefault();
	$('#dialog_title_add',this.mDialogAccount).show();
	$('#dialog_title_edit',this.mDialogAccount).hide();
	$('#row_account_password',this.mDialogAccount).show();
	this.mUrlToUse = this.mUrlCreateAccount;
	this.resetDialog(e);
	this.mDialogAccount.modal('show');
};

BitsAuthBasicAccounts.prototype.onEditClick = function(e) {
	e.preventDefault();
	$('#dialog_title_add',this.mDialogAccount).hide();
	$('#dialog_title_edit',this.mDialogAccount).show();
	$('#row_account_password',this.mDialogAccount).hide();
	this.mUrlToUse = this.mUrlUpdateAccount;
	this.resetDialog(e);
	
	var id = e.currentTarget.getAttribute('data-account_id');
	var an = e.currentTarget.getAttribute('data-account_name');
	var ae = e.currentTarget.getAttribute('data-email');
	var ia = e.currentTarget.getAttribute('data-is_active');
	var gl = $.parseJSON(e.currentTarget.getAttribute('data-groups'));
	
	$('#account_id',this.mDialogAccount).val(id);
	$('#account_name',this.mDialogAccount).val(an);
	$('#email',this.mDialogAccount).val(ae);
	//in JS: "0"==false is TRUE whereas if ("0") is FALSE
	$('#account_is_active',this.mDialogAccount).prop('checked', ia!=false);
	this.setGroupIdArray(gl);
	if (gl.indexOf(1)>=0) {
		$('#list_account_groups',this.mDialogAccount).hide();
		$('#empty_text',this.mDialogAccount).show();
	} else {
		$('#list_account_groups',this.mDialogAccount).show();
		$('#empty_text',this.mDialogAccount).hide();
	}
	this.mDialogAccount.modal('show');
};

BitsAuthBasicAccounts.prototype.onSaveClick = function(e) {
	var id = Number($('#account_id',this.mDialogAccount).val());
	if(id<0)id=undefined;
	var an = $('#account_name',this.mDialogAccount).val();
	if(an=="")an=undefined;
	var ap = $('#account_password',this.mDialogAccount).val();
	if(ap=="")ap=undefined;
	var ae = $('#email',this.mDialogAccount).val();
	if(ae=="")ae=undefined;
	var ia = $('#account_is_active',this.mDialogAccount).prop('checked') ? 1 : 0;
	var theGroupIds = this.getGroupIdArray();
	//console.log(theGroupIds);
	//pw not needed on update
	if (an && ae && (ap || this.mUrlToUse==this.mUrlUpdateAccount)) {
		$.post(this.mUrlToUse,{
			account_id: id
			,
			account_name: an
			,
			email: ae
			,
			account_password: ap
			,
			account_is_active: ia
			,
			account_group_ids: theGroupIds
		})
		.then (function (data) {
			window.location.reload();
		});
	}
}
