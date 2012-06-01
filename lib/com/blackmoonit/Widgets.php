<?php
namespace com\blackmoonit;
use com\blackmoonit\Strings;
{//begin namespace

/*
 * Author: Ryan Fischbach
 * Purpose: convenience class for creating html widgets
 */

class Widgets {
	
	private function __construct() {} //do not instantiate

	static public function createHtmlForm($aFormName, $aFormAction, $aDisplayHtml, $redirectLink='', $isPopup=true) {
		$theWidget = "\n";
		if ($isPopup)
			$theWidget .= '<div id="'.$aFormName.'_popup" class="popup">';
		$theWidget .= '<form id="'.$aFormName.'" method="post" '.(($isPopup)?'class="popupBack" ':'').'action="'.$aFormAction.'">'."\n";
		if (!empty($redirectLink))
			$theWidget .= "\t\t".'<input type="hidden" name="redirect" value="'.$redirectLink.'" />'."\n";
		$theWidget .= $aDisplayHtml."\n";
		$theWidget .= '</form>';
		if ($isPopup)
			$theWidget .= '</div>';
		//$theWidget .= '<!-- end '.$aFormName.' -->';
		$theWidget .= "\n";
		return $theWidget;
	}

	static public function createDropDown($aWidgetName, $aItemList, $aKeySelected='') {
		$theWidget = '';
		if (!empty($aItemList) && is_array($aItemList)) {
			$theWidget = '<select name="'.$aWidgetName.'" id="'.$aWidgetName.'" class="post">'."\n";
			//$theWidget .= '<!-- '.$aKeySelected.' -->'."\n";
			foreach ($aItemList as $key => $value) {
				if (empty($aKeySelected) || $aKeySelected!=$key)
					$theWidget .= "\t".'<option value="'.$key.'">'.$value.'</option>';
				else
					$theWidget .= "\t".'<option value="'.$key.'" selected>'.$value.'</option>';
				$theWidget .= "\n";
			}
			$theWidget .= '</select>'."\n";
		}
		return $theWidget;
	}

	static public function createTooltipLink($aHref, $aTooltipMsg, $aDisplayHtml) {
		$theWidget = "<a href=\"$aHref\"";
		$theWidget .= " onMouseover=\"ddrivetip('$aTooltipMsg');\" onMouseout=\"hideddrivetip()\">";
		$theWidget .= $aDisplayHtml.'</a>';
		return $theWidget;
	}
	
	static public function createTextBox($aWidgetName, $aValue, $isRequired=false, $aSize=60, $aMaxLen=255) {
		return Strings::format('<input type="text" name="%1$s" id="%1$s" value="%2$s" size="%4$d" maxlength="%5$d"%3$s />',
				$aWidgetName,$aValue,($isRequired)?' required':'',$aSize,$aMaxLen);
	}
	
	static public function createHiddenPost($aWidgetName, $aValue) {
		return Strings::format('<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />',$aWidgetName,$aValue);
	}
	
	static public function createRadioSet($aWidgetName, $aItemList, $aKeySelected=null, $showLabels='left', $separator='') {
		$theWidget = '<div class="radioset">'."\n";
		if (!empty($aItemList) && is_array($aItemList)) {
			foreach ($aItemList as $key => $value) {
				if ($showLabels=='left')
					$theWidget .= '<label for="'.$aWidgetName.'" class="radiolabel">'.$value.'</label>';
				$theWidget .= "\t".'<input type="radio" name="'.$aWidgetName.'" id="'.$aWidgetName.'" class="radiobutton"';
				$theWidget .= 'value="'.$key.'"';
				if (isset($aKeySelected))
					$theWidget .= (($aKeySelected==$key)?' checked':'');
				$theWidget .= ' />';
				if ($showLabels=='right')
					$theWidget .= '<label for="'.$aWidgetName.'" class="radiolabel">'.$value.'</label>';
				$theWidget .= $separator."\n";
			}
			$theWidget .= "</div>\n";
		}
		return $theWidget;
	}

	static public function createPassBox($aWidgetName, $aSize=60, $aMaxLen=255) {
		return Strings::format('<input type="password" name="%1$s" id="%1$s" size="%2$d" maxlength="%3$d" />',
				$aWidgetName,$aSize,$aMaxLen);
	}
	
	static public function createCheckBox($aWidgetName, $aValue, $isChecked=false, $aTooltipMsg=null) {
		$theWidget = '<input type="checkbox" name="'.$aWidgetName.'" value="'.$aValue.'" ';
		if ($isChecked) 
			$theWidget .= 'checked ';
		if (!empty($aTooltipMsg))
			$theWidget .= "onMouseover=\"ddrivetip('{$aTooltipMsg}');\" onMouseout=\"hideddrivetip()\" ";
		$theWidget .= '/>';
		return $theWidget;
	}
	
	static public function createSubmitButton($aWidgetName, $aText='Submit', $aClass='mainoption') {
		if (empty($aText))
			$aText = 'Submit';
		return Strings::format('<input type="submit" name="%1$s" id="%1$s" class="3$s" value="%2$s"/>',
				$aWidgetName,$aText,$aClass);
	}
	
	static public function createImageTag($aIconFilename, $altText='', $aIconStyle='', $bAltText_asTooltip=false) {
		global $pConfig;
		$theWidget = '<img src="'.BITS_RES.'/images/icons/'.$aIconFilename.'" border="0"';
		if (!empty($altText))
			$theWidget .= " alt=\"{$altText}\"";
		if (!empty($aIconStyle))
			$theWidget .= " style=\"{$aIconStyle}\"";
		if ($bAltText_asTooltip)
			$theWidget .= " onMouseover=\"ddrivetip('{$altText}');\" onMouseout=\"hideddrivetip()\"";
		$theWidget .= " />";
		return $theWidget;
	}

	static public function createPopup($aPopupFormName, $aTooltipMsg, $aDisplayHtml, $aPopupForm) {
		$theWidget = createTooltipLink("javascript:popupForm('$aPopupFormName');",$aTooltipMsg,$aDisplayHtml);
		$theWidget .= $aPopupForm;
		return $theWidget;
	}

}//end class

}//end namespace



