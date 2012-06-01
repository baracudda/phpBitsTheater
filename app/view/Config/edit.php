<?php
use \com\blackmoonit\Widgets;
$recite->includeMyHeader();

echo '<div align="center" class="contentHeader">';
echo '  <div class="contentHeaderText">GEMS Settings: </div>';
echo "</div>\n";
$w = '';
foreach ($v->config_areas as $ns => $nsInfo) {
	$w .= '<div align="left" class="contentHeader">';
	//$w .= '  <div class="contentHeaderText">'.$nsInfo['label'].' ('.$nsInfo['desc'].')</div>';
	$w .= '  <div class="contentHeaderText">'.$nsInfo['label'].'</div>';
	$w .= "</div>\n";
	$w .= '<table border="0" align="center" width="100%" cellspacing="1" cellpadding="5" class="dataOutline" style="margin-top: 1px;">'."\n";
	$w .= '  <thead><tr class="listHeader">'."\n";
	$w .= '    <th>Setting</th><th>Value</th><th>Description</th>'."\n";
	$w .= "  </tr></thead>\n";
	$w .= "  <tbody>\n";
	$i = 0;
	foreach ($v->getRes('config/'.$ns) as $theSetting => $theSettingInfo) {
		$theWidgetName = $ns.'__'.$theSetting;
		$cellLabel = '<td align="right" width="15%"><label for="'.$theWidgetName.'" >'.$theSettingInfo['label'].'</label></td>';
		$cellInput = '<td align="left" width="40%">';
		$theValue = $v->config->getConfigValue($ns,$theSetting);
		if (empty($theSettingInfo['input']) || $theSettingInfo['input']=='string') {
			$cellInput .= Widgets::createTextBox($theWidgetName,$theValue);
		} elseif ($theSettingInfo['input']=='boolean') {
			$cellInput .= Widgets::createCheckBox($theWidgetName,$theValue,($theValue==true));
		}
		$cellInput .= '</td>';
		$cellDesc = '<td align="left">'.$theSettingInfo['desc'].'</td>';

		//next statement alternates the row color
		$bcolor = 'class="row'.((++$i%2)+1).'"';
		$w .= '  <tr '.$bcolor.'>'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
	}//end foreach
	$w .= "  </tbody>\n";
    $w .= "</table><br/>\n";
}//end foreach
$w .= '<div align="left">'."<br/>\n";
$w .= $v->save_button;
$w .= '</div>'."<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print $form_html;

$recite->includeMyFooter();
