<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use com\blackmoonit\Widgets;

$w = '';
if (!empty($v->results)) {
//print($v->debugStr($v->results));
	$w .= Widgets::buildButton('btn_save_settings')->addClass('btn-primary')
			->addClass('btn-save-settings')->append($v->getRes('generic/save_button_text'))
			->render()
			;
	/* @var $theNamespaceInfo ConfigNamespaceInfo */
	foreach ($v->results as $arrayNamespaceInfo) {
		//DEBUG if ($arrayNamespaceInfo['namespace']=='site') { print($v->debugStr($arrayNamespaceInfo)); print('<br><br>'); }
		$theNamespaceInfo = ConfigNamespaceInfo::fromArray($v->getDirector(), $arrayNamespaceInfo);
		//DEBUG if ($theNamespaceInfo->namespace=='site') { print($v->debugStr($theNamespaceInfo)); print('<br><br>'); }
		$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
		$w .= '<table class="db-entry" style="padding:10px;">' . PHP_EOL;
		$w .= '<caption style="text-align:left">'.$theNamespaceInfo->label.'</caption>';
		$w .= '  <thead><tr class="rowh">' . PHP_EOL;
		$w .=     '<th>' . $v->getRes('config/colheader_setting_name') . '</th>';
		$w .=     '<th>' . $v->getRes('config/colheader_setting_value') . '</th>';
		$w .=     '<th>' . $v->getRes('config/colheader_setting_desc') . '</th>' . PHP_EOL;
		$w .= "  </tr></thead>" . PHP_EOL;
		$w .= "  <tbody>" . PHP_EOL;
		/* @var $theSettingInfo ConfigSettingInfo */
		foreach ($theNamespaceInfo->settings_list as $theSettingInfo) {
			$theWidgetName = $theSettingInfo->getWidgetName();
			if ($theSettingInfo->mSettingInfo->input_type!==ConfigSettingInfo::INPUT_ACTION) {
				//DEBUG if ($theSettingInfo->key==='security') {print($v->debugStr($theSettingInfo)); print('<br><br>');} //DEBUG
				$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" >'
						. htmlentities($theSettingInfo->getLabel()) . '</label></td>';
				$cellInput = '<td class="db-field">'.$theSettingInfo->getInputWidget().'</td>';
			} else {
				$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" ></label></td>';
				$theAction = "exec_action('{$theSettingInfo->mSettingInfo->default_value}')";
				$cellInput = '<td class="db-field">';
				$cellInput .= Widgets::buildButton($theWidgetName)->addClass('btn-warning')
						->append(htmlentities($theSettingInfo->getLabel()))
						->setAttr('onclick', $theAction)
						->render()
						;
			}
			$cellDesc = '<td class="">'.htmlentities($theSettingInfo->getDescription()).'</td>';

			$w .= '  <tr class="'.$v->_rowClass.' '.$theNamespaceInfo->namespace.'-'.$theSettingInfo->key.'">'
					.$cellLabel.$cellInput.$cellDesc."</tr>\n";
		}//end foreach
		$w .= "  </tbody>";
	    $w .= '</table>';
	    $w .= '<br />' . PHP_EOL;
	}//end foreach
	$w .= "<br/>\n";
	$w .= Widgets::buildButton('btn_save_settings2')->addClass('btn-primary')
			->addClass('btn-save-settings')->append($v->getRes('generic/save_button_text'))
			->render()
			;
} else {
	$w .= $v->getRes('generic/msg_nothing_found');
}
print($w);
