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

	static public function createHtmlForm($aFormName, $aFormAction, $aDisplayHtml, $redirectLink='', $isPopup=false) {
		$theWidget = "\n";
		if ($isPopup)
			$theWidget .= '<div class="popup">';
		$theWidget .= '<form'.(!empty($aFormName)?' id="'.$aFormName.'"':'').' method="post" '.(($isPopup)?'class="popup-back" ':'').'action="'.$aFormAction.'">'."\n";
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
	
	static public function createForm($aFormAction, $aDisplayHtml, $redirectLink='', $isPopup=false, $aFormName='') {
		return Widgets::createHtmlForm($aFormName, $aFormAction, $aDisplayHtml, $redirectLink, $isPopup); 
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
	
	static public function createInputBox($aWidgetName, $aType='text', $aValue='', $isRequired=false, $aSize=60, $aMaxLen=255, $aJsEvents='') {
		return Strings::format('<input type="%2$s" name="%1$s" id="%1$s" value="%3$s" size="%5$d" maxlength="%6$d" %7$s%4$s />',
				$aWidgetName,$aType,$aValue,($isRequired)?' required':'',$aSize,$aMaxLen,$aJsEvents);
	}
	
	static public function createPassBox($aWidgetName, $aValue='', $isRequired=false, $aSize=60, $aMaxLen=255, $aJsEvents='') {
		return Widgets::createInputBox($aWidgetName,'password',$aValue,$isRequired,$aSize,$aMaxLen,$aJsEvents);
	}
	
	static public function createEmailBox($aWidgetName, $aValue='', $isRequired=false, $aSize=60, $aMaxLen=255, $aJsEvents='') {
		return Widgets::createInputBox($aWidgetName,'email',$aValue,$isRequired,$aSize,$aMaxLen,$aJsEvents);
	}
	
	static public function createTextBox($aWidgetName, $aValue='', $isRequired=false, $aSize=60, $aMaxLen=255, $aJsEvents='') {
		return Widgets::createInputBox($aWidgetName,'text',$aValue,$isRequired,$aSize,$aMaxLen,$aJsEvents);
	}
	
	static public function createTextArea($aWidgetName, $aValue, $isRequired=false, $aRows=3, $aCols=40, $aWrap='physical') {
		return Strings::format('<textarea name="%1$s" id="%1$s" value="%2$s" rows="%4$d" cols="%5$d" wrap="%6$s"%3$s />',
				$aWidgetName,$aValue,($isRequired)?' required':'',$aRows,$aCols,$aWrap);
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

	static public function createCheckBox($aWidgetName, $isChecked=false) {
		return '<input type="checkbox" name="'.$aWidgetName.'"'.(($isChecked)?' checked':'').' />';
	}
	
	static public function createSubmitButton($aWidgetName, $aText='Submit', $aClass='mainoption') {
		if (empty($aText))
			$aText = 'Submit';
		return Strings::format('<input type="submit" name="%1$s" id="%1$s" class="%3$s" value="%2$s"/>',
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
