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

	public $tsKey = 'tx_templatedisplay';	// The key to find the TypoScript in "plugin."
	protected $conf;
	protected $table; // Name of the table where the details about the data display are stored
	protected $uid; // Primary key of the record to fetch for the details
	protected static $structure = array(); // Input standardised data structure
	protected $result; // The result of the processing by the Data Consumer

	protected $subTemplateCode = array();
	protected $labelMarkers = array();
	protected $fieldMarker = array();
	protected $markers = array();

	protected $datasource = array();
	protected $configurations = array();
	protected $cObjTypes = array();

	/**
	 *
	 * @var tslib_cObj
	 */
	protected $localCObj;
	
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
		
		// Initializes local cObj
		$this->localCObj = t3lib_div::makeInstance('tslib_cObj');

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
		$this->datasource = json_decode($record[0]['mappings'],true);

		// Transforms the configuration string into an array
		$parseObj = t3lib_div::makeInstance('t3lib_TSparser');
		foreach ($this->datasource as &$data) {
			if(trim($data['configuration']) != ''){
				$parseObj->parse($data['configuration']);
				$data['configuration'] = $parseObj->setup;
			}
			else{
				$data['configuration'] = array();
			}
		}

		$subTemplateContent = $this->getSubContent(self::$structure,$templateCode);

		// Substitutes subpart
		$templateContent = t3lib_parsehtml::substituteSubpart($templateCode, $this->markers[self::$structure['name']], $subTemplateContent);

		// Handles possible marker: ###LLL:EXT:myextension/localang.xml:myLable###
		$pattern = '/#{3}(LLL:EXT:.+)#{3}/isU';
		preg_match_all($pattern,$templateCode,$matches);
		$_labels = array();
		if(isset($matches[1])){
			foreach($matches[1] as $label){
				$_labels['###' . $label . '###'] = $GLOBALS['LANG']->sL($label);
			}
		}

		// Merges together 2 label arrays for performance reasons.
		$this->labelMarkers = array_merge($this->labelMarkers[self::$structure['name']], $_labels);

		// Substititutes label translation
		$this->result = t3lib_parsehtml::substituteMarkerArray($templateContent, $this->labelMarkers);
	}


	/**
	 * Recursive method. Gets the subpart template and substitutes content (label or field).
	 *
	 * @param array		$sdd
	 * @param string	$templateCode
	 * @return string	HTML code
	 */
	private function getSubContent(&$sds, $templateCode){

		// Defines marker array according to $sds['name'] which contains a table name.
		if (!isset($this->markers[$sds['name']])) {
			$this->markers[$sds['name']] = '###LOOP.' . $sds['name'] . '###';
		}

		// Defines subTemplateCode (template HTML) array according to $sds['name'] which contains a table name.
		if (!isset($this->subTemplateCode[$sds['name']])) {
			$this->subTemplateCode[$sds['name']] = t3lib_parsehtml::getSubpart($templateCode, $this->markers[$sds['name']]);
		}

		$templateContent = '';

		// Initializes language label and stores the lables for a possible further use.
		if (!isset($this->labelMarkers[$sds['name']])) {
			$this->labelMarkers[$sds['name']] = array();

			foreach ($sds['header'] as $index => $labelArray) {
				$this->labelMarkers[$sds['name']]['###LABEL.' . $index . '###'] = $labelArray['label'];
			}
		}

		// Traverses the records...
		foreach ($sds['records'] as $records) {
			$_fieldMarkers = array();

			// ... and stores them in an array.
			foreach ($records as $field => $value) {
				if ($field != 'sds:subtables') {
					switch ($this->getCObjType($sds['name'],$field)) {
						case 'text':
							$configuration = $this->getConfiguration($sds['name'],$field);
							$configuration['value'] = $value;
							$_fieldMarkers['###FIELD.'.$field.'###'] = $this->localCObj->TEXT($configuration);
							break;
						case 'image':
							break;
					}

				}
			}

			// Merges "field" with "label" and substitutes content
			$_fieldMarkers = array_merge($_fieldMarkers, $this->labelMarkers[$sds['name']]);
			$templateContent .= t3lib_parsehtml::substituteMarkerArray($this->subTemplateCode[$sds['name']], $_fieldMarkers);

			// If the records contains subtables, recursively calls getSubContent()
			// Else, removes a possible unwanted part <!-- ###LOOP.unsed ### begin -->.+<!-- ###LOOP.unsed ### end -->
			if (isset($records['sds:subtables'])) {
				foreach ($records['sds:subtables'] as $subSds) {
					$subTemplateContent = $this->getSubContent($subSds,$this->subTemplateCode[$sds['name']]);
					$templateContent = t3lib_parsehtml::substituteSubpart($templateContent, $this->markers[$subSds['name']], $subTemplateContent);
				}
			}
			else{
				$pattern = '/<!-- *###LOOP\.[^#]+### *begin *-->.+<!-- *###LOOP\.[^#]+### *end *-->/isU';
				# Debug code
				#preg_match_all($pattern,$templateContent,$matches);
				#print_r($matches);
				$templateContent = preg_replace($pattern, '', $templateContent);
			}
		}

		return $templateContent;
	}

	/**
	 * This method returns a configuration array. Furthermore, it stores the array for later use.
	 *
	 * @return	mixed	TypoScript configuration array
	 */
	private function getCObjType($table,$field){
		if(!isset($this->cObjTypes[$table.$field])){
			foreach($this->datasource as $data){
				if($data['table'] == $table && $data['field'] == $field){
					$this->cObjTypes[$table.$field] = $data['type'];
				}
			}
			if(!isset($this->cObjTypes[$table.$field])){
				$this->cObjTypes[$table.$field] = 'text';
			}
		}
		return $this->cObjTypes[$table.$field];
	}

	/**
	 * This method returns a configuration array. Furthermore, it stores the array for later use.
	 *
	 * @return	mixed	TypoScript configuration array
	 */
	private function getConfiguration($table,$field){
		if(!isset($this->configurations[$table.$field])){
			foreach($this->datasource as $data){
				if($data['table'] == $table && $data['field'] == $field){
					$this->configurations[$table.$field] = $data['configuration'];
				}
			}
			if(!isset($this->configurations[$table.$field])){
				$this->configurations[$table.$field] = array();
			}
		}
		return $this->configurations[$table.$field];
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