/*
function createSignupAddy($aName, $aSignupId, $aTimestamp) {
    return array($aName,"twr{$aSignupId}M{$aTimestamp}@twguild.org");
}

function createLeaderAddy($aName, $aRaidId, $aRaidStartTime) {
    return array($aName,"twl{$aRaidId}M{$aRaidStartTime}@twguild.org");
}

//returns (name, address)
function lookupSignupAddy($aSignupId, $aTimestamp) {
	global $db_raid;
	
	$sql['SELECT'] = "pr.username";
	$sql['FROM'] = array('signups AS s','profile AS pr');
	$sql['WHERE'] = "s.signup_id={$aSignupId} AND s.profile_id=pr.profile_id";
	$theRows = $db_raid->set_query('select', $sql, __FILE__, __LINE__);
	unset($sql);
	$theRow = $db_raid->sql_fetchrow($theRows);
	if ($theRow) {
        return createSignupAddy($theRow[0],$aSignupId,$aTimestamp);
    } else
        return false;
}

//returns (name, address)
function lookupLeaderAddy($aRaidId) {
	global $db_raid;
	
	$sql['SELECT'] = "pr.username, r.start_time";
	$sql['FROM'] = array('raid AS r','profile AS pr');
	$sql['WHERE'] = "r.raid_id={$aRaidId} AND r.profile_id=pr.profile_id";
	$theRows = $db_raid->set_query('select', $sql, __FILE__, __LINE__);
	unset($sql);
	$theRow = $db_raid->sql_fetchrow($theRows);
	if ($theRow) {
        return createLeaderAddy($theRow[0],$aRaidId,$theRow[1]);
    } else
        return false;
}

//return email standard "name <email@ddre.ss>"
function formatEmailAddress($email_addy) {
    if ($email_addy && is_array($email_addy))
        return $email_addy[0].' <'.$email_addy[1].'>';
    else if ($email_addy)
        return $email_addy;
    else
        return;
}

//returns (name, address)
function createMyReplyAddy($aRaidId) {
    global $db_raid, $pMain;
    
	$my_profile_id = $pMain->getProfileID();
	$my_username = $pMain->getUser();
	//first check to see if I am raid leader, if so, use that as my reply address
    $sql['SELECT'] = 'raid_leader, start_time';
    $sql['FROM'] = 'raid';
    $sql['WHERE'] = "raid_id={$aRaidId} AND profile_id = {$my_profile_id}";
    $theRows = $db_raid->set_query('select', $sql, __FILE__, __LINE__);
    unset($sql);
    $theRow = $db_raid->sql_fetchrow($theRows);
    if ($theRow) {
        return createLeaderAddy($my_username,$aRaidId,$theRow['start_time']);
    }
	//if I am not raid leader, then generate my reply address (requires being signed up)
	$sql['SELECT'] = 'signup_id, timestamp';
	$sql['FROM'] = 'signups';
	$sql['WHERE'] = "raid_id={$aRaidId} AND profile_id = {$my_profile_id}";
	$theRows = $db_raid->set_query('select', $sql, __FILE__, __LINE__);
	unset($sql);
	$theRow = $db_raid->sql_fetchrow($theRows);
	if ($theRow) {
		return createSignupAddy($my_username,$theRow['signup_id'],$theRow['timestamp']);
	}
	//return false if I am neither leader, nor signed up
	return false;
}

function createEmailToMemberPopup($aWidgetName, $aRaidId, $emailto) {
    $replyto = createMyReplyAddy($aRaidId);
    if (!$emailto || !$replyto)
        return;
    $form_tooltip = 'Send email to '.$emailto[0];
    $form_text = '&#9993;'; //Unicode id for the envelope character
    $form_action = 'index.php?option=com_view&amp;task=mailto&amp;id='.$aRaidId;
    $theFormWidgets = '<div align="left">';
    $theFormWidgets .= '<p class="name_class">'.$form_tooltip.'</p>';
    $theFormWidgets .= '<input type="text" class="post" name="mailto_subject" style="width:240px;color:black" value="" maxlength=255 /><br />';
    $theFormWidgets .= '<textarea class="post" name="mailto_body" style="height:120px;width:240px;color:black"></textarea><br />';
    $theFormWidgets .= '<input type="hidden" name="replyto" value="'.formatEmailAddress($replyto).'">';
    $theFormWidgets .= '<input type="hidden" name="emailto" value="'.formatEmailAddress($emailto).'">';
    $theFormWidgets .= '<input type="submit" class="mainoption" value="Send Email">';
    $theFormWidgets .= '</div>';
    $form_html = createHtmlForm($aWidgetName,$form_action,$theFormWidgets);
    return createPopup($aWidgetName,$form_tooltip,$form_text,$form_html);
}

function createEmailToListPopup($aListName, $aRaidId) {
    $replyto = createMyReplyAddy($aRaidId);
    if (!$replyto)
        return;
    $form_name = 'mailtolist'.$aListName.$aRaidId;
    $form_tooltip = "Send {$aListName} Email";
    $form_text = '&#9993;'; //Unicode id for the envelope character
    $form_action = 'index.php?option=com_view&amp;task=mailtolist&amp;id='.$aRaidId.'&amp;ln='.$aListName;
    $theFormWidgets = '<div align="left">';
    $theFormWidgets .= '<p class="name_class">'.$form_tooltip.'</p>';
    $theFormWidgets .= '<input type="text" class="post" name="mailto_subject" style="width:240px;color:black" value="" maxlength=255 /><br />';
    $theFormWidgets .= '<textarea class="post" name="mailto_body" style="height:120px;width:240px;color:black"></textarea><br />';
    $theFormWidgets .= '<input type="hidden" name="replyto" value="'.formatEmailAddress($replyto).'">';
    $theFormWidgets .= '<input type="submit" class="mainoption" value="Send Emails">';
    $theFormWidgets .= '</div>';
    $form_html = createHtmlForm($form_name,$form_action,$theFormWidgets);
    return createPopup($form_name,$form_tooltip,$form_text,$form_html);
}
*/

?>
