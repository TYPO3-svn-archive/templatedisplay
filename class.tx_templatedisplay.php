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
	protected $structure = array(); // Input standardised data structure
	protected $result; // The result of the processing by the Data Consumer

	protected $subTemplateCode = array();
	protected $labelMarkers = array();
	protected $fieldMarker = array();
	protected $markers = array();

	protected $datasource = array();
	protected $fieldsInDatasource = array();

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
	public function loadData($data) {
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
		$this->structure = $structure;
	}

	/**
	 * This method is used to pass a filter to the Data Consumer
	 *
	 * @param 	array	$filter: Data Filter structure
	 * @return	void
	 */
	public function setDataFilter($filter) {
		$this->filter = $filter;
	}

	/**
	 * This method is used to get a data structure
	 *
	 * @return 	array	$structure: standardised data structure
	 */
	public function getDataStructure() {
		return $this->structure;
	}

	/**
	 * This method returns the result of the work done by the Data Consumer (FE output or whatever else)
	 *
	 * @return	mixed	the result of the Data Consumer's work
	 */
	public function getResult() {
		return $this->result;
	}

	/**
	 * This method sets the result. Useful for hooks.
	 *
	 * @return	void
	 */
	public function setResult($result) {

		$this->result = $result;
	}

	/**
	 * This method starts whatever rendering process the Data Consumer is programmed to do
	 *
	 * @return	void
	 */
	public function startProcess() {

		// Declares global objects
		global $TYPO3_CONF_VARS;

		/* Hook that enables to pre process the output) */
		if (is_array($TYPO3_CONF_VARS['EXTCONF']['templatedisplay']['PreProcessingProc'])) {
			$_params = array(); // Associative array. In this case, $_params is empty.
			foreach ($TYPO3_CONF_VARS['EXTCONF']['templatedisplay']['PreProcessingProc'] as $_funcRef) {
				t3lib_div::callUserFunction($_funcRef, $_params, $this);
			}
		}

		// Initializes local cObj
		$this->localCObj = t3lib_div::makeInstance('tslib_cObj');

		// Initializes LANG Object. The object does'not exist in the frontend
		global $LANG;
		if($LANG == null){

			if (isset($GLOBALS['TSFE']->tmpl->setup['config.']['language'])) {
				$languageCode = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
			}

			$LANG = t3lib_div::makeInstance('language');
			$LANG->init('default');
		}

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
		#$templatePath = 'uploads/tx_templatedisplay/'.$record[0]['template'];
		#if(is_file($templatePath)){
	#		$templateCode = file_get_contents($templatePath);
		#}
		$templateCode = $record[0]['template'];

		// Transforms the string from field mappings into a PHP array.
		// This array contains the mapping information btw a marker and a field.
		$datasource = json_decode($record[0]['mappings'],true);

		// Transforms the configuration string into an array
		$parseObj = t3lib_div::makeInstance('t3lib_TSparser');
		foreach ($datasource as $data) {
			if(trim($data['configuration']) != ''){
				// Clears the setup (to avoid typoscript incrementation)
				$parseObj->setup = array();
				$parseObj->parse($data['configuration']);
				$data['configuration'] = $parseObj->setup;
			}
			else{
				$data['configuration'] = array();
			}

			// Concatains a new marker. Will look like: table.field
			$_marker = $data['table'] . '.' . $data['field'];

			// Replace the ###FIELD.xxx### by the value "table.field"
			$pattern = '/###' . $data['marker'] . '###/isU';
			$templateCode = preg_replace($pattern, '###' . $data['marker'] . '.' . $_marker . '###', $templateCode);
			$this->fieldsInDatasource[$data['marker']] = $_marker;

			// Build the datasource. This is an associative array
			$this->datasource[$data['marker']] = $data;
		}

		// Get the content from sub template, typically LOOP part
		$subTemplateContent = $this->getSubContent($this->structure,$templateCode);

		// Substitutes subpart
		$templateContent = t3lib_parsehtml::substituteSubpart($templateCode, $this->markers[$this->structure['name']], $subTemplateContent);

		// Handles possible marker: ###LLL:EXT:myextension/localang.xml:myLable###
		$pattern = '/#{3}(LLL:EXT:.+)#{3}/isU';
		preg_match_all($pattern,$templateCode,$matches);
		$_labels = array();
		if(isset($matches[1])){
			foreach($matches[1] as $label){
				$_labels['###' . $label . '###'] = $LANG->sL($label);
			}
		}

		$pattern = '/#{3}PAGEBROWSER#{3}|#{3}PAGE_BROWSER#{3}/isU';
		if (preg_match($pattern,$templateContent)) {
			$conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'];
			if ($conf != null) {
				$pageBrowser = $this->localCObj->cObjGetSingle('USER',$conf);
			}
			else {
				$pageBrowser = '<span style="color:red; font-weight: bold">Error: tx_pagebrowse_pi1 not loaded</span>';
            }
			$templateContent = preg_replace($pattern, $pageBrowser, $templateContent);
		}

		// Merges together 2 labels arrays for performance reasons.
		$this->labelMarkers = array_merge($this->labelMarkers[$this->structure['name']], $_labels);

		// Substititutes label translation
		$this->result = t3lib_parsehtml::substituteMarkerArray($templateContent, $this->labelMarkers);

		// Hook that enables to post process the output)
		if (is_array($TYPO3_CONF_VARS['EXTCONF']['templatedisplay']['PostProcessingProc'])) {
			$_params = array(); // Associative array. In this case, $_params is empty.
			foreach ($TYPO3_CONF_VARS['EXTCONF']['templatedisplay']['PostProcessingProc'] as $_funcRef) {
				t3lib_div::callUserFunction($_funcRef, $_params, $this);
			}
		}
	}

	/**
	 * Recursive method. Gets the subpart template and substitutes content (label or field).
	 *
	 * @param array		$sdd
	 * @param string	$templateCode
	 * @return string	HTML code
	 */
	protected function getSubContent(&$sds, $templateCode){

		// Defines marker array according to $sds['name'] which contains a table name.
		// This marker is used to extract a subtemplate
		$this->markers[$sds['name']] = '###LOOP.' . $sds['name'] . '###';

		// Defines subTemplateCode (template HTML) array according to $sds['name'] which contains a table name.
		$this->subTemplateCode[$sds['name']] = t3lib_parsehtml::getSubpart($templateCode, $this->markers[$sds['name']]);

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

			// initialize data
			$this->localCObj->start($records);

			// ... and stores them in an array.
			foreach ($records as $field => $value) {
				// Important control. Makes sure the field has been mapped.
				// Furthermore, it avoids the field "sds:subtables" to enter in the test
				$marker = $sds['name'] . '.' . $field;

				if (in_array($marker, $this->fieldsInDatasource)) {

					// A value can be used for many markers. Loop around them.
					$keys = array_keys($this->fieldsInDatasource, $marker);

					foreach	($keys as $key) {
						switch ($this->datasource[$key]['type']) {
							case 'text':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['value'] = $value;
								$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->TEXT($configuration);
								break;
							case 'image':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['file'] = $value;

								// Sets the alt attribute if no altText is defined
								if (!isset($configuration['altText'])) {
									// Gets the file name
									$configuration['altText'] = $this->getFileName($configuration['file']);

								}

								// Sets the title attribute if no title is defined
								if (!isset($configuration['titleText'])) {
									if ($configuration['altText'] != '') {
										$configuration['titleText'] = $configuration['altText'];
									}
									else{
										$configuration['titleText'] = $this->getFileName($configuration['file']);
									}
								}

								// Makes sure the file exists
								if (is_file($configuration['file'])) {
									$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->IMAGE($configuration);
								}
								else {
									$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = '<img src="" class="templateDisplay_imageNotFound" alt="Image not found"/>';
								}
								break;
							case 'linkToDetail':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['useCacheHash'] = 1;
								if (!isset($configuration['returnLast'])) {
									$configuration['returnLast'] = 'url';
                                }
								$additionalParams = '&' . $this->pObj->prefixId . '[table]=' . $sds['name'] . '&' . $this->pObj->prefixId .'[showUid]=' . $value;
								$configuration['additionalParams'] = $additionalParams . $this->localCObj->stdWrap($configuration['additionalParams'], $configuration['additionalParams.']);

								// Generates the link
								$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->typolink('',$configuration);
								break;
							case 'linkToPage':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['useCacheHash'] = 1;
								if (!isset($configuration['returnLast'])) {
									$configuration['returnLast'] = 'url';
                                }
								$configuration['additionalParams'] = $additionalParams . $this->localCObj->stdWrap($configuration['additionalParams'], $configuration['additionalParams.']);

								// Generates the link
								$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->typolink('',$configuration);
								break;
							case 'linkToFile':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['useCacheHash'] = 1;
								if (!isset($configuration['returnLast'])) {
									$configuration['returnLast'] = 'url';
                                }
								if (!isset($configuration['parameter'])) {
									$configuration['parameter'] = $value;
                                }
								
								// replaces white spaces in filename
								$configuration['parameter'] = str_replace(' ','%20',$configuration['parameter']);
							
								// Generates the link
								$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->typolink('',$configuration);
								break;
							case 'email':
								$configuration = $this->datasource[$key]['configuration'];
								if (!isset($configuration['parameter'])) {
									$configuration['parameter'] = $value;
                                }
								// Generates the email
								$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $this->localCObj->typolink('',$configuration);
								break;
						} // end switch
                    } // end foreach
				} // end if
			}

			// Merges "field" with "label" and substitutes content
			$_fieldMarkers = array_merge($_fieldMarkers, $this->labelMarkers[$sds['name']]);

			// $_templateContent contains the temporary HTML. Whenever getSubContent() is called recursively, the content is passed to the method
			$_templateContent = t3lib_parsehtml::substituteMarkerArray($this->subTemplateCode[$sds['name']], $_fieldMarkers);
			$templateContent .= $_templateContent;

			// If the records contains subtables, recursively calls getSubContent()
			// Else, removes a possible unwanted part <!-- ###LOOP.unsed ### begin -->.+<!-- ###LOOP.unsed ### end -->
			if (isset($records['sds:subtables'])) {
				foreach ($records['sds:subtables'] as $subSds) {
					// get the subContent
					$subTemplateContent = $this->getSubContent($subSds, $_templateContent);

					// Substitutes the subcontent with the main content
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
     * Extracts the filename of a path
     *
     * @param	string	$filename
     * @return	string	the filename
     */
	protected function getFileName($filepath) {
		$filename = '';
		$fileInfo = t3lib_div::split_fileref($filepath);
		if (isset($fileInfo['filebody'])) {
			$filename = $fileInfo['filebody'];
		}
		return $filename;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']){
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']);
}

?>