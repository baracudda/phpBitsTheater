<?php
use BitsTheater\scenes\Account as MyScene;
/* @var $recite MyScene */
/* @var $v MyScene */
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use com\blackmoonit\FinallyBlock;
use Exception;

$h = $v->cueActor('Fragments', 'get', 'csrf_header_jquery');
$recite->includeMyHeader($h);
$w = '';

$theAuthGroupsJS = Strings::phpArray2jsArray($v->auth_groups,'');
$v->jsCode = <<<EOD
$(document).ready(function(){
//var rg = new BitsAuthAccounts('{$v->getSiteURL('account/ajajSaveAccount')}',{$theAuthGroupsJS}).setup();

EOD;
//closer of above ready function done in FinallyBlock

//print($v->cueActor('Fragments', 'get', 'accounts-dialog_account'));

$w .= "<h2>{$v->getRes('account/title_acctlist')}</h2>\n";
$w .= $v->renderMyUserMsgsAsString();

$clsCannotCreate = ($v->isAllowed('account','create')) ? '' : 'invisible';
$labelBtn = $v->getRes('account/label_button_add_account');
$w .= '<button id="btn_add_account" type="button" class="btn btn-primary '.$clsCannotCreate.'">'.$labelBtn.'</button>';
$w .= '<br />'."\n";

$w .= '<div class="panel panel-default">';
//$w .= '<div class="panel-heading">Panel heading</div>';

$w .= '<table class="db-display table">';

$w .= '<thead class="rowh">';
$w .= $v->getColHeaderTextForSortableField('width:4ch', 'view_all', 'account_id');
$w .= $v->getColHeaderTextForSortableField('width:32ch', 'view_all', 'account_name');
//$w .= $v->getColHeaderTextForSortableField('width:4ch', 'view_all', 'external_id');
//$w .= $v->getColHeaderTextForSortableField('width:32ch', 'view_all', 'auth_id');
$w .= $v->getColHeaderTextForSortableField('width:40ch', 'view_all', 'email');
//$w .= $v->getColHeaderTextForSortableField('width:32ch', 'view_all', 'verified_ts');
$w .= $v->getColHeaderTextForSortableField('width:5ch', 'view_all', 'is_active');
$w .= $v->getColHeaderTextForSortableField('width:30ch', 'view_all', 'created_by');
$w .= $v->getColHeaderTextForSortableField('width:32ch', 'view_all', 'created_ts');
$w .= $v->getColHeaderTextForSortableField('width:30ch', 'view_all', 'updated_by');
$w .= $v->getColHeaderTextForSortableField('width:32ch', 'view_all', 'updated_ts');
$w .= "</thead>\n";
print($w);

print("<tbody>\n");
//since we can get exceptions in for loop, let us use a FinallyBlock
$w = "</tbody>\n";
$w .= "</table><br/>\n";
$w .= "</div>\n";
$theFinalEnclosure = new FinallyBlock(function($v, $w) {
	//print anything left in our "what to print" buffer
	print($w);
	//print out our JS code we may have at end of html body
	print($v->createJsTagBlock($v->jsCode.'});'));
	//print out normal footer stuff
	print(str_repeat('<br />',8));
	$v->includeMyFooter();
}, $v, $w);

//results have *not* been fetched yet, so place inside a TRY block
//  just in case we run into a problem
try {
	for ( $theRow = $v->results->fetch(); ($theRow !== false); $theRow = $v->results->fetch() ) {
		$r = '<tr class="'.$v->_rowClass.'">';
		
		/*
		$r .= '<td>';
		{ //edit button
			$r .= '<button type="button" class="btn_edit_group btn btn-default btn-sm"';
			$r .= ' group_id="'.$theGroup['group_id'].'"';
			$r .= ' group_name="'.$theGroup['group_name'].'"';
			//$r .= ' group_desc="'.$theGroup['group_desc'].'"';
			$r .= ' group_parent="'.$theGroup['parent_group_id'].'"';
			if (!empty($v->group_reg_codes[$theGroup['group_id']]))
				$r .= ' group_reg_code="'.$v->group_reg_codes[$theGroup['group_id']]['reg_code'].'"';
			$r .= '><span class="glyphicon glyphicon-pencil"></span></button> ';
		}
		$r .= '</td>';
		*/
		
		$r .= '<td style="align:center">'.$theRow->account_id.'</td>';

		$r .= '<td>'.$theRow->account_name.'</td>';
		
		//$r .= '<td>'.$theRow->external_id.'</td>';
		//$r .= '<td>'.$theRow->auth_id.'</td>';
		
		$r .= '<td>'.$theRow->email.'</td>';
		
		//$r .= '<td>'.$v->getLocalTimestampValue($theRow->verified_ts).'</td>';
		
		$r .= '<td style="align:center">';
		$r .= $theRow->is_active
				? $v->getRes('account/label_is_active_true')
				: $v->getRes('account/label_is_active_false')
		;
		$r .= '</td>';
		
		$r .= '<td>'.$theRow->created_by.'</td>';
		$r .= '<td>'.$v->getLocalTimestampValue($theRow->created_ts).'</td>';
		$r .= '<td>'.$theRow->updated_by.'</td>';
		if ($theRow->created_ts === $theRow->updated_ts) {
			$r .= '<td></td>';
		} else {
			$r .= '<td>'.$v->getLocalTimestampValue($theRow->updated_ts).'</td>';
		}
		$r .= "</tr>\n";
		print($r);
	}//end foreach
} catch (Exception $e) {
	$v->debugLog('account/view_all failed: ' . $e->getMessage() );
	throw $e;
}
//if the data printed out ok, place a button after the table
print($w); //close the table

$w = '<a href="#" class="scrollup">'.$v->getRes('generic/label_jump_to_top').'</a>';

$theFinalEnclosure->updateArgs($v, $w);
//end (FinallyBlock will print out $w and footer)
