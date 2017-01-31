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
use DateTime;
{//begin namespace

/**
 * Purpose: convenience class for creating html widgets
 * @author: baracudda
 */
class Widgets
{
	const ALIGN_LEFT = 'left' ;
	const ALIGN_RIGHT = 'right' ;
	const ALIGN_CENTER = 'center' ;
	
	/** INVALID_ID = '__invalid:ID__' */
	const INVALID_ID = '__invalid:ID__';
	/** INVALID_ATTR_NAME = '__invalid-name__' */
	const INVALID_ATTR_NAME = '__invalid-name__';
	
	private function __construct() {} //do not instantiate
	
	/**
	 * HTML "id" attributes have a specified format which this method
	 * enforces. It is fairly typical variable naming convention with
	 * some extra punctuation involved. A-z, 0-9, "-", "_", ":", and "."
	 * @param string $aElementID - the string to test.
	 * @return boolean Returns TRUE if the parameter is a valid ID.
	 */
	static public function isValidHtmlID( $aElementID )
	{
		return preg_match('/^[a-zA-Z][\w.:-]*$/', $aElementID);
		//note: ID must begin with a letter
		//note: \w is similar to [a-zA-Z\d_]
	}

	/**
	 * HTML "id" attributes have a specified format which this method
	 * enforces. If it could not come up with anything valid then
	 * {@link Widgets::INVALID_ID} will be used instead.
	 * @param string $aElementID - the string to sanitize.
	 * @return string Returns the sanitized string.
	 */
	static public function sanitizeElementID( $aElementID )
	{
		if( !isset($aElementID) ) return '' ;
		if( self::isValidHtmlID($aElementID) ) return $aElementID ;
		//if param is not a valid ID, attempt to sanitize it
		$s = preg_replace( '/[^\w.:-]/', '', $aElementID ) ;
		return ( self::isValidHtmlID($s) ) ? $s : self::INVALID_ID;
	}
	
	/**
	 * HTML attribute names have a specified format which this method
	 * enforces. It is fairly broadly defined with a few notalbe
	 * exceptions like the lack of "@", and HTML punctuation.
	 * @param string $aAttrName - the string to test.
	 * @return boolean Returns TRUE if the parameter is valid.
	 */
	static public function isValidHtmlAttrName( $aAttrName )
	{
		//note: PHP translates the "." into "_" on POST, so
		//  we will consider "." invalid since we're using PHP
		$basicTest = preg_match('/^[a-zA-Z_:][\w:-]*$/', $aAttrName);
		if ($basicTest) return true;
		//if the first match fails, check the entire range
		$theStartChar = '[a-zA-Z_:]'
				. ' | [\xC0-\xD6]'
				. ' | [\xD8-\xF6]'
				. ' | [\x{00F8}-\x{02FF}]'
				. ' | [\x{0370}-\x{037D}]'
				. ' | [\x{037F}-\x{1FFF}]'
				. ' | [\x{200C}-\x{200D}]' //ZERO WIDTH NON}-JOINER, ZERO WIDTH JOINER
				. ' | [\x{2070}-\x{218F}]'
				. ' | [\x{2C00}-\x{2FEF}]'
				. ' | [\x{3001}-\x{D7FF}]'
				. ' | [\x{F900}-\x{FDCF}]'
				. ' | [\x{FDF0}-\x{FFFD}]'
				. ' | [\x{10000}-\x{EFFFF}]'
				;
		$thePattern = '/^(?:'.$theStartChar.')(?:'.$theStartChar
				. ' | [0-9-]'
				. ' | [\xB7]' //Middle dot "·" - Georgian comma
				. ' | [\x{0300}-\x{036F}]' //Combining Diacritical Marks
				. ' | [\x{203F}-\x{2040}]' //‿ ⁀
				. ')*$/u';
		return preg_match($thePattern, $aAttrName);
	}
	
	/**
	 * HTML attribute names have a specified format which this method
	 * enforces. If it could not come up with anything valid then
	 * "__invalid-name__" will be used instead.
	 * @param string $aAttrName - the string to sanitize.
	 * @return string Returns the sanitized string.
	 */
	static public function sanitizeAttributeName( $aAttrName )
	{
		if( !isset($aAttrName) ) return self::INVALID_ATTR_NAME ;
		if( self::isValidHtmlAttrName($aAttrName) ) return $aAttrName ;
		//if param is not valid, attempt to sanitize it
		//note: PHP translates the "." into "_" on POST
		$s = str_replace( '.', '_', $aAttrName );
		$s = preg_replace( '/[^\w\-:]/', '', $s ) ;
		return ( self::isValidHtmlAttrName($s) ) ? $s : self::INVALID_ATTR_NAME ;
	}
	
	/** @deprecated Please use {@link Widgets::buildForm()} */
	static public function createHtmlForm( $aFormName, $aFormAction,
			$aDisplayHtml, $redirectLink='', $isPopup=false )
	{
		$theWidget = "\n" ;
		if ($isPopup)
			$theWidget .= '<div class="popup">' ;
		$theWidget .= '<form'
			. ( !empty($aFormName) ?
					' id="' . self::sanitizeElementID($aFormName) . '"' : '' )
			. ' method="post" '
			. ( $isPopup ? 'class="popup-back" ' : '' )
			. 'action="' . $aFormAction . '">'
			. PHP_EOL
			;
		if( !empty($redirectLink) )
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
	
	static public function createDropDown( $aWidgetName, $aItemList,
			$aValueSelected=null, $aIndent=0 )
	{
		$theWidget = '' ;
		if( !empty($aItemList) && is_array($aItemList) )
		{
			$theWidget = Strings::spaces($aIndent)
				. '<select name="' . $aWidgetName
				. '" id="' . self::sanitizeElementID($aWidgetName)
				. '" class="field">'
				. PHP_EOL
				;
			foreach( $aItemList as $aValue => $aCaption )
			{
				$theWidget .= Strings::spaces($aIndent+1)
					. '<option value="' . $aValue . '"'
					. ( $aValueSelected === $aValue ? ' selected>' : '>' )
					. $aCaption . '</option>'
					. PHP_EOL
					;
			}
			$theWidget .= Strings::spaces($aIndent) . '</select>' . PHP_EOL ;
		}
		return $theWidget;
	}
	
	/** Used by createMultiSelect() */
	const MULTI_SELECTOR_RENDERING_ERROR =
		'<!-- ERROR - Can\'t construct multi-selector widget. -->' ;
	/**
	 * Creates an HTML <select> element and its <option> elements, with
	 * attributes defined to make it a multi-selector control.
	 * @param string $aWidgetName the name of the widget, and its ID; a name
	 *  ending in '[]' is expected but will be sanitized before being used as
	 *  the ID
	 * @param array $aItemList an array of options; this should be an array in
	 *  which the key is the <option> element's actual value, and the value is
	 *  the caption to be rendered;
	 * @param number $aSize the number of rows to render in the selection box
	 * @param array $aValuesSelected an array of values which should be
	 *  selected by default
	 * @param string $aStyleClass the CSS class to which the element belongs
	 * @param number $aIndent an indent to be applied in the HTML code
	 * @return string HTML code for a <select> element and its list of <option>s
	 */
	static public function createMultiSelect( $aWidgetName, $aItemList,
		$aIndent=0, $aSize=4, $aValuesSelected=null, $aStyleClass='field' )
	{
		if( !isset($aItemList) || !isset($aWidgetName) )
			return self::MULTI_SELECTOR_RENDERING_ERROR ;
		$theSelector = Strings::spaces($aIndent)
			. '<select id="' . self::sanitizeElementID($aWidgetName)
			. '" name="' . $aWidgetName
			. '" class="' . $aStyleClass
			. '" size="' . $aSize
			. '" multiple="multiple">'
			. PHP_EOL
			;
		$theValuesSelected =
			( is_array($aValuesSelected) ? $aValuesSelected : null ) ;
		if( is_array($aItemList) )
		{
			foreach( $aItemList as $theValue => $theCaption )
			{
				$theSelector .= Strings::spaces($aIndent+1)
					. '<option '
					. 'value="' . $theValue
					. '"'
					;
				if( !empty($theValuesSelected)
					&& in_array( $theValue, $theValuesSelected ) )
				{
					$theSelector .= ' selected' ;
				}
				$theSelector .= '>' . $theCaption . '</option>' . PHP_EOL ;
			}
		}
		else if( isset($aItemList) )
		{ // Some weirdo passed a simple value into this function.
			$theSelector .= Strings::spaces($aIndent+1)
				. '<option value="' . $aItemList . '">'
				. $aItemList . '</option>' . PHP_EOL
				;
		}
		$theSelector .= Strings::spaces($aIndent) . '</select>' . PHP_EOL ;
		return $theSelector ;
	}
	
	/**
	 * Prepares an array of data items for use in createDropDown() or
	 * createMultiSelect().
	 * @param array $aArrayOfOptions an array of data items that define the
	 *  options to be set.
	 * @param string $aValueField the name of a field within each member of
	 *  $aArrayOfOptions; the value of this field will be used as the "value"
	 *  of the option element
	 * @param string $aCaptionField the name of a field within each member of
	 *  $aArrayOfOptions; the value of this field will be used as the "caption"
	 *  of the option element
	 * @return array an array in which the key of each member is the option's
	 *  value, and the value of the member is the option's caption
	 */
	static public function prepareArrayForSelect( $aArrayOfOptions,
		$aValueField=null, $aCaptionField=null )
	{
		if( ! is_array($aArrayOfOptions) ) return null ;
		
		$theOptions = array() ;
		foreach( $aArrayOfOptions as $aKey => $aOption )
		{ // Create an option element in our array with standard fields.
			$theCaption = $aOption ;
			$theValue = $aKey ;
			if( is_array($aOption) )
			{ // Find the caption and value within the input.
				if( !empty($aValueField)
					&& array_key_exists( $aValueField, $aOption ) )
				{ // Extract the value.
					$theValue = $aOption[$aValueField] ;
				}
				if( !empty($aCaptionField)
					&& array_key_exists( $aCaptionField, $aOption ) )
				{ // Use the caption.
					$theCaption = $aOption[$aCaptionField] ;
				}
				else // the value is the caption.
					$theCaption = $theValue ;
			}
			else
			{ // Use the simple value to create the option.
				$theValue = $aKey ;
				$theCaption = $aOption ;
			}
			$theOptions[$theValue] = $theCaption ;
		}
		return $theOptions ;
	}

	static public function createTooltipLink($aHref, $aTooltipMsg, $aDisplayHtml) {
		$theWidget = "<a href=\"$aHref\"";
		$theWidget .= " onMouseover=\"ddrivetip('$aTooltipMsg');\" onMouseout=\"hideddrivetip()\">";
		$theWidget .= $aDisplayHtml.'</a>';
		return $theWidget;
	}
	
	/** Used by createFileUploadControl() */
	const FILE_WIDGET_RENDERING_ERROR =
		'<!-- ERROR - Can\'t construct file upload widget. -->' ;
	/**
	 * Renders a file upload &lt;input&gt; control.
	 * @param string $aWidgetName the name of the widget within its HTML form
	 * @param array $aAttrs additional attributes for the &lt;input&gt; tag not
	 *  covered by other arguments; defaults to null
	 * @param array $aAcceptArray an array of file types to accept; must be
	 *  valid for a "file-accept" attribute spec
	 * @param number $aMinSize minimum file size in bytes; default 0; must be
	 *  valid for a "file-minsize" attribute spec
	 * @param number $aMaxSize maximum file size in bytes; defaults to the max
	 *  size allowed by PHP on this server; must be valid for a "file-maxsize"
	 *  attribute spec
	 * @param string $isRequired indicates whether the input control's value is
	 *  required by the form
	 * @param number $aLimit maximum number of files allowed; default 1; must be
	 *  numeric and valid for "file-limit" attribute spec
	 * @return string a fully-formed &lt;input&gt; tag or FILE_WIDGET_RENDERING_ERROR
	 *  enclosed in an HTML comment tag
	 */
	static public function createFileUploadControl( $aWidgetName, $aAttrs=null,
		$aAcceptArray, $aMinSize=0, $aMaxSize=-1, $isRequired=false, $aLimit=1 )
	{
		if( !isset($aWidgetName) ) return self::FILE_WIDGET_RENDERING_ERROR ;
		$theTag = '<input type="file" name="' . $aWidgetName . '" id="'
				. self::sanitizeElementID($aWidgetName) . '" ' ;
		if( $isRequired )
			$theTag .= 'required ' ;
		if( isset($aAcceptArray) && is_array($aAcceptArray) )
		{
			$theTypes = null ;
			$isFirst = true ;
			foreach( $aAcceptArray as $theType )
			{
				if( ! $isFirst ) $theTypes .= ', ' ;
				$theTypes .= $theType ;
				$isFirst = false ;
			}
			$theTag .= 'file-accept="' . $theTypes . '" ' ;
		}
		$theMinSize = Strings::semanticSizeToBytes( $aMinSize ) ;
		if( $theMinSize >= 0 )
			$theTag .= 'file-minsize="' . $theMinSize . '" ' ;
		$theMaxSize = Strings::semanticSizeToBytes( $aMaxSize ) ;
		if( $theMaxSize < 0 )
		{ // Can't use that size; try the PHP server maximum instead.
			$theMaxSize = Strings::semanticSizeToBytes(
				min(ini_get('post_max_size'),ini_get('upload_max_filesize'))) ;
		}
		if( $theMaxSize > 0 )
			$theTag .= 'file-maxsize="' . $theMaxSize . '" ' ;
		if( is_numeric($aLimit) && $aLimit > 0 )
			$theTag .= 'file-limit="' . $aLimit . '" ' ;
		if( isset($aAttrs) && is_array($aAttrs) )
		{
			foreach( $aAttrs as $theAttr => $theValue )
				$theTag .= $theAttr . '="' . $theValue . '" ' ;
		}
		$theTag .= ' />' ;
		return $theTag ;
	}
	
	/**
	 * @deprecated Please use {@link Widgets::buildInputBox()}.
	 */
	static public function createInputBox($aWidgetName, $aType='text', $aValue='',
			$isRequired=false, $aSize=60, $aMaxLen=255, $aJsEvents='')
	{
		return (new widgetbuilder\InputWidget($aWidgetName))->setInputType($aType)
				->setAttr('name', $aWidgetName)->setValue($aValue)->setRequired($isRequired)
				->setSize($aSize)->setAttr('maxlength', $aMaxLen)
				->render();
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
	
	/**
	 * @deprecated Please use {@link Widgets::buildTextArea()}
	 */
	static public function createTextArea($aWidgetName, $aValue, $isRequired=false,
			$aRows=3, $aCols=40, $aWrap='soft')
	{
		return self::buildTextArea($aWidgetName)->setRows($aRows)->setCols($aCols)
				->setWrap($aWrap)->setRequired($isRequired)->append($aValue)->render();
	}
	
	/**
	 * Create HTML for a hidden input.
	 * @param string $aWidgetName - the name and ID of the input.
	 * @param string $aValue - the value for the input.
	 * @return string Returns the HTML string.
	 */
	static public function createHiddenPost($aWidgetName, $aValue)
	{ return self::buildHiddenInput($aWidgetName, $aValue)->render(); }
	
	/**
	 * Renders a set of matched radio buttons.
	 * @param string $aWidgetName the name/ID of the radio button group
	 * @param array $aItemList an array of value-to-caption pairs
	 * @param string $aKeySelected which value should be pre-selected, if any
	 * @param string $showLabels indicates where to show labels (left or right)
	 * @param string $separator a separator to insert between buttons
	 * @return string a fully-formed set of radio button elements in HTML
	 */
	static public function createRadioSet( $aWidgetName, $aItemList,
			$aKeySelected=null, $showLabels=self::ALIGN_LEFT, $separator='' )
	{
		$theWidget = '<div class="radioset">' . PHP_EOL ;
		if( !empty($aItemList) && is_array($aItemList) )
		{
			foreach( $aItemList as $aValue => $aLabel )
			{
				$theWidget .= self::createOneRadioButton(
							$aWidgetName, $aValue, $aLabel,
							( isset($aKeySelected) && $aValue==$aKeySelected ),
							$showLabels ) ;
				$theWidget .= $separator . PHP_EOL ;
			}
			$theWidget .= '</div>' . PHP_EOL ;
		}
		return $theWidget;
	}
	
	/**
	 * Renders a single radio button and its label. This is consumed by the
	 * createRadioSet() function but may also be called separately to create
	 * radio buttons that are not immediately next to each other.
	 * @param string $aWidgetName the name of the radio button group
	 * @param string $aValue the value assigned to this particular button
	 * @param string $aLabel the label to be shown for this button
	 * @param string $isSelected whether this button should be pre-selected
	 * @param string $showLabel indicates where to show the label (left/right)
	 * @param string $aJavaScript any JS that should be included
	 * @return string a fully-formed HTML radio button with its label
	 */
	static public function createOneRadioButton( $aWidgetName, $aValue,
			$aLabel=null, $isSelected=false, $showLabel=self::ALIGN_LEFT,
			$aJavaScript=null )
	{
		$theHTML = '' ;
		$theLabel = ( empty($aLabel) ? $aValue : $aLabel ) ;
		if( $showLabel == self::ALIGN_LEFT )
			$theHTML .=
				self::createRadioButtonLabel($aWidgetName,$aValue,$theLabel) ;
		$theHTML .= self::createRadioButtonTag( $aWidgetName, $aValue,
						$isSelected, $aJavaScript ) ;
		if( $showLabel == self::ALIGN_RIGHT )
			$theHTML .=
				self::createRadioButtonLabel($aWidgetName,$aValue,$theLabel) ;
		return $theHTML ;
	}
	
	/**
	 * Used by createOneRadioButton() to render the button's label
	 * @param string $aWidgetName the name/ID of the button group
	 * @param string $aLabel the label to be rendered
	 * @return string an HTML label element
	 */
	static private function createRadioButtonLabel( $aWidgetName, $aValue,
			$aLabel=null )
	{
		return '<label for="' . $aWidgetName . '_' . $aValue
				. '" class="radiolabel">'
				. ( isset($aLabel) ? $aLabel : $aValue )
				. '</label>'
				;
	}
	
	/**
	 * Used by createOneRadioButton() to render the radio button itself.
	 * @param string $aWidgetName the name/ID of the button group
	 * @param string $aValue the actual value assigned to this button
	 * @param string $isSelected indicates whether the button is pre-selected
	 * @param string $aJavaScript any JS that should be included
	 * @return string an HTML radio button input element
	 */
	static private function createRadioButtonTag( $aWidgetName, $aValue,
			$isSelected=false, $aJavaScript=null )
	{
		return '<input type="radio" name="' . $aWidgetName
			. '" id="' . self::sanitizeElementID($aWidgetName) . '_' . $aValue
			. '" class="radiobutton" value="' . $aValue . '" '
			. ( $isSelected ? 'checked ' : '' )
			. ( !empty($aJavaScript) ? $aJavaScript : '' )
			. '/>'
			;
	}

	/**
	 * Checkboxes are strange animals in HTML forms. If they are unchecked,
	 * their value is NOT submitted to the action's endpoint. To combat this
	 * lack of submission quirk, a hidden input with a value of "0" is also
	 * placed just before the checkbox so that something is always submitted.
	 * PHP always takes the last defined input name as the actual value,
	 * which is why this particular technique works out pretty well.  The
	 * value "0" was chosen so that code checking for the checkbox can just
	 * check for "!empty($aWidgetName)".
	 * @param string $aWidgetName - the widget name.
	 * @param boolean $isChecked - (optional) should the input be pre-checked
	 * (default=FALSE).
	 * @param string $aClass - (optional) classes that should be defined with
	 * the widget.
	 * @return string Returns an HTML checkbox element.
	 */
	static public function createCheckBox($aWidgetName, $isChecked=false, $aClass=''){
		$theClass = (!empty($aClass)) ? ' class="'.$aClass.'"' : '';
		$theCheckbox = '<input type="checkbox" name="'.$aWidgetName.'"'.$theClass.(($isChecked)?' checked':'').' />';
		return self::createHiddenPost($aWidgetName, '0').$theCheckbox;
	}

	/**
	 * Create a standard HTML input submit button.
	 * @param string $aWidgetName - the name and ID of the widget.
	 * @param string $aText - (optional) the button label, defaults to "Submit".
	 * @param string $aClass - (optional) the widget class(es), defaults to "btn-primary".
	 * @return string Return the HTML string.
	 */
	static public function createSubmitButton($aWidgetName, $aText='Submit', $aClass='btn-primary') {
		if (empty($aText))
			$aText = 'Submit';
		return self::buildSubmitInput($aWidgetName, $aText)->addClass('btn-primary')->render();
	}
	
	static public function createResetButton( $aWidgetName='bits_button_reset',
		$aText='Reset', $aClass='btn-primary', $aAttrs=null )
	{
		$theButton = '<button type="reset" id="' . self::sanitizeElementID($aWidgetName)
			. '" name="' . $aWidgetName . '" class="' . $aClass . '" '
			;
		if( is_array($aAttrs) )
		{
			foreach( $aAttrs as $theAttr => $theValue )
				$theButton .= $theAttr . '="' . $theValue . '" ' ;
		}
		else if( isset($aAttrs) )
			$theButton .= $aAttrs . ' ' ;
		$theButton .= '>' . $aText . '</button>' ;
		return $theButton ;
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
	
	/**
	 * Factory convenience method for creating a text input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildInputBox($aName)
	{ return widgetbuilder\InputWidget::asText($aName); }

	/**
	 * Factory convenience method for creating a text input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildTextBox($aName)
	{ return widgetbuilder\InputWidget::asText($aName); }

	/**
	 * Factory convenience method for creating an email input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildEmailBox($aName)
	{ return widgetbuilder\InputWidget::asEmail($aName); }
	
	/**
	 * Factory convenience method for creating a password input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildPassBox($aName)
	{ return widgetbuilder\InputWidget::asPassword($aName); }
	
	/**
	 * Factory convenience method for creating a hidden input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildHiddenInput($aName, $aValue)
	{ return widgetbuilder\InputWidget::asHidden($aName, $aValue); }
	
	/**
	 * Factory convenience method for creating a honeypot input. Used to create
	 * a non-visible input element to trap spam bots into filling it out.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 */
	static public function buildHoneyPotInput($aName)
	{ return widgetbuilder\InputWidget::asHoneyPot($aName); }
	
	/**
	 * Factory convenience method for creating an older-style submit button.
	 * @param string $aName - the Name and default ID of the element.
	 * @param string $aLabel - the label the button will display.
	 * @return widgetbuilder\InputWidget Returns the new object for chaining.
	 * @deprecated Please use {@link Widgets::buildSubmitButton()}
	 */
	static public function buildSubmitInput($aName, $aLabel)
	{ return widgetbuilder\InputWidget::asSubmitButton($aName, $aLabel); }
	
	/**
	 * Factory convenience method for creating a submit button.
	 * @param string $aID - the ID of the element.
	 * @return widgetbuilder\ButtonWidget Returns the new object for chaining.
	 */
	static public function buildButton($aID=null)
	{ return new widgetbuilder\ButtonWidget($aID); }
	
	/**
	 * Factory convenience method for creating a submit button.
	 * @param string $aID - the ID of the element.
	 * @param string $aLabel - the label the button will display.
	 * @return widgetbuilder\ButtonWidget Returns the new object for chaining.
	 */
	static public function buildSubmitButton($aID, $aLabel)
	{ return self::buildButton($aID)->setButtonType('submit')->append($aLabel); }
	
	/**
	 * Factory convenience method for creating a text area widget.
	 * @param string $aName - the Name and default ID of the element.
	 * @return widgetbuilder\TextAreaWidget Returns the new object for chaining.
	 */
	static public function buildTextArea($aName)
	{ return new widgetbuilder\TextAreaWidget($aName); }
	
	/**
	 * Factory convenience method for creating a form object.
	 * @param string $aFormAction - the action to perform on submit.
	 * @return widgetbuilder\FormWidget Returns the new object for chaining.
	 */
	static public function buildForm($aFormAction)
	{
		return (new widgetbuilder\FormWidget())->setAction($aFormAction)
				//default enctype attribute is URL_ENCODED
				->setEncoding( widgetbuilder\FormWidget::URL_ENCODED )
				//framework prefers POST as default method (more secure)
				->setMethod( widgetbuilder\FormWidget::METHOD_POST )
				;
	}
	
}//end class

}//end namespace
