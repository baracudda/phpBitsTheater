<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;

$w = '';
if (!empty($v->results)) {
//print($v->debugStr($v->results));
	$w .= '<button type="button" id="btn_save_settings" class="btn btn-primary btn-save-settings">'.$v->getRes('generic/save_button_text').'</button>';
	/* @var $theNamespaceInfo ConfigNamespaceInfo */
	foreach ($v->results as $arrayNamespaceInfo) {
		//DEBUG if ($arrayNamespaceInfo['namespace']=='site') { print($v->debugStr($arrayNamespaceInfo)); print('<br><br>'); }
		$theNamespaceInfo = ConfigNamespaceInfo::fromArray($v->getDirector(), $arrayNamespaceInfo);
		//DEBUG if ($theNamespaceInfo->namespace=='site') { print($v->debugStr($theNamespaceInfo)); print('<br><br>'); }
		$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
		$w .= "<h2>{$theNamespaceInfo->label}</h2>";
		$w .= '<table class="db-entry">'."\n";
		$w .= '  <thead><tr class="rowh">'."\n";
		$w .= '    <th>Setting</th><th>Value</th><th>Description</th>'."\n";
		$w .= "  </tr></thead>\n";
		$w .= "  <tbody>\n";
		/* @var $theSettingInfo ConfigSettingInfo */
		foreach ($theNamespaceInfo->settings_list as $theSettingInfo) {
			//DEBUG if ($theSettingInfo->key==='security') {print($v->debugStr($theSettingInfo)); print('<br><br>');} //DEBUG
			$theWidgetName = $theSettingInfo->getWidgetName();
			$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" >'.$theSettingInfo->getLabel().'</label></td>';
			$cellInput = '<td class="db-field">';
			$cellInput .= $theSettingInfo->getInputWidget();
			$cellInput .= '</td>';
			$cellDesc = '<td class="">'.$theSettingInfo->getDescription().'</td>';

			$w .= '  <tr class="'.$v->_rowClass.' '.$theNamespaceInfo->namespace.'-'.$theSettingInfo->key.'">'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
		}//end foreach
		$w .= "  </tbody>\n";
		$w .= "</table><br/>\n";
	}//end foreach
	$w .= "<br/>\n";
	//$w .= '<br/>'.Widgets::createSubmitButton('submit_save', $v->save_button_text)."\n";
	$w .= '<button type="button" id="btn_save_settings2" class="btn btn-primary btn-save-settings">'.$v->getRes('generic/save_button_text').'</button>';
} else {
	$w .= $v->getRes('generic/msg_nothing_found');
}
print($w);
