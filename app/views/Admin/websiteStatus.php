<?php
use BitsTheater\scenes\Install as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use BitsTheater\models\SetupDb as MetaModel;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
$recite->includeMyHeader();
$w = '<br />';
$jsCode = <<<EOD
var theWarningDialog = {
	title: '{$v->getRes('admin/dialog_update_warning_title')}'
	,
	message: '{$v->getRes('admin/dialog_update_warning_msg')}'
	,
	buttons: {
		cancel: {
		    label: '{$v->getRes('admin/dialog_update_warning_btn_cancel')}'
			,
			className: 'btn-default'
		}
		,
		update: {
			label: '{$v->getRes('admin/dialog_update_warning_btn_update')}'
			,
			className: 'btn-danger'
			,
			callback: function(e) {
				$("#overlay_please_stand_by").show();
				$.post('{$v->getMyUrl('ajaxUpdateFeature')}',{feature_id:theWarningDialog.feature_id}).done(function(aData) {
					location.reload(true);
					$("#overlay_please_stand_by").hide();
					//console.log('posted');
				}).fail(function(aData, textStatus, errorThrown) {
					$("#overlay_please_stand_by").hide();
					location.reload(true);
					//console.log('failed');
				});
			}
		}
	}
}
$('button.btn-update-feature').click(function(e) {
	theWarningDialog.feature_id = $(e.currentTarget).attr('feature_id');
	bootbox.dialog(theWarningDialog);
});

$('#btn_resetup_db').click(function(e) {
	$("#overlay_please_stand_by").show();
	$.post('{$v->getMyUrl('ajaxResetupDb')}',{}).done(function(aData) {
		$("#overlay_please_stand_by").hide();
		alert('Missing tables created.');
		//console.log('resetup done');
	}).fail(function(aData, textStatus, errorThrown) {
		$("#overlay_please_stand_by").hide();
		//console.log('resetup failed');
	});
});

EOD;
$w .= '<img class="overlay" id="overlay_please_stand_by" src="'.BITS_RES.'/images/please_stand_by.png" >';
$w .= '<div class="panel panel-default">';

$w .= '<div class="panel-heading">';
$w .= '<h3 class="panel-title">'.$v->getRes('admin/title_websiteStatus').'</h3>';
$w .= '</div>';

$w .= $v->renderMyUserMsgsAsString();

$w .= '<table class="table db-display">';
$w .= '<thead><tr class="rowh">';
$w .= "<th>{$v->getRes('admin/colheader_feature')}</th>";
$w .= "<th>{$v->getRes('admin/colheader_curr_version')}</th>";
$w .= "<th>{$v->getRes('admin/colheader_new_version')}</th>";
$w .= "<th>{$v->getRes('admin/colheader_update')}</th>";
$w .= '</tr></thead>'."\n";
$w .= "<tbody>\n";

//if the website framework has changed, do not list anything else; forces them to always update the website first, which
//  in turn updates this feature list.
$theFeatureList = $v->feature_version_list;
$theFrameworkFeatureId = MetaModel::FEATURE_ID;
$theWebsiteFeatureId = $v->getRes('website/getFeatureId');
if ($theFeatureList[$theFrameworkFeatureId]['version_display']!==$theFeatureList[$theFrameworkFeatureId]['version_display_new']) {
	$theFeatureList = array($theFrameworkFeatureId => $theFeatureList[$theFrameworkFeatureId]);
} else if (empty($theFeatureList[$theWebsiteFeatureId]) ||
		$theFeatureList[$theWebsiteFeatureId]['version_display']!==$theFeatureList[$theWebsiteFeatureId]['version_display_new']) {
	$theFeatureList = array($theWebsiteFeatureId => array(
			'feature_id' => $theWebsiteFeatureId,
			'version_display' => $v->getRes('admin/field_value_unknown_version'),
			'version_display_new' => $v->getRes('website/version'),
	));
}

foreach ($theFeatureList as $theFeatureInfo) {
	$r = '<tr class="'.$v->_rowClass.'">';
	
	$theFeatureId = $theFeatureInfo['feature_id'];
	
	$r .= '<td>';
	$r .= $theFeatureId;
	$r .= '</td>';
	$r .= '<td>';
	$r .= $theFeatureInfo['version_display'];
	$r .= '</td>';
	$r .= '<td>';
	$r .= $theFeatureInfo['version_display_new'];
	$r .= '</td>';
	$r .= '<td>';
	if ($theFeatureInfo['version_display']!=$theFeatureInfo['version_display_new']) {
		$r .= '<button type="button" class="btn btn-danger btn-update-feature"';
		$r .= ' feature_id="'.$theFeatureId.'"';       //style="color:#ed9c28;"
		$r .= '>'.$v->getRes('admin/btn_label_update').'</button>';
	} else {
		$r .= '<label class="text-success">'.$v->getRes('admin/btn_label_uptodate').'</label>';
	}
	$r .= '</td>';

	$r .= "</tr>\n";
	$w .= $r;
}//end foreach
$w .= "</tbody>\n";
$w .= "</table><br/>\n";

$w .= '</div><span title=".panel" HIDDEN></span>'."\n";

$w .= '<br>';
$w .= 'Once all features are up-to-date, you may want to ensure all tables exist. ';
$w .= 'This is usually important if restoring from a partial backup or imported data. ';
$w .= '<br>';
$w .= '<button type="button" id="btn_resetup_db" class="btn btn-warning">'.$v->getRes('admin/btn_label_resetup_db').'</button>';

print($w);

print($v->createJsTagBlock($jsCode));
print(str_repeat('<br />',8));
$recite->includeMyFooter();
