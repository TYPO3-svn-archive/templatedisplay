<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Francois Suter (Cobweb) <typo3@cobweb.ch>
*  (c) 2008 Fabien Udriot <fabien.udriot@ecodev.ch>
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
 * @author	Fabien Udriot <fabien.udriot@ecodev.ch> sponsored by Cobweb
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay extends tx_basecontroller_consumerbase {


	public $extKey = 'templatedisplay';
	protected $conf;
	protected $table; // Name of the table where the details about the data display are stored
	protected $uid; // Primary key of the record to fetch for the details
	protected $structure = array(); // Input standardised data structure
	protected $result = ''; // The result of the processing by the Data Consumer
	protected $counter = array();

	protected $labelMarkers = array();
	protected $datasource = array();
	protected $LLkey = 'default';

	/**
	 * This method resets values for a number of properties
	 * This is necessary because services are managed as singletons
	 *
	 * @return	void
	 */
	public function reset(){
		$this->structure = array();
		$this->result = '';
		$this->uid = '';
		$this->table = '';
		$this->conf = array();
		$this->datasource = array();
		$this->LLkey = 'default';
    }
	
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
//		global $LANG;

		// Initializes local cObj
		$this->localCObj = t3lib_div::makeInstance('tslib_cObj');

		// Initializes LANG Object whether the object does not exist. (for example in the frontend)
//		if($LANG == null){
//
//			if (isset($GLOBALS['TSFE']->tmpl->setup['config.']['language'])) {
//				$languageCode = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
//			}
//
//			$LANG = t3lib_div::makeInstance('language');
//			$LANG->init('default');
//		}

		// ****************************************
		// ********** FETCHES DATASOURCE **********
        // ****************************************

		// Transforms the string from field mappings into a PHP array.
		// This array contains the mapping information btw a marker and a field.
		try {
			$datasource = json_decode($this->consumerData['mappings'],true);

			// Makes sure $datasource is an array
			if ($datasource === NULL) {
				$datasource = array();
			}
		}
		catch (Exception $e) {
			$this->result .= '<div style="color :red; font-weight: bold">JSON decoding problem for tx_templatedisplay_displays.uid = '.$this->uid . '.</div>';
			return false;
		}

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

			// Merges some data to create a new marker. Will look like: table.field
			$_marker = $data['table'] . '.' . $data['field'];

			// IMPORTANT NOTICE:
			// The idea is to make the field unique
			// Replaces the ###FIELD.xxx### by the value "table.field"
            // Ex: [###FIELD.title###] => ###FIELD.title.pages.title###
			$uniqueMarkers['###' . $data['marker'] . '###'] = '###' . $data['marker'] . '.' . $_marker . '###';

			// Builds the datasource as an associative array.
			// $data contains the complete record [marker], [table], [field], [type], [configuration]
			$this->datasource[$data['marker']] = $data;
		}

		// ***************************************
		// ********** BEGINS PROCESSING **********
        // ***************************************

		// LOCAL DOCUMENTATION:
		// $templateCode -> HTML template roughly extracted from the database
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

		if (isset($GLOBALS['_GET']['debug']['markers']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug('Content of $markers, line ' . __LINE__);
			t3lib_div::debug($markers);
		}

		if (isset($GLOBALS['_GET']['debug']['structure']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug('Content of $this->structure');
			t3lib_div::debug($this->structure);
		}

		// We want a convenient $templateCode. Substitutes $markers
		$templateCode = t3lib_parsehtml::substituteMarkerArray($templateCode, $markers);

		$templateStructure = $this->getTemplateStructure($templateCode);
		if (isset($GLOBALS['_GET']['debug']['template']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug($templateStructure);
		}

		// Transforms the templateStructure into real content
		$templateContent = $this->getContent($templateStructure);

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
				$numberOfMatches = count($matches[0]);
				for($index = 0; $index < $numberOfMatches; $index ++) {
					$markers[$matches[0][$index]] = $this->getValueFromArray($source, $matches[2][$index]);
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
	protected function getValueFromArray($source, $indices) {
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

				$numberOfMatches = count($matches[0]);
				for($index = 0; $index < $numberOfMatches; $index ++) {
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
     * Analyses the template code and build a structure of type array
     * This method is called recursively whenever a LOOP is found.
     *
     * Synopsis of the structure
     *
     * [table] => tableName
     * [template] => template code with markers
     * [content] => HTML code without <LOOP> marker (outer)
     * [loop] => contains the loop / subloops / subsubloops etc... (2 levels implemented in the datastructure
     *
     * @param	string	$template: template code with markers
     * @param	string	$content: template code without <LOOP> marker (outer)
     * @param	string	$tableName
     * @return	array	$templateStructure
     */
	protected function getTemplateStructure($template, $content = '', $tableName = '') {

		// Makes sure values are ok.
		if ($content == '') {
			$content = $template;
        }
		
		// Makes sure values are ok.
		if ($tableName == '') {
			$tableName = $this->structure['name'];
		}

		$templateStructure = array();
		$templateStructure['table'] = $tableName;
		$templateStructure['template'] = $template;
		$templateStructure['content'] = $content;
		$templateStructure['loops'] = array();

		// Pattern to match <!--LOOP(tt_content)-->.+<!--ENDLOOP-->
		if (preg_match_all('/<!-- *LOOP\((.+)\) *-->(.+)<!-- *ENDLOOP *-->/isU', $content, $matches, PREG_SET_ORDER)) {

			// Defines a new array

			// Traverses the array to find out table, template, content
			foreach ($matches as $match) {

				// Initialize variable name
				$subTemplate = $match[0];
				$subTable = $match[1];
				$subContent = $match[2];

				// This is a special case. True, means there is a loop in a loop...
                // ... and the $subTemplate, $subContent are not complete.
				if (preg_match('/<!-- *LOOP\(/isU', $subContent)) {

					// position of the template
					$position = strpos($template,$subTemplate);
					$remainingTemplate = substr($template, $position + strlen($subTemplate));

					// Matches the remaining HTML
					preg_match('/^(.+)<!-- *ENDLOOP *-->/isU', $remainingTemplate, $_match);
					$subTemplate .= $_match[0];
					$subContent .= '<!--ENDLOOP-->'.$_match[1];
                }

				// Gets recursively the template structure
				$templateStructure['loops'][] = $this->getTemplateStructure($subTemplate, $subContent, $subTable);
			}
        }
		
		return $templateStructure;
    }

	/**
     * Looks up for a value in a sds.
     *
     * @param	array	$sds: standard data structure
     * @param	int		$index: the position in the array
     * @param	string	$table: the name of the table
     * @param	string	$field: the name of the field
     * @return	string	$value: if no value is found return NULL
     */
	protected function getValueFromStructure(&$sds, $index, $table, $field) {

		// Default value is NULL
		$value = NULL;

		// TRUE, the best case, means the table is found at the first dimension of the sds
		if ($sds['name'] == $table) {
			if (isset($sds['records'][$index][$field])) {
				$value = $sds['records'][$index][$field];
            }
		}
		else {
			// Maybe the $sds contains subtables, have a look into it to find out the value.
			if (!empty($sds['records'][$index]['sds:subtables'])) {
				
				// Traverses all subSds and call it recursively
				foreach ($sds['records'][$index]['sds:subtables'] as $subSds){
					$value = $this->getValueFromStructure($subSds, 0, $table, $field);
					if ($value != NULL) {
						break;
                    }
                }
            }
        }
		return $value;
    }


	/**
     * Tries to find out a valid $sds according to a table name.
     *
     * @param	array	$sds
     * @param	string	$tableName
     * @return	array	$result: actually this is a sds
     */
	protected function getSubStructure(&$sds, $tableName) {
		$result = NULL;
		
		if ($sds['name'] == $tableName) {
			$result =& $sds;
		}
		else {
			foreach ($sds['records'][0]['sds:subtables'] as $subsds) {
				if ($subsds['name'] == $tableName) {
					$result =& $subsds;
					break;
				}
			}
		}

		if ($result === NULL) {
			// TODO improved error reporting
			throw new Exception('No sds found for table ' . $tableName);
		}
		return $result;
    }
	
	/**
     * Initializes language label and stores the lables for a possible further use.
     *
     * @param	$sds	$sds: standard data structure
     * @return	void
     */
	protected function setLabelMarkers(&$sds) {
		if (!isset($this->labelMarkers[$sds['name']]) && !empty($sds)) {

			// Defines as array
			$this->labelMarkers[$sds['name']] = array();
			foreach ($sds['header'] as $index => $labelArray) {
				$this->labelMarkers[$sds['name']]['###LABEL.' . $index . '###'] = $labelArray['label'];
			}
		}

    }
	
	/**
	 * Recursive method. Gets the subpart template and substitutes content (label or field).
	 *
	 * @param	string	$templateCode
     * @param	int		$deepth: the deepth of the array, begins with 0
	 * @return	string	HTML code
	 */
	protected function getContent(&$templateStructure){

		$this->setLabelMarkers($this->structure);

		// Stores content in an external array
		$content = $templateStructure['content'];
		
		// Means we need to handle the case "LOOP"
		if (!empty($templateStructure['loops'])) {

			
			// Loops around the template structure
			foreach ($templateStructure['loops'] as $subTemplateStructure) {

				// Resets temporary content
				$_content = '';

				// Tries to find out a valid $sds according to a table name ($subTemplateStructure[table])
				$sds = $this->getSubStructure($this->structure, $subTemplateStructure['table']);

				// Sets the label
				$this->setLabelMarkers($sds);
				
				// Retrieves the fields from the templateCode that needs a conversion
				// By the way catch the table name and the field name for futher use. -> "()"
				preg_match_all('/#{3}(FIELD\..+)\.(.+)\.(.+)#{3}/isU', $subTemplateStructure['content'], $subMarkers, PREG_SET_ORDER);

				// Loops around the records
				$numbersOfRecords = count($sds['records']);
				for($index = 0; $index < $numbersOfRecords; $index++) {
					
					// Increment counter
					$_counter['###COUNTER###'] = $index;
					
					// other possible syntax, useful with loop in loop
					$_counter['###COUNTER.' . $sds['name'] . '###'] = $index;
					
					// Initializes content object.
					$this->localCObj->start($sds['records'][$index]);
					
					// Defines default value in case no $fieldsMarkers are found
					$fieldMarkers = array();
					
					// Finds out the marker in the template.
					foreach ($subMarkers as $marker) {
						$markerName = $marker[0];
						$key = $marker[1];
						$table = $marker[2];
						$field = $marker[3];
						$value = $this->getValueFromStructure($sds, $index, $table, $field);

						// Ouch... difficult to explain
						// We are traversing a sds. The sds can be at the first dimension of $this->structure *OR* a the second dimension.
                        //
                        // We are in the "first" dimension:
						// this first case: we are in the first dimension, even is value is NULL replace it
                        //                  Anyway, templatedisplay will have *no* futher chance to translate the value.
                        //
                        // We are in a "second" dimension:
						// the second case: replace only the value you that are not NULL
						//                  templatedisplay will have futher chance to translate the value at the end of the function
						if ($this->structure['name'] == $subTemplateStructure['table']) {
							$fieldMarkers[$markerName] = $this->getValue($key ,$value);
                        }
						else if ($value !== NULL) {
							$fieldMarkers[$markerName] = $this->getValue($key ,$value);
                        }
					}

					// Defines a temporary variable
					$__content = $subTemplateStructure['content'];

					// Means there is a LOOP in a LOOP
					if (!empty($subTemplateStructure['loops'])) {
						foreach ($subTemplateStructure['loops'] as $subSubTemplateStructure) {

							// Reset value of $subSds
							$subSds = array();

							// Searches for the correct subsds
							foreach ($sds['records'][$index]['sds:subtables'] as $subSubStructure) {
								if ($subSubStructure['name'] == $subSubTemplateStructure['table']) {
									$subSds = $subSubStructure;
									break;
                                }
                            }

							// Defines the labels
							$this->setLabelMarkers($subSds);
							
							/**************/
							// Resets variable
							$subContent = '';
							preg_match_all('/#{3}(FIELD\..+)\.(.+)\.(.+)#{3}/isU', $subSubTemplateStructure['content'], $subSubMarkers, PREG_SET_ORDER);

							// Traverses subRecords
							$subNumbersOfRecords = count($subSds['records']);
							for($subIndex = 0; $subIndex < $subNumbersOfRecords; $subIndex++) {

								// Increments counter
								$_counter['###SUBCOUNTER###'] = $subIndex;

								// Other syntax. TODO: choose one syntax!
								$_counter['###COUNTER.' . $subSds['name'] . '###'] = $subIndex;

								// Initializes content object.
								$this->localCObj->start($sds['records'][$index]);
								
								// Defines default value in case no $subFieldMarkers are found
                                $_fieldMarkers = array();
								
								// Gets value
								foreach ($subSubMarkers as $marker) {
									$markerName = $marker[0];
									$key = $marker[1];
									$table = $marker[2];
									$field = $marker[3];
									$value = $this->getValueFromStructure($subSds, $subIndex, $table, $field);
									if ($value !== NULL) {
										$_fieldMarkers[$markerName] = $this->getValue($key ,$value);
									}
								}
								$subFieldMarkers = array_merge($fieldMarkers, $_fieldMarkers, $this->labelMarkers[$subSds['name']], $_counter);
								$subContent .= t3lib_parsehtml::substituteMarkerArray($subSubTemplateStructure['content'], $subFieldMarkers);
							}
							/**************/
							
							// Replaces original sub template by the new content
							$__content = str_replace($subSubTemplateStructure['template'], trim($subContent), $__content);
                        }
                    }

					// Merges array
					$fieldMarkers = array_merge($fieldMarkers, $this->labelMarkers[$sds['name']], $_counter);
					
					// Substitues content
					$_content .= t3lib_parsehtml::substituteMarkerArray($__content, $fieldMarkers);
					
                } // end for (records)

				$content = str_replace($subTemplateStructure['template'], trim($_content), $content);
            } // foreach $templateStructure['loops']
			
		}

		// The template dimension 1
		preg_match_all('/#{3}(FIELD\..+)\.(.+)\.(.+)#{3}/isU', $content, $markers, PREG_SET_ORDER);

		$fieldMarkers = array();

		foreach ($markers as $marker) {
			$markerName = $marker[0];
			$key = $marker[1];
			$table = $marker[2];
			$field = $marker[3];
			$value = $this->getValueFromStructure($this->structure, 0, $table, $field);
			if ($value !== NULL) {
				$fieldMarkers[$markerName] = $this->getValue($key ,$value);
			}
		}

		// Merges additional fields
		$fieldMarkers = array_merge($fieldMarkers, $this->labelMarkers[$this->structure['name']]);
		return t3lib_parsehtml::substituteMarkerArray($content, $fieldMarkers);

    }

	protected function getValue($key,$value) {
		
		switch ($this->datasource[$key]['type']) {
			case 'text':
				$configuration = $this->datasource[$key]['configuration'];
				$configuration['value'] = $value;
				$output = $this->localCObj->TEXT($configuration);
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
					// TODO: in production mode, nothing should be displayed. "templateDisplay_imageNotFound"
					$output = '<img src="'.t3lib_extMgm::extRelPath($this->extKey).'resources/images/missing_image.png'.'" class="templateDisplay_imageNotFound" alt="Image not found"/>';
				}
				else {
					$output = $image;
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
				$output = $this->localCObj->typolink('',$configuration);
				break;
			case 'linkToPage':
				$configuration = $this->datasource[$key]['configuration'];
				$configuration['useCacheHash'] = 1;
				if (!isset($configuration['returnLast'])) {
					$configuration['returnLast'] = 'url';
				}
				$configuration['additionalParams'] = $additionalParams . $this->localCObj->stdWrap($configuration['additionalParams'], $configuration['additionalParams.']);

				// Generates the link
				$output = $this->localCObj->typolink('',$configuration);
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
				$output = $this->localCObj->typolink('',$configuration);
				break;
			case 'email':
				$configuration = $this->datasource[$key]['configuration'];
				if (!isset($configuration['parameter'])) {
					$configuration['parameter'] = $value;
				}
				// Generates the email
				$output = $this->localCObj->typolink('',$configuration);
				break;
		} // end switch
		
		return $output;
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
		if (preg_match_all('/#{3}(LLL:.+)#{3}/isU', $content, $matches, PREG_SET_ORDER)) {
			foreach($matches as $marker){
				$markers[$marker[0]] = $GLOBALS['TSFE']->sL($marker[1]);
			}
		}
//		if (preg_match_all('/#{3}L{0,3}:*(EXT:(.+)\/.+):(.+)#{3}/isU', $content, $matches, PREG_SET_ORDER)) {
//			foreach($matches as $marker){
//				$fileReference = $marker[1];
//				$extensionKey = $marker[2];
//				$LLLMarker = $marker[3];
//				$this->loadLocalLang($extensionKey, $fileReference);
//				$markers[$marker[0]] = $this->getLocalLang($extensionKey, $LLLMarker);
//			}
//		}
		return $markers;
	}

	/**
	 * Loads local-language values by looking for a "locallang.php" file in the plugin class directory ($this->scriptRelPath) and if found includes it.
	 * Also locallang values set in the TypoScript property "_LOCAL_LANG" are merged onto the values found in the "locallang.php" file.
	 *
     * @param	string	$extensionKey: the extension key
     * @param	string	$extensionKey: the extension key
     * @author	Fabien Udriot
     * @author	Kasper Skårhøj
	 * @return	void
	 */
	protected function loadLocalLang($extensionKey, $fileReference) {
		if ($GLOBALS['TSFE']->config['config']['language']) {
			$this->LLkey = $GLOBALS['TSFE']->config['config']['language'];
			if ($GLOBALS['TSFE']->config['config']['language_alt']) {
				$this->altLLkey = $GLOBALS['TSFE']->config['config']['language_alt'];
			}
		}
		
		$basePath = t3lib_div::getFileAbsFileName($fileReference);

		if (!is_readable($basePath) && isset($GLOBALS['_GET']['debug']['markers']) && $GLOBALS['BE_USER']) {
			echo t3lib_div::debug('Warning: language file does not exist for ' . $fileReference);
		}

		if(!$this->LOCAL_LANG_loaded[$extensionKey]){
			// php or xml as source: In any case the charset will be that of the system language.
			// However, this function guarantees only return output for default language plus the specified language (which is different from how 3.7.0 dealt with it)
			$this->LOCAL_LANG[$extensionKey] = t3lib_div::readLLfile($basePath,$this->LLkey);
			if ($this->altLLkey) {
				$tempLOCAL_LANG = t3lib_div::readLLfile($basePath, $this->altLLkey);
				$this->LOCAL_LANG[$extensionKey] = array_merge(is_array($this->LOCAL_LANG[$extensionKey]) ? $this->LOCAL_LANG[$extensionKey] : array(), $tempLOCAL_LANG);
			}

			// Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
			// TODO: this portion of code need to be tested. In the state of art, it won't work.
			// Actually, _LOCAL_LANG is not transmitted to templatedisplay but needs to be extracted from the global TypoScript
			if (is_array($this->conf['_LOCAL_LANG.'])) {
				reset($this->conf['_LOCAL_LANG.']);
				while(list($k,$lA) = each($this->conf['_LOCAL_LANG.']))   {
					if (is_array($lA))      {
						$k = substr($k,0,-1);
						foreach($lA as $llK => $llV)    {
							if (!is_array($llV))    {
								$this->LOCAL_LANG[$extension][$k][$llK] = $llV;
								if ($k != 'default')    {
									$this->LOCAL_LANG_charset[$k][$llK] = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];        // For labels coming from the TypoScript (database) the charset is assumed to be "forceCharset" and if that is not set, assumed to be that of the individual system languages (thus no conversion)
								}
							}
						}
					}
				}
			}
			$this->LOCAL_LANG_loaded[$extensionKey] = 1;
		}
    }

	/**
	 * Returns the localized label of the LOCAL_LANG key, $key
	 * Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
	 *
	 * @param	string		The key from the LOCAL_LANG array for which to return the value.
	 * @param	string		Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
	 * @param	boolean		If true, the output label is passed through htmlspecialchars()
	 * @return	string		The value from LOCAL_LANG.
     * @author	Fabien Udriot
     * @author	Kasper Skårhøj
	 */
	function getLocalLang($extension, $key,$alt='',$hsc=FALSE)	{
		// The "from" charset of csConv() is only set for strings from TypoScript via _LOCAL_LANG
		if (isset($this->LOCAL_LANG[$extension][$this->LLkey][$key])) {
			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$extension][$this->LLkey][$key], $this->LOCAL_LANG_charset[$extension][$this->LLkey][$key]);
		} elseif ($this->altLLkey && isset($this->LOCAL_LANG[$extension][$this->altLLkey][$key]))	{
			$word = $GLOBALS['TSFE']->csConv($this->LOCAL_LANG[$extension][$this->altLLkey][$key], $this->LOCAL_LANG_charset[$extension][$this->altLLkey][$key]);
		} elseif (isset($this->LOCAL_LANG[$extension]['default'][$key])) {
			$word = $this->LOCAL_LANG[$extension]['default'][$key];	// No charset conversion because default is english and thereby ASCII
		} else {
			$word = $this->LLtestPrefixAlt.$alt;
		}
		// TODO:
		#var_dump($this->LOCAL_LANG_charset);

		//$output = $this->LLtestPrefix.$word;
		//if ($hsc) {
		//	$output = htmlspecialchars($output);
        //}

		return $word;
	}	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']){
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']);
}

?>
