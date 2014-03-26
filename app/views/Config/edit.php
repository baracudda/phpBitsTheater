<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

$w = "<h1 align=\"center\">Configuration Settings</h1>\n";
$w .= 'Use "?" to reset a setting to its default value ("\\?" to save just a question mark).'."<br />\n";
$w .= "<br />\n";
foreach ($v->config_areas as $ns => $nsInfo) {
	$v->_row_class = 1; //reset row counter back to 1 for each table created (resets the row formatting)
	$w .= "<h2>{$nsInfo['label']}</h2>";
	$w .= '<table class="data-entry">'."\n";
	$w .= '  <thead><tr class="rowh">'."\n";
	$w .= '    <th>Setting</th><th>Value</th><th>Description</th>'."\n";
	$w .= "  </tr></thead>\n";
	$w .= "  <tbody>\n";
	foreach ($v->getRes('config/'.$ns) as $theSetting => $theSettingInfo) {
		$theWidgetName = $ns.'__'.$theSetting;
		$cellLabel = '<td width="15%" class="data-label"><label for="'.$theWidgetName.'" >'.$theSettingInfo['label'].'</label></td>';
		$cellInput = '<td width="40%" class="data-field">';
		$theValue = $v->config->getConfigValue($ns,$theSetting);
		if (empty($theSettingInfo['input']) || $theSettingInfo['input']=='string') {
			$cellInput .= Widgets::createTextBox($theWidgetName,$theValue);
		} elseif ($theSettingInfo['input']=='boolean') {
			$cellInput .= Widgets::createCheckBox($theWidgetName,$theValue,!empty($theValue));
		}
		$cellInput .= '</td>';
		$cellDesc = '<td width="45%">'.$theSettingInfo['desc'].'</td>';

		$w .= '  <tr class='.$v->_row_class.'>'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	$w .= "  </tbody>\n";
    $w .= "</table><br/>\n";
}//end foreach
$w .= "<br/>\n";
$w .= $v->save_button;
$w .= "<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print $form_html;

$recite->includeMyFooter();
