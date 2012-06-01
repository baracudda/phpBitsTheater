<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

if ($recite->_dbError) {
	echo $recite->_dbError;
} else {
	echo '<div align="center" class="contentHeader"><div class="contentHeaderText">Assign Rights To Groups</div></div>';
	echo '<table border="0" align="center" width="100%" cellspacing="1" cellpadding="5" class="dataOutline" style="margin-top: 1px;">';
	echo '	<thead><tr class="listHeader">';
	echo '		<th>#</th>';
	echo '		<th>Right Group</th>';
	echo "	</tr></thead>\n";
	echo "	<tbody>\n";
	$i = 0;
	foreach ($v->groups as $theGroup) {
		$cellGroupId = '<td align="right">'.$theGroup['group_id'].'</td>';
		if ($theGroup['group_id']==1) //super-admin group cannot be modified anyway
			$theLink = $theGroup['group_name'];
		else
			$theLink = '<a href="'.BITS_URL.'/rights/group/'.$theGroup['group_id'].'">'.$theGroup['group_name'].'</a>';
		$cellGroupName = '<td align="left">'.$theLink.'</td>';

		//next statement alternates the row color
		$bcolor = 'class="row'.((++$i%2)+1).'"';
		echo '		<tr '.$bcolor.'>'.$cellGroupId.$cellGroupName."</tr>\n";
	}//end foreach

	echo "	</tbody>\n";
    echo "</table><br/>\n";
}
$recite->includeMyFooter();
