<?php
use BitsTheater\scenes\Rights as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use BitsTheater\models\Groups;
$recite->includeMyHeader();
$w = '';

$theParentGroups = array();
foreach ($v->groups as $gid => $row) {
	if ($gid>1)
		$theParentGroups[$gid] = $row['group_name'];
}
$theParentGroupsJS = Strings::phpArray2jsArray($theParentGroups,'');
$jsCode = <<<EOD
$(document).ready(function(){
	var rg = new RightGroups('{$v->getSiteURL('rights/ajaxUpdateGroup')}',{$theParentGroupsJS}).setup();
});

EOD;

$w .= "<h2>{$v->getRes('permissions/title_groups')}</h2>";

$w .= "<br />\n";
$w .= $v->renderMyUserMsgsAsString();
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= "<br />\n";


$w .= '<div class="panel panel-default">';
//$w .= '<div class="panel-heading">Panel heading</div>';

$w .= '<table class="table">';
$w .= '<thead><tr class="rowh">';
$w .= '<th></th>';
$w .= "<th>{$v->getRes('permissions/colheader_group_id')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_name')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_parent')}</th>";
$w .= "<th>{$v->getRes('permissions/colheader_group_reg_code')}</th>";
$w .= '</tr></thead>'."\n";
$w .= "<tbody>\n";
foreach ($v->groups as $theGroup) {
	$r = '<tr class="'.$v->_rowClass.'">';
	
	$r .= '<td>';
	if ($theGroup['group_id']!=1) { //1 is super-admin group cannot be modified anyway
		$r .= '<button type="button" class="btn_edit_group btn btn-default btn-sm"';
		$r .= ' group_id="'.$theGroup['group_id'].'"';
		$r .= ' group_name="'.$theGroup['group_name'].'"';
		//$r .= ' group_desc="'.$theGroup['group_desc'].'"';
		$r .= ' group_parent="'.$theGroup['parent_group_id'].'"';
		if (!empty($v->group_reg_codes[$theGroup['group_id']]))
			$r .= ' group_reg_code="'.$v->group_reg_codes[$theGroup['group_id']]['reg_code'].'"';
		$r .= '><span class="glyphicon glyphicon-pencil"></span></button> ';
	}
	$r .= '</td>';
	
	$r .= '<td>'.$theGroup['group_id'].'</td>';
	
	if ($theGroup['group_id']==1) //super-admin group cannot be modified anyway
		$theLink = $theGroup['group_name'];
	else
		$theLink = '<a href="'.BITS_URL.'/rights/group/'.$theGroup['group_id'].'">'.$theGroup['group_name'].'</a>';
	$r .= '<td>'.$theLink.'</td>';

	$theGroupDesc = '';
	if ($theGroup['group_id']==1) //super-admin group cannot be subclassed
		$theGroupDesc = $v->getRes('permissions/display_group_1_desc');
	else {
		if ($theGroup['group_id']==0) { //guest, not logged in
			$theGroupDesc = $v->getRes('permissions/display_group_0_desc');
		}
		if (!empty($theGroup['parent_group_id'])) {
			$s = $v->getRes('permissions/display_parent_group/'.$v->groups[$theGroup['parent_group_id']]['group_name']);
			if (!empty($theGroupDesc)) {
				$theGroupDesc .= ' ('.$s.')';
			} else {
				$theGroupDesc .= $s;
			}
		}
	}
	$r .= '<td>'.$theGroupDesc.'</td>';
	
	$r .= '<td>';
	if (!empty($v->group_reg_codes[$theGroup['group_id']]))
		$r .= $v->group_reg_codes[$theGroup['group_id']]['reg_code'];
	$r .= '</td>';
	
	$r .= "</tr>\n";
	$w .= $r;
}//end foreach
$w .= "  </tbody>\n";
$w .= "</table><br/>\n";

$w .= '</div>'."\n";

$clsCannotCreate = ($v->isAllowed('auth','create')) ? '' : 'invisible';
$w .= '<button id="btn_add_group" type="button" class="btn btn-primary '.$clsCannotCreate.'"><span class="glyphicon glyphicon-plus"></span> Add Group</button> ';

print($w);
?>
	<div class="modal fade" id="dialog_group">
		<div class="modal-dialog "><!-- modal-lg -->
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			<h4 id="dialog_title" class="modal-title">Add Group</h4>
		  </div>
		  <div class="modal-body">
		  	<div class="form-group">
		  		<input type="hidden" name="group_id" id="group_id" />
		  		<label style="width:10em;text-align: right">Name:</label> <input style="width: 30ch" type="text" id="group_name" name="group_name" /><br>
		  		<br>
		  		<!--
		  		<label style="width:10em;text-align: right">Description:</label> <input style="width: 60ch" type="text" id="group_desc" name="group_desc" /><br>
		  		<br>
		  		-->
		  		<label style="width:10em;text-align: right">Parent:</label> <select id="group_parent" name="group_parent" ></select><br>
		  		<br>
		  		<label style="width:10em;text-align: right">Registration Code:</label> <input style="width: 40ch" type="text" id="group_reg_code" name="group_reg_code" /><br>
		  		<p style="padding-left:10em">When registering, a user supplying this code will automatically be a member of this group.</p><br>
			</div>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal" id="btn_close_dialog_add_group">Cancel</button>
			<button type="button" class="btn btn-primary" data-dismiss="modal" id="btn_save_dialog_add_group">Save</button>
		  </div>
		</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

<?php
$v->loadScript('RightGroups.js',WEBAPP_JS_URL);

print($v->createJsTagBlock($jsCode));
print(str_repeat('<br />',8));
$recite->includeMyFooter();
