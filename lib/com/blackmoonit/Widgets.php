<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace com\blackmoonit;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use \DateTime;
{//begin namespace

/**
 * Purpose: convenience class for creating html widgets
 * @author: Ryan Fischbach
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
	
	static public function createDropDown($aWidgetName, $aItemList, $aKeySelected=null) {
		$theWidget = '';
		if (!empty($aItemList) && is_array($aItemList)) {
			$theWidget = '<select name="'.$aWidgetName.'" id="'.$aWidgetName.'" class="field">'."\n";
			//$theWidget .= '<!-- '.$aKeySelected.' -->'."\n";
			foreach ($aItemList as $key => $value) {
				if (!isset($aKeySelected) || $aKeySelected!=$key)
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
		$attr_size = (!is_null($aSize)) ? " size=\"$aSize\"" : '';
		$attr_maxlen = (!is_null($aMaxLen)) ? " maxlength=\"$aMaxLen\"" : '';
		return Strings::format('<input type="%2$s" name="%1$s" id="%1$s" value="%3$s"%5$s%6$s%7$s%4$s class="field" />',
				$aWidgetName,$aType,$aValue,($isRequired)?' required':'',$attr_size,$attr_maxlen,$aJsEvents);
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
	
	/**
	 * HTML 5 number input, if you need FLOAT input, be sure to specify STEP or else Chrome will not work right.
	 */
	static public function createNumericBox($aWidgetName, $aValue='', $isRequired=false, $aSize=10, $aStep=null, $aMin=null, $aMax=null, $aJsEvents='') {
		$attr_size = (!is_null($aSize)) ? Strings::format(" size=\"%d\"",$aSize) : '';
		$attr_step = (!is_null($aStep)) ? Strings::format(" step=\"%f\"",$aStep) : '';
		$attr_min = (!is_null($aMin)) ? Strings::format(" min=\"%f\"",$aMin) : '';
		$attr_max = (!is_null($aMax)) ? Strings::format(" max=\"%f\"",$aMax) : '';
		return Strings::format('<input type="number" name="%1$s" id="%1$s" value="%2$s"%4$s%5$s%6$s%7$s%8$s%3$s class="field" />',
				$aWidgetName,$aValue,($isRequired)?' required':'',$attr_size,$attr_step,$attr_min,$attr_max,$aJsEvents);
	}
	
	static public function createTextArea($aWidgetName, $aValue, $isRequired=false, $aRows=3, $aCols=40, $aWrap='soft') {
		return Strings::format('<textarea name="%1$s" id="%1$s" rows="%4$d" cols="%5$d" wrap="%6$s"%3$s class="field" >%2$s</textarea>',
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

	static public function createCheckBox($aWidgetName, $isChecked=false, $aClass='') {
		return '<input type="checkbox" name="'.$aWidgetName.'" class="'.$aClass.'"'.(($isChecked)?' checked':'').' />';
	}
	
	static public function createSubmitButton($aWidgetName, $aText='Submit', $aClass='btn-primary') {
		if (empty($aText))
			$aText = 'Submit';
		return Strings::format('<input type="submit" name="%1$s" id="%1$s" class="%3$s" value="%2$s"/>',
				$aWidgetName,$aText,$aClass);
	}
	
	static public function createImageTag($aIconFilename, $altText='', $aIconStyle='', $bAltText_asTooltip=false) {
		$theWidget = '<img src="'.BITS_RES.'/images/'.$aIconFilename.'" border="0"';
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
	
	/**
	 * Converts a UTC timestamp to Local datetime string for human consumption (uses JavaScript).
	 * @param string $aElemId - variable containing an Element ID, else a UUID is generated and returned in it.
	 * @param number/string $aTime - either a UTC timestamp or a MySQL datetime string.
	 * @return string Returns a JavaScript function call with parameters that will display the timestamp as
	 * a local datetime string based on their computer date format display settings.
	 */
	static public function cnvUtcTs2LocalStr(&$aElemId, $aTime) {
		if (empty($aElemId))
			$aElemId = Strings::createUUID();
		//timestamps need to have @ in front
		$theTime = (is_numeric($aTime)?'@':'').$aTime;
		$theTs = new DateTime($theTime.' UTC'); //Z failed on CentOS v6.5
		return 'zulu_to_local("'.$aElemId.'",'.$theTs->getTimestamp().');';
	}

	/**
	 * Takes a computed diff and translates it into an HTML string.
	 * @param array('values','diff','delimiter') $aComputedDiff - the computed diff
	 * @param string $aDiffSeparator - string used to separate line diffs. (optional, defaults to "")
	 * @return string Returns the HTML string with <ins> and <del> tags where appropriate.
	 * @see Arrays::computeDiff()
	 */
	static public function diffToHtml($aComputedDiff, $aDiffSeparator='') {
		$theValues =& $aComputedDiff['values'];
		$theDiffs =& $aComputedDiff['diff'];
		$theDelimiter = $aComputedDiff['delimiter'];
		$n = count($theValues);
		$pmc = 0;
		$theResult = '';
		for ($i = 0; $i < $n; $i++) {
			$mc = $theDiffs[$i];
			if ($mc != $pmc) {
				switch ($pmc) {
					case -1: $theResult .= '</del>'.$aDiffSeparator; break;
					case  1: $theResult .= '</ins>'.$aDiffSeparator; break;
				}
				if ($i<$n-1) {
					$theResult .= $theDelimiter;
				}
				switch ($mc) {
					case -1: $theResult .= '<del>'; break;
					case  1: $theResult .= '<ins>'; break;
				}
			} else {
				$theResult .= $theDelimiter;
			}
			$theResult .= $theValues[$i];
			$pmc = $mc;
		}
		switch ($pmc) {
			case -1: $theResult .= '</del>'.$aDiffSeparator; break;
			case  1: $theResult .= '</ins>'.$aDiffSeparator; break;
		}
		$theResult = str_replace('<del></del>','',str_replace('<ins></ins>','',$theResult));
		return $theResult;
	}
	
	/**
	 * Compute the Diff between old and new text.
	 * @param string $aTextOld - orig string set
	 * @param string $aTextNew - revised string set
	 * @param string $aDelimiter - (optional) explode the parameters based on this delimiter, defaults to "\n".
	 * @param string $aDiffSeparator - (optional) string used to separate line diffs, defaults to ""
	 * @param boolean $bPreserveHtmlTagsAs1Unit - (optional) if TRUE, the default, treats <tags> as a single comparison unit.
	 * @return string Returns a Diff array set.
	 * @see Arrays::computeDiff()
	 */
	static public function computeDiff($aTextOld, $aTextNew, $aDelimiter="\n", $aDiffSeparator='', $bPreserveHtmlTagsAs1Unit=true) {
		$theDiff = null;
		if (empty($aDelimiter))
			$theDiff = Arrays::computeDiff(str_split($aText1), str_split($aText2));
		else {
			$theExplodeDelimiter = $aDelimiter;
			$theReconstructionDelimiter = $aDelimiter;
			if ($theExplodeDelimiter==' ' && $bPreserveHtmlTagsAs1Unit) {
				if (preg_match_all('~(<[^>]+?\s+[^>]*?>)~', $aTextOld, $theMatches)) {
					foreach ($theMatches[1] as $theNeedle) {
						$aTextOld = str_replace($theNeedle, str_replace(' ',"\e\a",$theNeedle), $aTextOld);
					}
				}
				if (preg_match_all('~(<[^>]+?\s+[^>]*?>)~', $aTextNew, $theMatches)) {
					foreach ($theMatches[1] as $theNeedle) {
						$aTextNew = str_replace($theNeedle, str_replace(' ',"\e\a",$theNeedle), $aTextNew);
					}
				}
				$aTextOld = str_replace("\e\a", ' ', str_replace(' ',"\a",$aTextOld));
				$aTextNew = str_replace("\e\a", ' ', str_replace(' ',"\a",$aTextNew));
				$theExplodeDelimiter = "\a";
			}
			$theDiff = Arrays::computeDiff(explode($theExplodeDelimiter,$aTextOld),
					explode($theExplodeDelimiter,$aTextNew),$theReconstructionDelimiter);
		}
		return $theDiff;
	}
	
	/**
	 * Combine two sets of lines into one "diff lines" text.
	 * @param string $aTextOld - orig string set
	 * @param string $aTextNew - revised string set
	 * @param string $aDelimiter - (optional) explode the parameters based on this delimiter, defaults to "\n".
	 * @param string $aDiffSeparator - (optional) string used to separate line diffs, defaults to ""
	 * @param boolean $bPreserveHtmlTagsAs1Unit - (optional) if TRUE, the default, treats <tags> as a single comparison unit.
	 * @return string Returns a string intersperced with <ins> and <del> tags along with the merged text.
	 */
	static public function diffLines($aTextOld, $aTextNew, $aDelimiter="\n", $aDiffSeparator='', $bPreserveHtmlTagsAs1Unit=true) {
		if (!isset($aTextOld))
			return $aTextNew;
		if (!isset($aTextNew))
			return $aTextOld;
		$theDiff = self::computeDiff($aTextOld,$aTextNew,$aDelimiter,$aDiffSeparator,$bPreserveHtmlTagsAs1Unit);
		if (!empty($theDiff))
			return self::diffToHtml($theDiff, $aDiffSeparator);
		else
			return $aTextNew;
	}


}//end class

}//end namespace
