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
		this.mDialogGroup.on('click', '#btn_save_dialog_group', this.asCB('onGroupSaveClick'));
		
		if (this.mGroups) {
			var e = $('#group_parent',this.mDialogGroup);
			e.append($('<option value="-1" selected></option>'));
			$.each(this.mGroups, function(key, row) {
				e.append($("<option></option>").attr("value",key).text(row));
			});
		}
	}
});

BitsRightGroups.prototype.onAddGroupClick = function(e) {
	e.preventDefault();
	$('#dialog_title_add',this.mDialogGroup).show();
	$('#dialog_title_edit',this.mDialogGroup).hide();

	$('#group_id',this.mDialogGroup).val('-1');
	$('#group_name',this.mDialogGroup).val('');
	//$('#group_desc',this.mDialogGroup).val('');
	
	//reset parent dropdown to default
	$('#group_parent',this.mDialogGroup).val('');
	$('select option',this.mDialogGroup).prop('disabled',false);

	$('#group_reg_code',this.mDialogGroup).val('');

	this.mDialogGroup.modal('show');
};

BitsRightGroups.prototype.onEditGroupClick = function(e) {
	e.preventDefault();
	$('#dialog_title_add',this.mDialogAccount).hide();
	$('#dialog_title_edit',this.mDialogAccount).show();
	
	var id = e.currentTarget.getAttribute('data-group_id');
	var gn = e.currentTarget.getAttribute('data-group_name');
	//var gd = e.currentTarget.getAttribute('data-group_desc');
	var gp = e.currentTarget.getAttribute('data-group_parent');
	var rc = e.currentTarget.getAttribute('data-group_reg_code');
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
	
	this.mDialogGroup.modal('show');
};

BitsRightGroups.prototype.onGroupSaveClick = function(e) {
	var thisone = this;
	var id = Number($('#group_id',this.mDialogGroup).val());
	var gn = $('#group_name',this.mDialogGroup).val();
	//var gd = $('#group_desc',this.mDialogGroup).val();
	var gp = Number($('#group_parent',this.mDialogGroup).val());
	if(gp<2)gp=undefined;
	var rc = $('#group_reg_code',this.mDialogGroup).val();
	//console.log('SAVE group click id='+id+' gn='+gn+' gp='+gp+' rc='+rc);
	if (gn) {
		$.post(thisone.mUrlUpdateGroup,{
			group_id: id
			,
			group_name: gn
			,
			parent_group_id: gp
			,
			reg_code: rc
		})
		.then (function (data) {
			window.location.reload();
		})
	}
}
