<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Francois Suter (Cobweb) <typo3@cobweb.ch>
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
* $Id: class.tx_templatedisplay_pi1.php 3938 2008-06-04 08:39:01Z fsuter $
***************************************************************/

//require_once(PATH_tslib.'class.tslib_pibase.php');
//require_once(t3lib_extMgm::extPath('dataquery','class.tx_dataquery_wrapper.php'));
require_once(t3lib_extMgm::extPath('basecontroller', 'services/class.tx_basecontroller_consumerbase.php'));

/**
 * Plugin 'Data Displayer' for the 'datadisplay' extension.
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay extends tx_basecontroller_consumerbase {

	public $tsKey        = 'tx_templatedisplay';	// The key to find the TypoScript in "plugin."
	protected $conf;
	protected $table; // Name of the table where the details about the data display are stored
	protected $uid; // Primary key of the record to fetch for the details
	protected static $structure = array(); // Input standardised data structure
	protected $result; // The result of the processing by the Data Consumer
	protected static $currentIndex = 0;

	/**
	 * This method can be called when displaying a nested table inside the data structure
	 * It avoids the overhead of the full initialisation
	 *
	 * @return	string	The HTML content to display
	 */
	function getSubResult() {
//t3lib_div::debug($this->conf);
		$subContent = '';
		$subConfig = $this->getConfigForTable($this->conf['name']);
//t3lib_div::debug($subConfig);
		$limit = (isset($subConfig['limit'])) ? $subConfig['limit'] : 0;
			// Look for the correct subtable
		if (isset(self::$structure['records'][self::$currentIndex]['sds:subtables'])) {
			foreach (self::$structure['records'][self::$currentIndex]['sds:subtables'] as $subtableData) {
				if ($subtableData['name'] == $this->conf['name']) {
					$theSubtable = $subtableData['records'];
					break;
				}
			}
		}
			// Instantiate content object
		$localCObj = t3lib_div::makeInstance('tslib_cObj');
			// Render the subtable data, if defined
			#print_r($theSubtable);
		if (isset($theSubtable)) {
//t3lib_div::debug($theSubtable);
			$counter = 0;
			$hasFieldConfig = (isset($subConfig['field.'])) ? true : false;
			foreach ($theSubtable as $record) {
				$localCObj->start($record);
				$subrowContent = '';

// If there's a generic configuration for the fields, loop on all fields and apply configuration to each

				if ($hasFieldConfig) {
					foreach ($record as $field) {
						if ($field == 'section_count') continue; // Ignore special section count field
						$subrowContent .= $localCObj->stdWrap($field, $subConfig['field.']);
					}
				}
				$subContent .= $localCObj->stdWrap($subrowContent, $subConfig['row.']);
				$counter++;
				if ($limit > 0 && $counter >= $limit) break;
			}
		}

// Apply global stdWrap

		$subContent = $localCObj->stdWrap($subContent, $subConfig['allWrap.']);
		return $subContent;
	}

	/**
	 * This method checks whether a config exists for a given table name
	 * If yes, it returns that config, if not it returns the default one
	 *
	 * @param	string	table name
	 *
	 * @return	array	TS configuration for the rendering of the table
	 */
	function getConfigForTable($tableName) {
		if (isset($this->conf['configs.'][$tableName.'.'])) {
			return $this->conf['configs.'][$tableName.'.'];
		}
		elseif (isset($this->conf['configs.']['default.'])) {
			return $this->conf['configs.']['default.'];
		}
		else { // Default configuration shouldn't be missing really. Bad boy! TODO: issue error message
			return array();
		}
	}

	/**
	 * This method is used to pass a TypoScript configuration (in array form) to the Data Consumer
	 *
	 * @param	array	$conf: TypoScript configuration for the extension
	 */
	public function setTypoScript($conf) {
		$this->conf = $conf;
	}

