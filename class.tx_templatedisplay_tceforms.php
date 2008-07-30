<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Francois Suter (Cobweb) <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
*
* $Id: class.tx_datadisplay_pi1.php 3938 2008-06-04 08:39:01Z fsuter $
***************************************************************/

require_once(t3lib_extMgm::extPath('basecontroller', 'class.tx_basecontroller_div.php'));

/**
 * TCEform custom field for template mapping
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay_tceforms {
	protected $extKey = 'templatedisplay';

	/**
	 * This method renders the user-defined mapping field,
     * i.e. the screen where data is mapped to the template markers
     *
     * @param	array			$PA: information related to the field
     * @param	t3lib_tceform	$fobj: reference to calling TCEforms object
	 *
	 * @return	string	The HTML for the form field
	 */
	public function mappingField($PA, $fobj) {
		$formField = '';
//		$formField .= t3lib_div::view_array($PA);
//		$formField .= t3lib_div::view_array($GLOBALS['TYPO3_CONF_VARS']);

		try {
			// Get the related (primary) provider
			$provider = $this->getRelatedProvider($PA['row']);
			try {
				$fieldsArray = $provider->getTablesAndFields();
				$formField .= '<input type="hidden" name="'.$PA['itemFormElName'].'" value="Hello" />';

				$row = $PA['row'];
				// true when the user has defined a template.
				if($row['template'] != ''){
					# Retrieve the template string and init the path
					$temporaryArray = explode('|', $row['template']);
					$row['template'] = $temporaryArray[0];
					$templateFile = t3lib_div::getFileAbsFileName('uploads/tx_templatedisplay/' . $row['template']);
					$templateContent = htmlspecialchars(file_get_contents($templateFile));

					$pattern = '/(#{3}FIELD[0-9a-zA-Z\.]+#{3})/m';
					$path = t3lib_extMgm::extRelPath('templatedisplay').'resources/images/';
					$replacement = '<a href="#" onclick="return false">$1</a><img src="'.$path.'exclamation.png" alt="" style="position: relative; bottom: 10px; right: 20px"/>';
					$templateContent = preg_replace($pattern, $replacement, $templateContent);

					# Initialize the select drop down which contains the fields
					# Simulation. This is a typical array received by a consumer
/*
					$fieldsArray['pages']['label'] = 'Page';
					$fieldsArray['pages']['fields']= array( 'title' => 'titre');
					$fieldsArray['tt_content']['label'] = 'Page content';
					$fieldsArray['tt_content']['fields']= array( 'uid' => 'uid', 'header' => 'en tête');
*/

					$options = '';
					foreach($fieldsArray as $keyTable => $fields){
						$options .= '<optgroup label="'. $keyTable .'" class="c-divider">';
						foreach($fields['fields'] as $keyField => $field){
							$options .= '<option value="'.$keyTable.'.'.$keyField.'">'.$keyField.'</option>';
						}
						$options .= '</optgroup>';
					}
					$marker['###AVAILABLE_FIELDS###'] = $options;

					# Initialize some template variable
					$marker['###TEMPLATE_CONTENT###'] = $templateContent;
					$marker['###INFOMODULE_PATH###'] = $path;
					$marker['###SELECT_ONE###'] = $this->getLL('tx_templatedisplay_displays.select_one');
					$marker['###TEXT###'] = $this->getLL('tx_templatedisplay_displays.text');
					$marker['###IMAGE###'] = $this->getLL('tx_templatedisplay_displays.image');
					$marker['###SHOW_XML###'] = $this->getLL('tx_templatedisplay_displays.show_xml');
					$marker['###EDIT_XML###'] = $this->getLL('tx_templatedisplay_displays.edit_xml');
					$marker['###TYPES###'] = $this->getLL('tx_templatedisplay_displays.types');
					$marker['###FIELDS###'] = $this->getLL('tx_templatedisplay_displays.fields');
					$marker['###CONFIGURATION###'] = $this->getLL('tx_templatedisplay_displays.configuration');
					$marker['###INSERT_DEFAULT_CONFIGURATION###'] = $this->getLL('tx_templatedisplay_displays.insertDefaultConfiguration');

					# Parse the template and render it.
					$backendTemplatefile = t3lib_div::getFileAbsFileName('EXT:templatedisplay/resources/templates/templatedisplay.html');
					$formField .= t3lib_parsehtml::substituteMarkerArray(file_get_contents($backendTemplatefile), $marker);
				}
				else{
					$file = t3lib_div::getFileAbsFileName('EXT:templatedisplay/resources/templates/templateMissing.html');

					$marker['###noTemplateFoundError1###'] = $this->getLL('tx_templatedisplay_displays.noTemplateFoundError1');
					$marker['###noTemplateFoundError2###'] = $this->getLL('tx_templatedisplay_displays.noTemplateFoundError2');
					$formField .= t3lib_parsehtml::substituteMarkerArray(file_get_contents($file), $marker);
				}
            }
			catch (Exception $e) {
				$formField .= tx_basecontroller_div::wrapMessage($e->getMessage());
			}

		}
		catch (Exception $e) {
			$formField .= tx_basecontroller_div::wrapMessage($e->getMessage());
        }
		return $formField;
	}


	/**
	 * Return the translated string according to the key
	 *
	 * @param string key of label
	 */
	function getLL($key){
		$langReference = 'LLL:EXT:templatedisplay/locallang_db.xml:';
		return $GLOBALS['LANG']->sL($langReference . $key);
    }

	/**
     * This method returns the name of the table where the relations between
     * Data Providers and Controllers are saved
     * (this has been abstracted in a method in case the was of retrieving this table mame is changed in the future
     * e.g. by defining a bidirection MM-relation to the display controller, in which case the name
     * would be retrieved from the TCA instead)
     *
     * @return	string	Name of the table
     */
	protected function getMMTableName() {
		return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['mm_table'];
    }

	/**
     * This method retrieves the controller which calls this specific instance of template display
     *
     * @param	array	$row: database record corresponding the instance of template display
     */
	protected function getRelatedProvider($row) {
		// Get the tt_content record(s) the template display instance is related to
		$mmTable = $this->getMMTableName();
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid_local', $mmTable, "uid_foreign = '".$row['uid']."'");
		$numRows = count($rows);

		// The template display instance is not related yet
		if ($numRows == 0) {
			throw new Exception('No controller found');
        }

		// The template display instance is related to exactly one tt_content record (easy case)
		elseif ($numRows == 1) {
			$tt_contentRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('CType', 'tt_content', "uid = '".$rows[0]['uid_local']."'");
			$controller = t3lib_div::makeInstanceService('datacontroller', $tt_contentRecord[0]['CType']);
			$controller->loadControllerData($rows[0]['uid_local']);
			$provider = $controller->getPrimaryProvider();
			return $provider;
       }

		// The template display instance is related to more than one tt_content records
		// Some additional checks must be performed
		else {
			throw new Exception('More than one controller found');
        }
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php']);
}

?>