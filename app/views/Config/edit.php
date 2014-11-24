<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '';

$w .= "<h1 align=\"center\">Configuration Settings</h1>\n";
if ($msgs = $v->getUserMsgs()) {
	$w .= "<br />\n".$v->renderMyUserMsgsAsString()."<br />\n";
}
$w .= 'Use "?" to reset a setting to its default value ("\\?" to save just a question mark).'."<br />\n";
$w .= "<br />\n";
/* @var $theNamespaceInfo ConfigNamespaceInfo */
foreach ($v->config_areas as $theNamespaceInfo) {
	$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
	$w .= "<h2>{$theNamespaceInfo->label}</h2>";
	$w .= '<table class="db-entry">'."\n";
	$w .= '  <thead><tr class="rowh">'."\n";
	$w .= '    <th>Setting</th><th>Value</th><th>Description</th>'."\n";
	$w .= "  </tr></thead>\n";
	$w .= "  <tbody>\n";
	/* @var $theSettingInfo ConfigSettingInfo */
	foreach ($theNamespaceInfo->settings_list as $theSettingName => $theSettingInfo) {
		$theWidgetName = $theSettingInfo->getWidgetName();
		$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" >'.$theSettingInfo->label.'</label></td>';
		$cellInput = '<td class="db-field">';
		$cellInput .= $theSettingInfo->getInputWidget();
		$cellInput .= '</td>';
		$cellDesc = '<td class="data-desc">'.$theSettingInfo->desc.'</td>';

		$w .= '  <tr class="'.$v->_rowClass.' '.$theNamespaceInfo->namespace.'-'.$theSettingName.'">'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	$w .= "  </tbody>\n";
    $w .= "</table><br/>\n";
}//end foreach
$w .= "<br/>\n";
$w .= $v->save_button;
$w .= "<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print($form_html);
print(str_repeat('<br />',3));
$recite->includeMyFooter();