// Data Consumer interface methods

	/**
	 * This method returns the type of data structure that the Data Consumer can use
	 *
	 * @return	string	type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return tx_basecontroller::$recordsetStructureType;
	}

	/**
	 * This method indicates whether the Data Consumer can use the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return $type == tx_basecontroller::$recordsetStructureType;
	}

	/**
	 * This method is used to load the details about the Data Consumer passing it whatever data it needs
	 * This will generally be a table name and a primary key value
	 *
	 * @param	array	$data: Data for the Data Consumer
	 * @return	void
	 */
	public function loadConsumerData($data) {
		$this->table = $data['table'];
		$this->uid = $data['uid'];
	}

	/**
	 * This method is used to pass a data structure to the Data Consumer
	 *
	 * @param 	array	$structure: standardised data structure
	 * @return	void
	 */
	public function setDataStructure($structure) {
		self::$structure = $structure;
	}

	/**
	 * This method starts whatever rendering process the Data Consumer is programmed to do
	 *
	 * @return	void
	 */
	public function startProcess() {
			// Get record where the details of the data display are stored
		$tableTCA = $GLOBALS['TCA'][$this->table];
		$whereClause = "uid = '".$this->uid."'";
		$whereClause .= $GLOBALS['TSFE']->sys_page->enableFields($this->table, $GLOBALS['TSFE']->showHiddenRecords);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $this->table, $whereClause);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			return false;
		}
		else {
			$this->result = '';

// Get the name and configuration for the main table

			$maintableConf = $this->getConfigForTable(self::$structure['name']);

// Set general display flag
// May be set to false on some conditions

			$display = true;

// Continue only if display is true

			if ($display) {

// Display the data
// First set some flags depending on TS template

				$hasFieldConfig = (isset($maintableConf['field.'])) ? true : false;
				$sectionBreak = (empty($maintableConf['section'])) ? '' : $maintableConf['section.']['field'];

// Initialise some values
// Get an instance of tslib_cobj for rendering the data

				$localContent = '';
				$sectionContent = '';
				$oldSectionValue = '';
				$sectionCount = 1;
				$localCObj = t3lib_div::makeInstance('tslib_cObj');

				foreach (self::$structure['records'] as $index => $record) {
					$record['section_count'] = $sectionCount;
					self::$currentIndex = $index;

// Load the local cObj with data from the database

					$localCObj->start($record);

// If sections are activated, check if a new section has started

					if (!empty($sectionBreak)) {
						$newSectionValue = $record[$sectionBreak];

// New section

						if ($newSectionValue != $oldSectionValue) {

// Wrap content from previous section
// Then add section header

							if ($sectionCount > 1) $localContent .= $localCObj->stdWrap($sectionContent, $maintableConf['section.']['content.']);
							$localContent .= $localCObj->stdWrap($newSectionValue, $maintableConf['section.']['header.']);

// Switch section value, reinitialise section content and increase section count

							$oldSectionValue = $newSectionValue;
							$sectionContent = '';
							$sectionCount++;
						}
					}
					$rowContent = '';

// If there's a generic configuration for the fields, loop on all fields and apply configuration to each

					if ($hasFieldConfig) {
						foreach ($record as $field) {
							if ($field == 'section_count') continue; // Ignore special section count field
							$rowContent .= $localCObj->stdWrap($field, $maintableConf['field.']);
						}
					}

// Apply stdWrap to the row (i.e. data record)

					$sectionContent .= $localCObj->stdWrap($rowContent, $maintableConf['row.']);
				}

// If sections were not activated, just take the result from the loop
// Otherwise apply section content stdWrap to last section

				if (empty($sectionBreak)) {
					$localContent = $sectionContent;
				}
				else {
					$localContent .= $localCObj->stdWrap($sectionContent, $maintableConf['section.']['content.']);
				}

// Apply global stdWrap

				$content .= $localCObj->stdWrap($localContent, $maintableConf['allWrap.']);
			}
			else {
				$content = '';
			}

			$this->result .= $content;
		}
	}

	/**
	 * This method returns the result of the work done by the Data Consumer (FE output or whatever else)
	 *
	 * @return	mixed	the result of the Data Consumer's work
	 */
	public function getResult() {
		return $this->result;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/datadisplay/pi1/class.tx_templatedisplay_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/datadisplay/pi1/class.tx_templatedisplay_pi1.php']);
}

?>