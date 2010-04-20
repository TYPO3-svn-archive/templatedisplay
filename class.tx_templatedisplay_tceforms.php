<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2010	Francois Suter (Cobweb) <typo3@cobweb.ch>
*					Fabien Udriot <fabien.udriot@ecodev.ch>
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
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('tesseract', 'lib/class.tx_tesseract_utilities.php'));
require_once(t3lib_extMgm::extPath('templatedisplay', 'class.tx_templatedisplay.php'));

/**
 * TCEform custom field for template mapping
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_templatedisplay
 *
 * $Id$
 */
class tx_templatedisplay_tceforms {
	protected $extKey = 'templatedisplay';

	/**
	 * This method renders the user-defined mapping field,
	 * i.e. the screen where data is mapped to the template markers
	 *
	 * @param	array			$PA: information related to the field
	 * @param	t3lib_tceforms	$fobj: reference to calling TCEforms object
	 *
	 * @return	string	The HTML for the form field
	 */
	public function mappingField($PA, t3lib_TCEforms $fobj) {
		$marker = array();
		$formField = '';
		try {
				// Get the related (primary) provider
			$provider = $this->getRelatedProvider($PA['row']);
			try {
				$fieldsArray = $provider->getTablesAndFields();
				$row = $PA['row'];

				$marker['###CONTENT_FROM_FILE###'] = '';
				$marker['###IMPORTED###'] = '';
				$marker['###TEMPLATE_CONTENT_SRC###'] = $row['template'];
				$templateContent = $row['template'];
				
					// True when the user has defined no template.
				if (empty($row['template'])) {
					$templateContent = $this->getLL('tx_templatedisplay_displays.noTemplateFoundError');
				} elseif (preg_match('/^FILE:/isU', $row['template'])) {
				
					$filePath = str_replace('FILE:', '' ,$row['template']);
					$filePath = t3lib_div::getFileAbsFileName($filePath);
					$marker['###IMPORTED###'] = '(' . $this->getLL('tx_templatedisplay_displays.imported') . ')';
					if (is_file($filePath)) {
					 	$templateContent = file_get_contents($filePath);
						$templateContent = str_replace('	', '  ', $templateContent);
					} else {
					 	$templateContent = $this->getLL('tx_templatedisplay_displays.fileNotFound') . ' ' . $row['template'];
					}
				}

					// Initialize the select drop down which contains the fields
				$options = '';
				foreach ($fieldsArray as $keyTable => $fields){
					$options .= '<optgroup label="' . $keyTable . '" class="c-divider">';
					foreach($fields['fields'] as $keyField => $field){
						$options .= '<option value="' . $keyTable . '.' . $keyField . '">' . $keyField . '</option>';
					}
					$options .= '</optgroup>';
				}
				$marker['###AVAILABLE_FIELDS###'] = $options;
					
					// Reinitializes the array pointer
				reset($fieldsArray);
				
					// Initialize some template variable
				$marker['###DEFAULT_TABLE###'] = key($fieldsArray);
				$marker['###TEMPLATE_CONTENT###'] = $this->transformTemplateContent($templateContent);
				$marker['###STORED_FIELD_NAME###'] = $PA['itemFormElName'];
				$marker['###STORED_FIELD_NAME_TEMPLATE###'] = str_replace('mappings', 'template', $PA['itemFormElName']);
				$marker['###STORED_FIELD_VALUE###'] = $row['mappings'];
				$marker['###INFOMODULE_PATH###'] = t3lib_extMgm::extRelPath('templatedisplay') . 'resources/images/';
				$marker['###UID###'] = $row['uid'];
				$marker['###TYPES_OPTIONS###'] = '';
				foreach (tx_templatedisplay::$defaultTypes as $type) {
					$option = '<option value="' . $type . '">' . $this->getLL('tx_templatedisplay_displays.' . $type) . '</option>';
					$marker['###TYPES_OPTIONS###'] .= $option;
				}
				$marker['###SHOW_JSON###'] = $this->getLL('tx_templatedisplay_displays.showJson');
				$marker['###EDIT_JSON###'] = $this->getLL('tx_templatedisplay_displays.editJson');
				$marker['###EDIT_HTML###'] = $this->getLL('tx_templatedisplay_displays.editHtml');
				$marker['###MAPPING###'] = $this->getLL('tx_templatedisplay_displays.mapping');
				$marker['###TYPES###'] = $this->getLL('tx_templatedisplay_displays.types');
				$marker['###FIELDS###'] = $this->getLL('tx_templatedisplay_displays.fields');
				$marker['###CONFIGURATION###'] = $this->getLL('tx_templatedisplay_displays.configuration');
				$marker['###SAVE_FIELD_CONFIGURATION###'] = $this->getLL('tx_templatedisplay_displays.saveFieldConfiguration');

					// Parse the template and render it.
				$backendTemplatefile = t3lib_div::getFileAbsFileName('EXT:templatedisplay/resources/templates/templatedisplay.html');
				$formField .= t3lib_parsehtml::substituteMarkerArray(file_get_contents($backendTemplatefile), $marker);
			}
			catch (Exception $e) {
				$formField .= tx_tesseract_utilities::wrapMessage($e->getMessage());
			}

		}
		catch (Exception $e) {
			$formField .= tx_tesseract_utilities::wrapMessage($e->getMessage());
		}
		return $formField;
	}
	
