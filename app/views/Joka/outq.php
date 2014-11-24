<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;
use com\blackmoonit\Strings;
use \DateInterval;
use \DateTime;
$recite->includeMyHeader();
$w = '';

$jsCode = <<<EOD
function showRow(id) {
	document.getElementById(id).style.display="table-row";
	document.getElementById(id+"_hide").style.display="inline";
	document.getElementById(id+"_show").style.display="none";
}

function hideRow(id) {
	document.getElementById(id).style.display="none";
	document.getElementById(id+"_show").style.display="inline";
	document.getElementById(id+"_hide").style.display="none";
}

EOD;

$w .= '<h2>Communication Log</h2>';
$w .= "<br />\n";

$w .= "<br />\n";
$w .= $v->renderMyUserMsgsAsString();
$w .= "<br />\n";

if (!empty($v->results)) {
	$thePager = $v->getPagerHtml('commlog');
	$w .= $thePager;
	
	$w .= '<table id="joka_outq" class="db-display">';

	//header row
	$w .= '<thead><tr class="rowh">';
	
	//$w .= '<th>Package Name</th>';
	$w .= '<th class="col_sender">Sender</th>';
	//$w .= '<th>Transmit</th>';
	//$w .= '<th>Received</th>';
	$w .= '<th class="col_timestamps">Timestamps</th>';
	$w .= '<th class="col_payload_info">Payload</th>';
	
	$w .= '</tr></thead><tbody>';
	//body rows
	foreach((array) $v->results as $theResultRow) {
		$theBodyRow = '<tr class="'.$v->_rowClass.'">';

		//$theBodyRow .= '<td>'.$theResultRow['payload_id'].'</td>';
		$theBodyRow .= '<td id="sender" class="col_sender">'.$theResultRow['package_name'].'<br />'.$theResultRow['device_id'].'</td>';
		//$theBodyRow .= '<td id="field_device_id">'.$theResultRow['device_id'].'</td>';
		//$theBodyRow .= '<td id="field_transmit_ts">'.$theResultRow['transmit_ts'].'</td>';
		//$theBodyRow .= '<td id="field_received_ts">'.$theResultRow['received_ts'].'</td>';
		$theBodyRow .= '<td id="timestamps" class="col_timestamps">Tx:'.$theResultRow['transmit_ts'].'<br />Rx:'.$theResultRow['received_ts'].'</td>';

		$theBodyRow .= '<td id="payload_info" class="col_payload_info">ID: '.$theResultRow['payload_id'].'<br />';
		$theFullPayload = '';
		if (strlen($theResultRow['payload'])>50) {
			$theBodyRow .= substr($theResultRow['payload'],0,50).'…';
			$theDivId = 'payload_'.$theResultRow['payload_id'];
			$theBodyRow .= '<button id="'.$theDivId.'_show" type="button" onClick="showRow(\''.$theDivId.'\')">Show</button>';
			$theBodyRow .= '<button id="'.$theDivId.'_hide" type="button" onClick="hideRow(\''.$theDivId.'\')" style="display:none;">Hide</button>';
			$theFullPayload .= '<tr id="'.$theDivId.'" style="display:none;"><td class="col_log_id"> </td><td colspan="4">';
			$theFullPayload .= $theResultRow['payload'];
			$theFullPayload .= '</td></tr>';
		} else {
			$theBodyRow .= $theResultRow['payload'];
		}
		$theBodyRow .= '</td>';
		
		$w .= $theBodyRow.'</tr>';
		if (!empty($theFullPayload)) {
			$w .= $theFullPayload;
		}
	}
		
	$w .= '</tbody></table>';
	$w .= $thePager;
	
	$w .= str_repeat('<br />',2);
	$w .= '<a href="#" class="scrollup">Jump to top</a>';
}

$w .= str_repeat('<br />',8);
$w .= $v->createJsTagBlock($jsCode);
print($w);
//print($v->debugPrint(str_replace(' ','.&nbsp;.',$v->debugStr($v->results,'<br>'))));
$recite->includeMyFooter();
