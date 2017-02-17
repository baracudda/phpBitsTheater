<?php
use BitsTheater\Scene as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Widgets;

$h = $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$v->includeMyHeader($h);

$v->jsCode = <<<EOD
$(document).ready(function(){
	var thePleaseWaitDlg = $('meta[itemprop=js-dialog_please_wait]').data('bootbox_dialog_argument');
	var theWarningDialog = $('meta[itemprop=admin-js-dialog_update_schema]').data('bootbox_dialog_argument');

	$('button.btn-update-feature').click(function(e) {
		var theID = e.currentTarget.getAttribute('data-feature_id');
		theWarningDialog.buttons.success.callback = function(e) {
			var waitd=bootbox.dialog(thePleaseWaitDlg);
			var theData={feature_id: theID};
			$.post('{$v->getMyUrl('ajajUpdateFeature')}',theData).always(function() {
				waitd.modal('hide');
				location.reload(true);
			});
		}
		bootbox.dialog(theWarningDialog);
	});

	$('#btn_resetup_db').click(function(e) {
		var waitd=bootbox.dialog(thePleaseWaitDlg);
		$.post('{$v->getMyUrl('ajajResetupDb')}',{}).done(function(aData) {
			waitd.modal('hide');
			bootbox.alert("{$v->getRes('admin/msg_missing_tables_created')}");
			//console.log('resetup done');
		}).fail(function(aData, textStatus, errorThrown) {
			waitd.modal('hide');
			//console.log('resetup failed');
		});
	});
});

EOD;

print($v->cueActor('Fragments', 'get', 'js-dialog_please_wait'));
print($v->cueActor('Fragments', 'get', 'admin-js-dialog_update_schema'));
?>
<br />
<div class="panel panel-default">
	<div class="panel-heading">
		<h1 class="panel-title"><?php print($v->getRes('admin/title_websiteStatus')); ?></h1>
	</div>
	<?php print($v->renderMyUserMsgsAsString()); ?>
	<div class="panel-body">
	<table class="table db-display">
		<thead><tr class="rowh">
			<th><?php print($v->getRes('admin/colheader_feature')); ?></th>
			<th><?php print($v->getRes('admin/colheader_curr_version')); ?></th>
			<th><?php print($v->getRes('admin/colheader_new_version')); ?></th>
			<th><?php print($v->getRes('admin/colheader_update')); ?></th>
		</tr></thead>
		<tbody>
<?php
//if the website framework has changed, do not list anything else;
//  it forces the user to always update the website first, which
//  in turn updates this feature list.
$theFeatureList = $v->feature_version_list;
//$v->debugPrint($v->debugStr($theFeatureList));
$theMetaModel = $v->getProp('SetupDb');
$theFrameworkFeatureId = $theMetaModel::FEATURE_ID;
$theWebsiteFeatureId = $v->getRes('website/getFeatureId');
if ($theFeatureList[$theFrameworkFeatureId]['needs_update']) {
	$theFeatureList = array($theFrameworkFeatureId => $theFeatureList[$theFrameworkFeatureId]);
} else if ($theFeatureList[$theWebsiteFeatureId]['needs_update']) {
	$theFeatureList = array($theWebsiteFeatureId => $theFeatureList[$theWebsiteFeatureId]);
}

foreach ($theFeatureList as $theFeatureInfo) {
	$r = '<tr class="'.$v->_rowClass.'">';
	
	$r .= '<td>' . $theFeatureInfo['feature_id'] . '</td>';
	$r .= '<td>' . $theFeatureInfo['version_display'] . '</td>';
	$r .= '<td>' . $theFeatureInfo['version_display_new'] . '</td>';
	$r .= '<td>';
	if ($theFeatureInfo['needs_update']) {
		$r .= Widgets::buildButton()->addClass('btn-danger')->addClass('btn-update-feature')
			->setDataAttr('feature_id', $theFeatureInfo['feature_id'])
			->append($v->getRes('admin/btn_label_update'))
			->render();
	} else {
		$r .= '<label class="text-success">'.$v->getRes('admin/btn_label_uptodate').'</label>';
	}
	$r .= '</td>';

	$r .= "</tr>\n";
	print($r);
}//end foreach
?>
		</tbody>
	</table>
	</div>
</div><span title=".panel" ARIA-HIDDEN></span>
<br>
<?php if (count($theFeatureList)!=1): ?>
<div class="row"><div class="col-md-4"><div class="panel panel-info">
	<div class="panel-heading"><?php
		print($v->getRes('admin/hint_create_missing_tables'));
	?></div>
	<div class="panel-body"><?php
		$w .= Widgets::buildButton('btn_resetup_db')->addClass('btn-warning')
				->append($v->getRes('admin/btn_label_resetup_db'))->render();
		print($w);
	?></div>
</div></div></div>
<?php endif;

print($v->createJsTagBlock($v->jsCode));
print(str_repeat('<br />',8));
$v->includeMyFooter();
