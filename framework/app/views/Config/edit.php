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

$v->jsCode = <<<EOD
function func_save_settings(r) {
	$("#overlay_please_stand_by").show();
	var fdata = $('form').serialize();
	$.post('{$v->getMyUrl('ajajModifyThenRedirect')}',fdata).done(function(aData) {
		$("#overlay_please_stand_by").hide();
		if (r) location.reload(true);
	}).fail(function(aData, textStatus, errorThrown) {
		$("#overlay_please_stand_by").hide();
	});
}

function exec_action(aAction) {
	func_save_settings(false);
	bootbox.prompt({
	  title: "Re-enter your password to continue",
	  inputType: "password",
	  callback: function(result) {
	  	if (result) {
			$("#overlay_please_stand_by").show();
			var me = '{$v->getDirector()->account_info->account_name}';
			var pw = result;
			$.ajax({
				type: "GET",
				url: aAction,
				headers: {
				    "Authorization": "Basic " + btoa(me+":"+pw)
				},
			}).done(function(aData) {
				$("#overlay_please_stand_by").hide();
			}).fail(function(aData, textStatus, errorThrown) {
				$("#overlay_please_stand_by").hide();
			});
		}
	  }
	});
}

$(document).ready(function(){
	$('form').on( "submit", function( event ) {
		event.preventDefault();
		func_save_settings(true);
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
				func_save_settings(true);
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

$w = '<img class="overlay" id="overlay_please_stand_by" src="'.BITS_RES.'/images/please_stand_by.png" >';
$w .= '<h1>' . $v->getRes('config/title_settings_page') . "</h1>\n";
$w .= $v->renderMyUserMsgsAsString();
print($w);
?>
<div class="row">
<div class="col-sm-6">
<div class="panel panel-info">
	<div class="panel-heading">
		<span style="font-size:larger;"><?php print($v->getRes('generic/label_note_title')); ?></span>
	</div>
	<div class="panel-body"><?php print($v->getRes('config/note_default_setting')); ?></div>
</div>
</div>
<div class="col-sm-6"></div>
</div>
<br />
<br />
<?php
if (!empty($v->config_areas)) {
	$w = '';
	/* @var $theNamespaceInfo ConfigNamespaceInfo */
	foreach ($v->config_areas as $theNamespaceInfo) {
		$v->_rowClass = 1; //reset row counter back to 1 for each table created (resets the row formatting)
		$w .= '<h2>'.$theNamespaceInfo->label.'</h2>';
		$w .= '<table class="db-entry">' . PHP_EOL;
		$w .= '  <thead><tr class="rowh">' . PHP_EOL;
		$w .=     '<th>' . $v->getRes('config/colheader_setting_name') . '</th>';
		$w .=     '<th>' . $v->getRes('config/colheader_setting_value') . '</th>';
		$w .=     '<th>' . $v->getRes('config/colheader_setting_desc') . '</th>' . PHP_EOL;
		$w .= "  </tr></thead>" . PHP_EOL;
		$w .= "  <tbody>" . PHP_EOL;
		/* @var $theSettingInfo ConfigSettingInfo */
		foreach ($theNamespaceInfo->settings_list as $theSettingName => $theSettingInfo) {
			$theWidgetName = $theSettingInfo->getWidgetName();
			$cellLabel = '<td class="db-field-label"><label for="'.$theWidgetName.'" >'
					. htmlentities($theSettingInfo->getLabel()) . '</label></td>';
			$cellInput = '<td class="db-field">';
			$cellInput .= $theSettingInfo->getInputWidget();
			$cellInput .= '</td>';
			$cellDesc = '<td class="">'.htmlentities($theSettingInfo->getDescription()).'</td>';

			$w .= '  <tr class="'.$v->_rowClass.' '.$theNamespaceInfo->namespace.'-'.$theSettingName.'">'
					.$cellLabel.$cellInput.$cellDesc
					."</tr>" . PHP_EOL
					;
		}//end foreach
		$w .= "  </tbody>";
	    $w .= '</table>';
	    $w .= '<br />' . PHP_EOL;
	}//end foreach
	$w .= "<br/>" . PHP_EOL;
	$w .= '<br/>'.Widgets::createSubmitButton('submit_save', $v->save_button_text) . PHP_EOL;
	$w .= "<br/>" . PHP_EOL;
} else {
	$w = '<div id="config_data">' . $v->getRes('config/msg_loading') . '</div>';
	$w .= "<br/>" . PHP_EOL;
}

$theForm = Widgets::buildForm($v->next_action)->setName($v->form_name)
		->setRedirect($v->redirect)->append($w)
		;
print($theForm->render());
print($v->createJsTagBlock($v->jsCode));
print(str_repeat('<br />',3));
$recite->includeMyFooter();
