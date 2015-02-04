var BitsRightGroups = BaseClass.extend({
	mUrlUpdateGroup: null
	,
	mDialogGroup: null
	,
	mGroups: null
	,
	onCreate: function(aUrlUpdateGroup, aGroups) {
		this.mUrlUpdateGroup = aUrlUpdateGroup;
		this.mGroups = aGroups;
	}
	,
	setup: function() {
		this.mDialogGroup = $('#dialog_group');
		$(document).on('click', '#btn_add_group', this.asCB('onAddGroupClick'));
		$(document).on('click', '.btn_edit_group', this.asCB('onEditGroupClick'));
		this.mDialogGroup.on('click', '#btn_save_dialog_add_group', this.asCB('onGroupSaveClick'));
		
		if (this.mGroups) {
			var e = $('#group_parent',this.mDialogGroup);
			e.append($('<option value selected></option>'));
			$.each(this.mGroups, function(key, row) {
				e.append($("<option></option>").attr("value",key).text(row));
			});
		}
	}
});

BitsRightGroups.prototype.onAddGroupClick = function(e) {
	$('#dialog_title',this.mDialogGroup).html('Add Group');

	$('#group_id',this.mDialogGroup).val('-1');
	$('#group_name',this.mDialogGroup).val('');
	//$('#group_desc',this.mDialogGroup).val('');
	
	//reset parent dropdown to default
	$('#group_parent',this.mDialogGroup).val('');
	$('select option',this.mDialogGroup).prop('disabled',false);

	$('#group_reg_code',this.mDialogGroup).val('');

	this.mDialogGroup.modal('toggle');
};

BitsRightGroups.prototype.onEditGroupClick = function(e) {
	$('#dialog_title',this.mDialogGroup).html('Edit Group');
	
	var id = e.currentTarget.getAttribute('group_id');
	var gn = e.currentTarget.getAttribute('group_name');
	//var gd = e.currentTarget.getAttribute('group_desc');
	var gp = e.currentTarget.getAttribute('group_parent');
	var rc = e.currentTarget.getAttribute('group_reg_code');
	$('#group_id',this.mDialogGroup).val(id);
	$('#group_name',this.mDialogGroup).val(gn);
	//$('#group_desc',this.mDialogGroup).val(gd);
	
	//reset parent dropdown to default
	$('#group_parent',this.mDialogGroup).val('');
	$('select option',this.mDialogGroup).prop('disabled',false);
	//disable "ourselves" from being selected
	var e = $('select option[value="'+id+'"]',this.mDialogGroup);
	if (e)
		e.attr('disabled','disabled');
	//auto-select current selection
	if (gp) {
		$('select option[value="'+gp+'"]',this.mDialogGroup).attr('selected','selected');
	}

	$('#group_reg_code',this.mDialogGroup).val(rc);
	
	this.mDialogGroup.modal('toggle');
};

BitsRightGroups.prototype.onGroupSaveClick = function(e) {
	var thisone = this;
	var id = $('#group_id',this.mDialogGroup).val();
	var gn = $('#group_name',this.mDialogGroup).val();
	//var gd = $('#group_desc',this.mDialogGroup).val();
	var gp = $('#group_parent',this.mDialogGroup).val();
	var rc = $('#group_reg_code',this.mDialogGroup).val();
	console.log('SAVE group click id='+id+' gn='+gn+' gp='+gp+' rc='+rc);
	if (gn) {
		$.post(thisone.mUrlUpdateGroup,{
			group_id: id
			,
			group_name: gn
			,
			group_parent: gp
			,
			group_reg_code: rc
		})
		.then (function (data) {
			window.location.reload();
		})
	}
}
