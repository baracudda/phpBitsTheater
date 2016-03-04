<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
$h = $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$recite->includeMyHeader($h);

$w = '';
$jsCode = <<<EOD
function func_save_settings() {
	$("#overlay_please_stand_by").show();
	var fdata = $('form').serialize();
	$.post('{$v->getMyUrl('ajajModifyThenRedirect')}',fdata).done(function(aData) {
		$("#overlay_please_stand_by").hide();
		location.reload(true);
	}).fail(function(aData, textStatus, errorThrown) {
		$("#overlay_please_stand_by").hide();
	});
}

$(document).ready(function(){
	$('form').on( "submit", function( event ) {
		event.preventDefault();
		func_save_settings();
	});

	$("#overlay_please_stand_by").show();
	$.post('{$v->getMyUrl('ajajGetSettings')}',{}).done(function(aData) {
		aData.results = aData.data;
		delete aData.data;
		delete aData.status;
		delete aData.error;
		var d = JSON.stringify(aData);
		$.post('{$v->getSiteUrl('/fragments/ajajGet/config-formview')}',{post_as_json: d}).done(function(aData) {
			$("#config_data").replaceWith(aData);

			$('.btn-save-settings').click(function(e) {
				func_save_settings();
			});

			$("#overlay_please_stand_by").hide();
		}).fail(function(aData, textStatus, errorThrown) {
			$("#overlay_please_stand_by").hide();
		});
	}).fail(function(aData, textStatus, errorThrown) {
		$("#overlay_please_stand_by").hide();
	});
});

EOD;

$w .= '<img class="overlay" id="overlay_please_stand_by" src="'.BITS_RES.'/images/please_stand_by.png" >';
$w .= "<h1 align=\"center\">Configuration Settings</h1>\n";
if ($msgs = $v->getUserMsgs()) {
	$w .= "<br />\n".$v->renderMyUserMsgsAsString()."<br />\n";
}
$w .= 'Use "?" to reset a setting to its default value ("\\?" to save just a question mark).'."<br />\n";
$w .= "<br />\n";
if (!empty($v->config_areas)) {
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
			$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" >'.$theSettingInfo->getLabel().'</label></td>';
			$cellInput = '<td class="db-field">';
			$cellInput .= $theSettingInfo->getInputWidget();
			$cellInput .= '</td>';
			$cellDesc = '<td class="">'.$theSettingInfo->getDescription().'</td>';

			$w .= '  <tr class="'.$v->_rowClass.' '.$theNamespaceInfo->namespace.'-'.$theSettingName.'">'.$cellLabel.$cellInput.$cellDesc."</tr>\n";
		}//end foreach
		$w .= "  </tbody>\n";
	    $w .= "</table><br/>\n";
	}//end foreach
	$w .= "<br/>\n";
	$w .= '<br/>'.Widgets::createSubmitButton('submit_save', $v->save_button_text)."\n";
} else {
	$w .= '<div id="config_data">Loading...</div>';
}
$w .= "<br/>\n";
$w .= "<br/>\n";

$form_html = Widgets::createHtmlForm($recite->form_name,$recite->next_action,$w,$v->redirect,false);
print($form_html);

print($v->createJsTagBlock($jsCode));
print(str_repeat('<br />',3));
$recite->includeMyFooter();