	/**
	 * Transformes $templateContent, this method is also util for Ajax called. In this case, the method is called externally.
	 * 2) wrap IF markers with a different background
	 * 2) wrap LOOP markers with a different background
	 * 1) wrap FIELD markers with a clickable href
	 *
	 * @param	string	$templateContent
	 * @return	string	$templateContent, the content transformed
	 */
	public function transformTemplateContent($templateContent) {
		$templateContent = htmlspecialchars($templateContent);

		# Wrap IF markers with a different background
		$pattern = $replacement = array();
		$pattern[] = "/(&lt;!-- *IF *\(.+--&gt;|&lt;!-- *ELSE *--&gt;|&lt;!-- *ENDIF *--&gt;)/isU";
		$replacement[] = '<span class="templatedisplay_if">$1</span>';
		
		$pattern[] = "/(&lt;!-- *EMPTY *--&gt;|&lt;!-- *ENDEMPTY *--&gt;)/isU";
		$replacement[] = '<span class="templatedisplay_empty">$1</span>';

		$pattern[] = "/(#{3}.+#{3})/isU";
		$replacement[] = '<span class="templatedisplay_label">$1</span>';

		#$pattern[] = "/(&lt;!-- *ENDIF *--&gt;)/isU";
		#$replacement[] = '<span class="templatedisplay_if">$1</span>';

		# LIMIT, UPPERCASE, LOWERCASE, UPPERCASE_FIRST
		$pattern[] = "/(PRINTF\(.+\)|LIMIT\(.+\)|UPPERCASE\(.+\)|LOWERCASE\(.+\)|UPPERCASE_FIRST\(.+\)|COUNT\(.+\))|PAGE_STATUS\(.+\)/isU";
		$replacement[] = '<span class="templatedisplay_function">$1</span>';

		# Wrap LOOP markers with a different background
		$pattern[] = "/(&lt;!-- *LOOP *\(.+--&gt;)/isU";
		$replacement[] = '<span class="templatedisplay_loop">$1</span>';

		$pattern[] = "/(&lt;!-- *ENDLOOP *--&gt;)/isU";
		$replacement[] = '<span class="templatedisplay_loop">$1</span>';

		# Wrap FIELD markers with a clickable href
		$pattern[] = '/(#{3}FIELD.+#{3}|#{3}OBJECT.+#{3})/isU';
		$path = t3lib_extMgm::extRelPath('templatedisplay').'resources/images/';
		$_replacement = '<span class="mapping_pictogrammBox">';
		$_replacement .= '<a href="#" onclick="return false">$1</a>';
		$_replacement .= '<img src="'.$path.'empty.png" alt="" class="mapping_pictogramm1"/>';
		$_replacement .= '<img src="'.$path.'empty.png" alt="" class="mapping_pictogramm2"/>';
		$_replacement .= '</span>';
		$replacement[] = $_replacement;
		
		return preg_replace($pattern, $replacement, $templateContent);
	}

	/**
	 * Return the translated string according to the key
	 *
	 * @param string key of label
	 */
	private function getLL($key){
		$langReference = 'LLL:EXT:templatedisplay/locallang_db.xml:';
		return $GLOBALS['LANG']->sL($langReference . $key);
	}

	/**
	 * This method returns the names of all tables that store relations
	 * between controllers and components
	 * (this has been abstracted in a method in case the way of retrieving this list is changed in the future)
	 *
	 * @return	array	List of table names
	 */
	protected function getMMTablesList() {
		return $GLOBALS['T3_VAR']['EXT']['tesseract']['controller_mm_tables'];
	}

	/**
	 * This method retrieves a controller which calls this specific instance of template display
	 *
	 * @param	array	$row: database record corresponding the instance of template display
	 */
	protected function getRelatedProvider($row) {
		$numRelations = 0;
			// Get the list of tables where relations are stored
		$mmTables = $this->getMMTablesList();
			// In each table, try to find relations to the current templatedisplay component
		foreach ($mmTables as $aTable) {
			$relations = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid_local, local_table, local_field', $aTable, "uid_foreign = '" . $row['uid'] . "' AND tablenames = 'tx_templatedisplay_displays'");
			$numRelations = count($relations);
				// Exit the loop as soon as at least one relation is found
			if ($numRelations > 0) {
				break;
			}
		}

			// If no relations were found, throw an exception
		if ($numRelations == 0) {
			throw new Exception('No controller found');

			// Otherwise get all the related records
			// NOTE:	a templatedisplay component may be related to several providers
			//			Which one we pick does not matter, as they should all provide the same structure
			//			(otherwise inconsistencies can only be expected)
		} else {
				// Get the related controllers
			$table = $relations[0]['local_table'];
			$field = $relations[0]['local_field'];
			$uid = $relations[0]['uid_local'];
			$relatedRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($field, $table, "uid = '" . $uid . "'");
				// Instantiate the corresponding service and load the data into it
			$controller = t3lib_div::makeInstanceService('datacontroller', $relatedRecords[0][$field]);
			$controller->loadData($uid);
				// NOTE: getRelatedProvider() may throw an exception, but we just let it pass at this point
			$provider = $controller->getRelatedProvider();
			return $provider;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php']);
}

?>