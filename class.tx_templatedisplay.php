<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Francois Suter (Cobweb) <typo3@cobweb.ch>
*  (c) 2008 Fabien Udriot <typo3@omic.ch>
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

	
	public $extKey = 'templatedisplay';
	protected $conf;
	protected $table; // Name of the table where the details about the data display are stored
	protected $uid; // Primary key of the record to fetch for the details
	protected $structure = array(); // Input standardised data structure
	protected $result; // The result of the processing by the Data Consumer

	protected $subTemplateCode = array();
	protected $labelMarkers = array();
	protected $fieldMarker = array();
	protected $markers = array();
	protected $counter = array();

	protected $datasource = array();
	protected $fieldsInDatasource = array();

	/**
     *
     * @var	array	$functions: list of function handled by templatedisplay 'LIMIT_TEXT', 'UPPERCASE', 'LOWERCASE', 'UPPERCASE_FIRST
     */
	protected $functions = array('LIMIT_TEXT', 'UPPERCASE', 'LOWERCASE', 'UPPERCASE_FIRST');
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

		// ************************************
		// ********** INITIALISATION **********
        // ************************************

		// Declares global objects
		global $TYPO3_CONF_VARS;
		global $LANG;

		// Makes sure mapping exists, otherwise stops
		if(!isset($this->consumerData['mappings'])){
			$this->result .= '<div style="color :red; font-weight: bold">No templatedisplay has been found for uid = '.$this->uid . '.</div>';
			$this->result .= '<div style="color :red; font-weight: bold; margin-top: 10px;">Templatedisplay\'s record may be deleted or hidden.</div>';
			#throw new Exception('No mappings found for uid = ' . $this->uid);
			return false;
		}

		// Initializes local cObj
		$this->localCObj = t3lib_div::makeInstance('tslib_cObj');

		// Initializes LANG Object whether the object does not exist. (for example in the frontend)
		if($LANG == null){

			if (isset($GLOBALS['TSFE']->tmpl->setup['config.']['language'])) {
				$languageCode = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
			}

			$LANG = t3lib_div::makeInstance('language');
			$LANG->init('default');
		}

		// ****************************************
		// ********** FETCHES DATASOURCE **********
        // ****************************************
		
		// Transforms the string from field mappings into a PHP array.
		// This array contains the mapping information btw a marker and a field.
		$datasource = json_decode($this->consumerData['mappings'],true);
		$uniqueMarkers = array();
		
		// Transforms the typoScript configuration into an array.
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

			// Concatains some data to create a new marker. Will look like: table.field
			$_marker = $data['table'] . '.' . $data['field'];

			// IMPORTANT NOTICE:
			// The idea is to make the field unique
			// Replaces the ###FIELD.xxx### by the value "table.field"
            // Ex: [###FIELD.title###] => ###FIELD.title.pages.title###
			$uniqueMarkers['###' . $data['marker'] . '###'] = '###' . $data['marker'] . '.' . $_marker . '###';

			// Stores which markers are going to be substitued to what fields
			// Ex: [FIELD.title] => pages.title
			$this->fieldsInDatasource[$data['marker']] = $_marker;

			// Builds the datasource as an associative array.
			// $data contains the complete record [marker], [table], [field], [type], [configuration]
			$this->datasource[$data['marker']] = $data;
		}
		
		// ***************************************
		// ********** BEGINS PROCESSING **********
        // ***************************************

		// LOCAL DOCUMENTATION:
		// $templateCode -> HTML template roughly extracted from the database
		// $subTemplateContent -> sub HTML transformed (temporary variable)
		// $templateContent -> HTML that is going to be outputed

		// Loads the template file
		$templateCode = $this->consumerData['template'];

		// Starts transformation of $templateCode.
		// Must be at the beginning of startProcess()
		$templateCode = $this->preProcessIf($templateCode);
		$templateCode = $this->preProcessFunctions($templateCode);

		// Handles possible marker: ###LLL:EXT:myextension/localang.xml:myLable###, ###GP:###, ###TSFE:### etc...
		$LLLMarkers = $this->getLLLMarkers($templateCode);
		$GPMarkers = $this->getExpressionMarkers('GP', array_merge(t3lib_div::_GET(), t3lib_div::_POST()), $templateCode);
		$TSFEMarkers = $this->getExpressionMarkers('TSFE', $GLOBALS['TSFE'], $templateCode);
		$pageMarkers = $this->getExpressionMarkers('page', $GLOBALS['TSFE']->page, $templateCode);
		$globalVariablesMarkers = $this->getGlobalVariablesMarkers($templateCode); // Global template variable can be ###TOTAL_OF_RECORDS### ###SUBTOTAL_OF_RECORDS###

		// Merges array, in order to have only one array (performance!)
		$markers = array_merge($uniqueMarkers, $LLLMarkers, $GPMarkers, $TSFEMarkers, $pageMarkers, $globalVariablesMarkers);

		#$this->displayDebug('markers', __LINE__);
		if (isset($GLOBALS['_GET']['debug']['markers']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug('content of $markers, line ' . __LINE__);
			t3lib_div::debug($markers);
		}

		if (isset($GLOBALS['_GET']['debug']['structure']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug('content of $this->structure');
			t3lib_div::debug($this->structure);
		}
		
		// We want a convenient $templateCode. Substitutes $markers
		$templateCode = t3lib_parsehtml::substituteMarkerArray($templateCode, $markers);

		// Handles LOOP tag. Transforms whenever necessary <!-- LOOP(table) --> into <!-- ###LOOP.table### begin -->
		$templateCode = $this->processLOOP($templateCode);

		// Gets the content from sub template, typically LOOP part
		$subTemplateContent = $this->getSubContent($this->structure, $templateCode);

		// Defines the $templateContent (@see variable explanation).
		// Content can be "simply" $subTemplateContent...
		// ...but can be something more if the $templateCode contains ###LOOP.myTableLevel1###

		// Wherever markers are found, substitutes them!
		if ($this->markers[$this->structure['name']] != '') {

			// Makes sure the LOOP is present in the templateCode.
			// It not, it might mean, there is a LOOP level2 without a LOOP level1
			if (preg_match('/#{3}LOOP.' . $this->structure['name'] . '#{3}/',$templateCode)) {
				$templateContent = t3lib_parsehtml::substituteSubpart($templateCode, $this->markers[$this->structure['name']], $subTemplateContent);

				// Substititutes the remaining label
				$templateContent = t3lib_parsehtml::substituteMarkerArray($templateContent, $this->labelMarkers[$this->structure['name']]);
			}
			else {
				$templateContent = $subTemplateContent;
			}
		}
		else {
			$templateContent = $subTemplateContent;
		}

		// Handles the page browser
		$templateContent = $this->processPageBrowser($templateContent);

		// Handles the <!--IF(###MARKER### == '')-->
		// Evaluates the condition and replaces the content whether it is necessary
		// Must be at the end of startProcess()
		$templateContent = $this->postProcessIf($templateContent);
		$this->result = $this->postProcessFunctions($templateContent);

		// Hook that enables to post process the output)
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessResult'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessResult'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$this->result = $postProcessor->postProcessResult($this->result, $this);
			}
		}
	}

	/**
	 * Makes sure the operand does not contain the symbol "'".
	 *
	 * @param string	$operand
	 * @return string
	 */
	protected function sanitizeOperand($operand) {
		$operand = substr(trim($operand), 1);
		$operand = substr($operand, 0, strlen($operand) - 1);
		$operand = str_replace("'","\'",$operand);
		return "'" . $operand . "'";
	}

	/**
     * If found, returns markers, of type LLL
     * 
	 * Example of marker: ###LLL:EXT:myextension/localang.xml:myLable###
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function getLLLMarkers($content) {
		$markers = array();
		$pattern = '/#{3}(LLL:EXT:.+)#{3}/isU';
		if (preg_match_all($pattern, $content, $matches)) {
			global $LANG;
			if(isset($matches[1])){
				foreach($matches[1] as $marker){
					$markers['###' . $marker . '###'] = $LANG->sL($marker);
				}
			}
		}
		return $markers;
	}

	/**
     * If found, returns markers, of type $key (GP, TSFE, page)
     *
	 * Example of GP marker: ###GP:tx_displaycontroller_pi2|parameter###
	 *
	 * @param	string	$key: Maybe, tsfe, page, gp
	 * @param	key		$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function getExpressionMarkers($key, &$source, $content) {
		
		// Makes sure $expression has a value
		if (empty($key)){
			throw new Exception('No key given to getExpressionMarkers()');
		}

		// Defines empty array.
		$markers = array();

		// Tests if $expressions are found
        // Does it worth to get into the process of evaluation?
		$pattern = '/#{3}(' . $key . ':)(.+)#{3}/isU';
		if (preg_match_all($pattern, $content, $matches)) {
			if(isset($matches[2])){
				$numberOfRecords = count($matches[0]);
				for($index = 0; $index < $numberOfRecords; $index ++) {
					$markers[$matches[0][$index]] = $this->getValue($source, $matches[2][$index]);
				}
			}
		}
		return $markers;
	}

	/**
     * If found, returns markers, of type global template variable
     * Global template variable can be ###TOTAL_OF_RECORDS### ###SUBTOTAL_OF_RECORDS###
     *
     * @param	string	$content: HTML content
     * @return  string	$content: transformed HTML content
     */
	protected function getGlobalVariablesMarkers($content) {
		$markers = array();
		if (preg_match('/#{3}TOTAL_OF_RECORDS#{3}/isU', $content)) {
			$markers['###TOTAL_OF_RECORDS###']  = $this->structure['totalCount'];
        }
		if (preg_match('/#{3}SUBTOTAL_OF_RECORDS#{3}/isU', $content)) {
			$markers['###SUBTOTAL_OF_RECORDS###']  = $this->structure['count'];
        }
		return $markers;
	}

	/**
	 * This method is used to get a value from inside a multi-dimensional array or object
	 * NOTE: this code is largely inspired by tslib_content::getGlobal()
	 *
	 * @param	mixed	$source: array or object to look into
	 * @param	string	$indices: "path" of indinces inside the multi-dimensional array, of the form index1|index2|...
	 * @return	mixed	Whatever value was found in the array
     * @author  François Suter (Cobweb)
	 */
	protected function getValue($source, $indices) {
		if (empty($indices)) {
			throw new Exception('No key given for source');
		}
		else {
			$indexList = t3lib_div::trimExplode('|', $indices);
			$value = $source;
			foreach ($indexList as $key) {
				if (is_object($value) && isset($value->$key)) {
					$value = $value->$key;
				}
				elseif (is_array($value) && isset($value[$key])) {
					$value = $value[$key];
				}
				else {
					$value = ''; // no value found
				}
			}
		}
		return $value;
	}

	/**
	 * Handles the page browser
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function processPageBrowser($content) {
		$pattern = '/#{3}PAGE_BROWSER#{3}/isU';
		if (preg_match($pattern, $content)) {

			// Fetches the configuration
			$conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'];

			if ($conf != null) {

				// Adds limit to the query and calculates the number of pages.
				if ($this->filter['limit']['max'] != '' && $this->filter['limit']['max'] != '0') {
					//$conf['extraQueryString'] .= '&' . $this->pObj->getPrefixId() . '[max]=' . $this->filter['limit']['max'];
					$conf['numberOfPages'] = ceil($this->structure['totalCount'] / $this->filter['limit']['max']);
				}
				else {
					$conf['numberOfPages'] = 1;
				}

				// Can be tx_displaycontroller_pi1 OR tx_displaycontroller_pi1
				$conf['pageParameterName'] = $this->pObj->getPrefixId() . '|page';

				$this->localCObj->start(array(), '');
				$pageBrowser = $this->localCObj->cObjGetSingle('USER',$conf);
			}
			else {
				$pageBrowser = '<span style="color:red; font-weight: bold">Error: extension pagebrowse not loaded</span>';
			}

			// Replaces the marker by some HTML content
			$content = preg_replace($pattern, $pageBrowser, $content);
		}
		return $content;
	}

	/**
	 * Pre processes the <!--IF(###MARKER### == '')-->, puts a '' around the marker
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function preProcessIf($content) {

		// Preprocesses the <!--IF(###MARKER### == '')-->, puts a '' around the marker
		$pattern = '/<!-- *IF.+-->/isU';
		if (preg_match_all($pattern, $content, $matches)) {

			// Loop around the matches
			foreach	($matches[0] as $match){
				$pattern = '/#{3}.+#{3}/isU';
				preg_match_all($pattern, $match, $_matches);
				$replaceString = $match;
				foreach ($_matches[0] as $_match) {
					$replaceString = str_replace($_match,'\'' . $_match . '\'',$replaceString);
				}
				$content = str_replace($match,$replaceString,$content);
			}
		}
		return $content;
	}
	
	/**
	 * Pre processes the template function LIMIT_TEXT, UPPERCASE, LOWERCASE, UPPERCASE_FIRST.
     * Makes them recognizable by wrapping them with !--### ###--
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function preProcessFunctions($content) {
		foreach ($this->functions as $function) {
			$pattern = '/' . $function . '\(.+\)/isU';
			if (preg_match_all($pattern, $content, $matches)) {
				foreach ($matches[0] as $match) {
					$content = str_replace($match,'!--###' . $match . '###--',$content);
                }
			}
        }
		return $content;
	}
	
	/**
     * Handles LOOP tag.
     * Transforms whenever necessary <!-- LOOP(table) --> into <!-- ###LOOP.table### begin -->.
     * 
     * @param	string	$content: the HTML code.
     * @return	string	$content: the HTML code transformed
     */
	protected function processLOOP($content) {
		$pattern = '/<!-- *LOOP *\((.+)\) *-->/isU';
		if (preg_match_all($pattern, $content, $matches)) {
			$numberOfMatches = count($matches[0]);

			// Reverses loop. The last <!--ENDLOOP--> becomes the first one
			for ($index = $numberOfMatches; $index > 0; $index --) {
				$search = $matches[0][$index - 1];
				$replacement = '<!-- ###LOOP.' . $matches[1][$index - 1] . '### begin -->';
				$content = str_replace($search, $replacement , $content);
				
				$pattern = '/<!-- *ENDLOOP *-->/isU';
				$replacement = '<!-- ###LOOP.' . $matches[1][$index - 1] . '### end -->';
				$content = preg_replace($pattern, $replacement, $content, 1);
            }
        }
		return $content;
    }

	/**
	 * Post processes the <!--IF(###MARKER### == '')-->
	 * Evaluates the condition and replaces the content when necessary
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function postProcessIf($content) {
		$pattern = '/(<!-- *IF *\( *(.+)\) *-->)(.+)(<!-- *ENDIF *-->)/isU';
		if (preg_match_all($pattern, $content, $matches)) {

			// count number of IF
			$numberOfElements = count($matches[0]);

			// Evaluates the condition
			for ($index = 0; $index < $numberOfElements; $index++) {
				$condition = $matches[2][$index];

				// true means something went wrong in the eval()
				if (eval('$result = ' . $condition .';') === false) {
					$pattern = '/(.+)([\!\= ]+)(.+)/isU';
					$pattern = '/(.+) *(!=|==|<=|>=) *(.+)/is';
					preg_match($pattern, $condition, $_matches);
					if (isset($_matches[3])) {
						$operand1 = $this->sanitizeOperand($_matches[1]);
						$operand2 = $this->sanitizeOperand($_matches[3]);
						$condition = $operand1 . $_matches[2] . $operand2;
						eval('$result = ' . $condition .';');
					}
				}

				$searchContent = $matches[0][$index];
				$replaceContent = $matches[3][$index];

				// Tests the result
				if ($result) {
					// checks if $replaceContent contains a <!-- ELSE -->
					if (preg_match('/(.+)(<!-- *ELSE *-->)(.+)/is', $replaceContent, $_matches)) {
						$replaceContent = $_matches[1];
					}
					// else is not necessary, it would be equal to write $replaceContent = $replaceContent;
				}
				else {
					// checks if $replaceContent contains a <!-- ELSE -->
					if (preg_match('/(.+)(<!-- *ELSE *-->)(.+)/is', $replaceContent, $_matches)) {
						$replaceContent = $_matches[3];
					}
					else {
						$replaceContent = '';
					}
				}
				$content = str_replace($searchContent, trim($replaceContent), $content);
			}
		}
		return $content;
	}

	
	/**
	 * Handles the function: LIMIT_TEXT, UPPERCASE, LOWERCASE, UPPERCASE_FIRST.
	 * 
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	function postProcessFunctions($content) {
		foreach ($this->functions as $function) {
			$pattern = '/!--###' . $function . '\((.+)\)###--/isU';

			if (preg_match_all($pattern, $content, $matches)) {

				$numberOfRecords = count($matches[0]);
				for($index = 0; $index < $numberOfRecords; $index ++) {
					switch ($function) {
						case 'LIMIT_TEXT':
							preg_match('/,([0-9]+)$/isU', $matches[1][$index], $limit);

							// Defines the length of the string that needs to be removed
							$stringLength = '-' . strlen(',' . $limit[1]);

							// Resets the real content, without the ",xx" a the end
							$matches[1][$index] = substr($matches[1][$index], 0, $stringLength);

							// Limits the text whenever is it necessary
							$_content = $this->limit_text($matches[1][$index], $limit[1]);
							$content = str_replace($matches[0][$index], $_content, $content);
							break;
						case 'UPPERCASE':
							$content = str_replace($matches[0][$index], strtoupper($matches[1][$index]), $content);
							break;
						case 'LOWERCASE':
							$content = str_replace($matches[0][$index], strtolower($matches[1][$index]), $content);
							break;
						case 'UPPERCASE_FIRST':
							$content = str_replace($matches[0][$index], ucfirst($matches[1][$index]), $content);
							break;
                    }
							
                }
			}
        }
		return $content;
    }

	/**
     * Usful method that shorten a text according to the parameter $limit.
     *
     * @param	string	$text: the input text
     * @param	int		$limit: the limit of words
     * @return	string	$text that has been shorten
     */
	protected function limit_text($text, $limit) {
		$text = strip_tags($text);
		$words = str_word_count($text, 2);
		$pos = array_keys($words);
		if (count($words) > $limit) {
			$text = substr($text, 0, $pos[$limit]) . ' ...';
		}
		return $text;
	}

	/**
	 * Recursive method. Gets the subpart template and substitutes content (label or field).
	 *
	 * @param array		$sdd
	 * @param string	$templateCode
	 * @return string	HTML code
	 */
	protected function getSubContent(&$sds, $templateCode){

		if (preg_match('/#{3}LOOP./',$templateCode)) {
			// Defines marker array according to $sds['name'] which contains a table name.
			// This marker is used to extract a subtemplate
			$this->markers[$sds['name']] = '###LOOP.' . $sds['name'] . '###';
			$this->counter[$sds['name']] = 0;

			// Defines subTemplateCode (template HTML) array according to $sds['name'] which contains a table name.
			$subTemplateCode = t3lib_parsehtml::getSubpart($templateCode, $this->markers[$sds['name']]);

			// If nothing is found, it means there are no LOOP defined for the this table name
			// This case can be faced whenever a template defined with the LOOP level2 without a LOOP level1
			// This code code could be compacted. However, for understanding the logic, is is clearer to let it like it.
			if ($subTemplateCode != '') {
				$this->subTemplateCode[$sds['name']] = $subTemplateCode;
			}
			else {
				$this->subTemplateCode[$sds['name']] = $templateCode;
			}
		}
		else {
			$this->subTemplateCode[$sds['name']] = $templateCode;
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

			// initialize data
			$this->localCObj->start($records);

			// ... and traverses the fields of the current record (associative array)
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

								$image = $this->localCObj->IMAGE($configuration);
								if (empty($image)) {
									$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = '<img src="'.t3lib_extMgm::extRelPath($this->extKey).'resources/images/missing_image.png'.'" class="templateDisplay_imageNotFound" alt="Image not found"/>';
								}
								else {
									$_fieldMarkers['###' . $key . '.' . $sds['name'] . '.' . $field . '###'] = $image;
								}
								break;
							case 'linkToDetail':
								$configuration = $this->datasource[$key]['configuration'];
								$configuration['useCacheHash'] = 1;
								if (!isset($configuration['returnLast'])) {
									$configuration['returnLast'] = 'url';
								}
								$additionalParams = '&' . $this->pObj->getPrefixId() . '[table]=' . $sds['name'] . '&' . $this->pObj->getPrefixId() .'[showUid]=' . $value;
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
			if (isset($this->counter[$sds['name']])) {
				$_temp['###COUNTER###'] = $this->counter[$sds['name']];
				$_fieldMarkers = array_merge($_fieldMarkers, $_temp);
			}

			// $_templateContent contains the temporary HTML. Whenever getSubContent() is called recursively, the content is passed to the method
			$_templateContent = t3lib_parsehtml::substituteMarkerArray($this->subTemplateCode[$sds['name']], $_fieldMarkers);
			$templateContent .= $_templateContent;
			# Debug:
			#echo $_templateContent;

			$this->counter[$sds['name']] = $this->counter[$sds['name']] + 1;
			# Debug:
			#echo $this->counter[$sds['name']];

			// If the records contains subtables, recursively calls getSubContent()
			// Else, removes a possible unwanted part <!-- ###LOOP.unsed ### begin -->.+<!-- ###LOOP.unsed ### end -->
			if (!empty($records['sds:subtables'])) {
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