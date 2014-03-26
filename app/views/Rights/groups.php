<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = '<h2>Assign Rights To Groups</h2>';
if (isset($v->err_msg)) {
	$w .= '<span class="msg-error">'.$v->err_msg.'</span>';
}
$w .= "<table><thead><tr class=\"rowh\"><th>#</th><th>Right Group</th></tr></thead>\n";
$w .= "  <tbody>\n";
foreach ($v->groups as $theGroup) {
	$cellGroupId = '<td align="right">'.$theGroup['group_id'].'</td>';
	if ($theGroup['group_id']==1) //super-admin group cannot be modified anyway
		$theLink = $theGroup['group_name'];
	else
		$theLink = '<a href="'.BITS_URL.'/rights/group/'.$theGroup['group_id'].'">'.$theGroup['group_name'].'</a>';
	$cellGroupName = '<td align="left">'.$theLink.'</td>';
	$w .= '    <tr class='.$v->_row_class.'>'.$cellGroupId.$cellGroupName."</tr>\n";
}//end foreach
$w .= "  </tbody>\n";
$w .= "</table><br/>\n";

print $w;
$recite->includeMyFooter();
