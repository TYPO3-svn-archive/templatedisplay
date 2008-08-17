<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Francois Suter (Cobweb) <typo3@cobweb.ch>
*  (c) 2008 Fabien Udriot (Ecodev) <fabien.udriot@ecodev.ch>
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
 * Plugin 'Data Displayer' for the 'templatedisplay' extension.
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @author	Fabien Udriot <fabien.udriot@ecodev.ch>
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
		
		// Fetches mappings + template file
		$whereClause = "uid = '".$this->uid."'";
		$whereClause .= $GLOBALS['TSFE']->sys_page->enableFields($this->table, $GLOBALS['TSFE']->showHiddenRecords);

		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('mappings,template',$this->table,$whereClause);
		if(!isset($record[0]['mappings'])){
			$this->result .= '<div style="color :red; font-weight: bold">No templatedisplay has been found for uid = '.$this->uid . '.</div>';
			$this->result .= '<div style="color :red; font-weight: bold; margin-top: 10px;">Templatedisplay\'s record may be deleted or hidden.</div>';
			return false;
		}

		// Loads the template file
		$templatePath = 'uploads/tx_templatedisplay/'.$record[0]['template'];
		if(is_file($templatePath)){
			$templateCode = file_get_contents($templatePath);
		}
		
		// Transforms the string from field mappings into a PHP array.
		// This array contains the mapping information btw a marker and a field.
		$datasources = json_decode($record[0]['mappings'],true);
		
		#$string = '<!-- ###LOOP.pages### begin -->';
		#$pattern = '/LOOP\.([^#]+)### begin/i';
		#preg_match_all($pattern,$templateCode,$matches);
		
		$marker = '###LOOP.' . self::$structure['name'] . '###';
		$subTemplate = t3lib_parsehtml::getSubpart($templateCode, $marker);
//		print_r(self::$structure['header']);

		$langMarkerArray = array();
		foreach (self::$structure['header'] as $index => $labelArray) {
			$langMarkerArray['###LABEL.' . $index . '###'] = $labelArray['label'];
		}

		$subTemplateContent = '';
		foreach (self::$structure['records'] as $records) {
			$_markerArray = array();
			if (is_array($records)) {
				foreach ($records as $index => $record) {
					if ($index != 'sds:subtables') {
						$_markerArray['###FIELD.'.$index.'###'] = $record;
					}
				}
				// Merge field content with label translation
				$_markerArray = array_merge($_markerArray, $langMarkerArray);
				$subTemplateContent .= t3lib_parsehtml::substituteMarkerArray($subTemplate, $_markerArray);
			}
		}

		// Search / replace label translation
		$templateCode = t3lib_parsehtml::substituteMarkerArray($templateCode, $langMarkerArray);

		// Substitute subpart
		$templateContent = t3lib_parsehtml::substituteSubpart($templateCode, $marker, $subTemplateContent);
		$this->result = $templateContent;
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



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/pi1/class.tx_templatedisplay_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/pi1/class.tx_templatedisplay_pi1.php']);
}

?>