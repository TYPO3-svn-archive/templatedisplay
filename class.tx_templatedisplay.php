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
* $Id$
***************************************************************/

require_once(t3lib_extMgm::extPath('basecontroller', 'services/class.tx_basecontroller_feconsumerbase.php'));

/**
 * Plugin 'Data Displayer' for the 'templatedisplay' extension.
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @author	Fabien Udriot <fabien.udriot@ecodev.ch> sponsored by Cobweb
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay extends tx_basecontroller_feconsumerbase {

	public $tsKey = 'tx_templatedisplay';
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
	 * @var	array	$functions: list of function handled by templatedisplay 'LIMIT', 'UPPERCASE', 'LOWERCASE', 'UPPERCASE_FIRST
	 */
	protected $functions = array('LIMIT', 'UPPERCASE', 'LOWERCASE', 'UPPERCASE_FIRST');
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

		// Initializes local cObj
		$this->localCObj = t3lib_div::makeInstance('tslib_cObj');
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

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

		// Formats TypoScript configuration as array.
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
			// The idea is to make the field unique and to be able to know which field of the database is associated
			// Adds to ###FIELD.xxx### the value "table.field"
			// Ex: [###FIELD.title###] => ###FIELD.title.pages.title###
			$uniqueMarkers['###' . $data['marker'] . '###'] = '###' . $data['marker'] . '.' . $_marker . '###';

			// Builds the datasource as an associative array.
			// $data contains the following information: [marker], [table], [field], [type], [configuration]
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

		// Begins $templateCode transformation.
		// *Must* be at the beginning of startProcess()
		$templateCode = $this->preProcessIf($templateCode);
		$templateCode = $this->preProcessFunctions($templateCode);
		$templateCode = $this->processLoop($templateCode); // Adds a LOOP marker of first level, if it does not exist.

		// Handles possible marker: ###LLL:EXT:myextension/localang.xml:myLable###, ###GP:###, ###TSFE:### etc...
		$LLLMarkers = $this->getLLLMarkers($templateCode);
		$GPMarkers = $this->getExpressionMarkers('GP', array_merge(t3lib_div::_GET(), t3lib_div::_POST()), $templateCode);
		$TSFEMarkers = $this->getExpressionMarkers('TSFE', $GLOBALS['TSFE'], $templateCode);
		$pageMarkers = $this->getExpressionMarkers('page', $GLOBALS['TSFE']->page, $templateCode);
		$globalVariablesMarkers = $this->getGlobalVariablesMarkers($templateCode); // Global template variable can be ###TOTAL_OF_RECORDS### ###SUBTOTAL_OF_RECORDS###

		// Merges array, in order to have only one array (performance!)
		$markers = array_merge($uniqueMarkers, $LLLMarkers, $GPMarkers, $TSFEMarkers, $pageMarkers, $globalVariablesMarkers);

		// First transformation of $templateCode. Substitutes $markers that can be already substituted. (LLL, GP, TSFE, etc...)
		$templateCode = t3lib_parsehtml::substituteMarkerArray($templateCode, $markers);

		// Cuts out the template into different part and organizes it in an array.
		$templateStructure = $this->getTemplateStructure($templateCode);

		/* Debug */
		$this->debug($markers,$templateStructure);

		// Transforms the templateStructure[template] into real content
		$templateContent = $templateCode;
		foreach ($templateStructure as &$_templateStructure) {
			$_content = $this->getContent($_templateStructure, $this->structure);
			$templateContent = str_replace($_templateStructure['template'], $_content, $templateContent);
		}

		// Translate ramaining labels
		$templateContent = t3lib_parsehtml::substituteMarkerArray($templateContent,$this->getLabelMarkers($this->structure['name']));

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
	 * Adds a LOOP marker of first level, if it does not exist and close according to the table name.
	 * E.g. <!--ENDLOOP--> becomes <!--ENDLOOP(tablename)-->
	 * This additionnal information allows a better cuting out of the template.
	 *
	 * @param	string	$content HTML code
	 * @return	string	$content transformed HTML code
	 */
	protected function processLoop($content) {

		// Matches the LOOP(table) with offset
		if (preg_match_all('/<!-- *LOOP *\((.+)\) *-->/isU', $content, $loopMatches, PREG_OFFSET_CAPTURE)) {
			preg_match_all('/<!-- *ENDLOOP *-->/isU', $content, $endLoopMatches, PREG_OFFSET_CAPTURE);

			// Traverses the array. Begins at the end
			$numberOfMatches = count($loopMatches[0]);
			for ($index = ($numberOfMatches - 1); $index >= 0; $index--) {
				$table = $loopMatches[1][$index][0];
				$offset = $loopMatches[1][$index][1];

				// Loops around the ENDLOOP.
				// Checks the value offset. The first bigger is the good one. -> remembers the table name.
				for ($index2 = 0; $index2 < $numberOfMatches; $index2++) {
					$_offset = $endLoopMatches[0][$index2][1];
					if($_offset > $offset && !isset($endLoopMatches[0][$index2][2])) {
						$endLoopMatches[0][$index2][2] = $table;
						break;
					}
				} // end for ENDLOOP
			} // end for LOOP

			// Builds replacement array
			for ($index = 0; $index < $numberOfMatches; $index ++) {
				$patterns[$index] = '/<!-- *ENDLOOP *-->/isU';
				$replacements[$index] = '<!--ENDLOOP(' . $endLoopMatches[0][$index][2] . ')-->';
			}
			// Replacement with limit 1
			$content = preg_replace($patterns, $replacements, $content, 1);
		}

		// Wraps if LOOP
		if (!preg_match('/<!-- *LOOP\(' . $this->structure['name'] . '\)/isU', $content, $matches)) {
			$content = '<!--LOOP(' . $this->structure['name'] . ')-->' . chr(10) . $content . chr(10) . '<!--ENDLOOP(' . $this->structure['name'] . ')-->';
		}
		return $content;
	}

	/**
	 * Pre processes the template function LIMIT, UPPERCASE, LOWERCASE, UPPERCASE_FIRST.
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
	 * Handles the function: LIMIT, UPPERCASE, LOWERCASE, UPPERCASE_FIRST.
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
						case 'LIMIT':
							preg_match('/,([0-9]+)$/isU', $matches[1][$index], $limit);

							// Defines the length of the string that needs to be removed
							$stringLength = '-' . strlen(',' . $limit[1]);

							// Resets the real content, without the ",xx" a the end
							$matches[1][$index] = substr($matches[1][$index], 0, $stringLength);

							// Limits the text whenever is it necessary
							$_content = $this->limit($matches[1][$index], $limit[1]);
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
	protected function limit($text, $limit) {
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
	 * [table]		=>	(string) tableName
	 * [template]	=>	(string) template code with markers
	 * [content]	=>	(string) HTML code without <LOOP> marker (outer)
	 * [emptyLoops]	=>	(array) Contains the value if loops is empty.
	 * [loops]		=>	(array) Contains a templateStructure array [table], [template], [content], [emptyLoops], [loops]
	 *
	 * @param	string	$template: template code with markers
	 * @param	string	$content: template code without <LOOP> marker (outer)
	 * @return	array	$templateStructure: multidemensionl array
	 */
	protected function getTemplateStructure($template, $content = '') {
		// Defines a value for $content
		if ($content == '') {
			$content = $template;
		}

		// Default value
		$templateStructure = array();

		if (preg_match_all('/<!-- *LOOP\((.+)\) *-->(.+)<!-- *ENDLOOP\(\1\) *-->/isU', $content, $matches, PREG_SET_ORDER)) {

			$numberOfMatches = count($matches);

			// Traverses the array to find out table, template, content
			for ($index = 0; $index < $numberOfMatches; $index++) {

				// Initialize variable name
				$_template = $matches[$index][0];
				$_table = $matches[$index][1];
				$_content = $matches[$index][2];

				$templateStructure[$index] = array();
				$templateStructure[$index]['table'] = $_table;
				$templateStructure[$index]['template'] = $_template;
				$templateStructure[$index]['content'] = trim($_content);
				// Gets recursively the template structure
				$templateStructure[$index]['loops'] = $this->getTemplateStructure($_template, trim($_content));

				// Searches for EMPTY $value
				foreach ($templateStructure[$index]['loops'] as &$loopContent) {

					// Handles the case when special content must substitue empty LOOP instead of nothing.
					if (preg_match('/<!-- *EMPTY *-->(.+)<!-- *ENDEMPTY *-->/isU', $loopContent['content'], $_match)) {

						$emptyTemplate = $_match[0];
						$_emptyContent = $_match[1];

						// Replaces final content
						$loopContent['content'] = trim(str_replace($emptyTemplate, '', $loopContent['content']));
					}
					else {
						$_emptyContent = '';
					}

					$templateStructure[$index]['emptyLoops'][] = trim($_emptyContent);
				}
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
	 * Initializes language label and stores the lables for a possible further use.
	 *
	 * @param	$sds	$sds: standard data structure
	 * @return	void
	 */
	protected function setLabelMarkers(&$sds) {
		if (!isset($this->labelMarkers[$sds['name']]) && !empty($sds['header'])) {

			// Defines as array
			$this->labelMarkers[$sds['name']] = array();
			foreach ($sds['header'] as $index => $labelArray) {
				$this->labelMarkers[$sds['name']]['###LABEL.' . $index . '###'] = $labelArray['label'];
			}
		}
	}

	/**
	 * Returns an array that contains LABEL
	 *
	 * @param	string	$name: corresponds to a table name.
	 * @return	array	$markers
	 */
	protected function getLabelMarkers($name) {
		$markers = array();

		if (isset($this->labelMarkers[$name])) {
			$markers = $this->labelMarkers[$name];
		}
		return $markers;
	}


	/**
	 * Gets the subpart template and substitutes content (label or field).
	 *
	 * @param	array	$templateStructure
	 * @param	array	$sds: standard data structure
	 * @return	string	$content: HTML content
	 */
	protected function getContent($templateStructure, &$sds, $pRecords = array(), $fieldMarkers = array()){

		// Intializes the label (header part of sds).
		$this->setLabelMarkers($sds);

		// Resets temporary content
		$content = '';

		// Retrieves the fields from the templateCode that needs a substitution
		// By the way catch the table name and the field name for futher use. -> "()"
		preg_match_all('/#{3}(FIELD\..+)\.(.+)\.(.+)#{3}/isU', $templateStructure['content'], $markers, PREG_SET_ORDER);

		// TRAVERSES RECORDS
		$numbersOfRecords = count($sds['records']);
		for($index = 0; $index < $numbersOfRecords; $index++) {

			$_content = $templateStructure['content'];

			// Initializes content object.
			$this->localCObj->start($sds['records'][$index]);

			// Loads a register
			foreach ($pRecords as $key => $value) {
				if (strpos($key, 'sds:') === FALSE) {
					$registerKey = 'parent.'.$key;
					$GLOBALS['TSFE']->register[$registerKey] = $value;
				}
			}

			// TRAVERSES MARKERS
			foreach ($markers as $marker) {
				$markerName = $marker[0];
				$key = $marker[1];
				$table = $marker[2];
				$field = $marker[3];
				$value = $this->getValueFromStructure($sds, $index, $table, $field);

				#if ($value !== NULL) {
				$fieldMarkers[$markerName] = $this->getValue($key ,$value, $sds);
				#}
			}

			// Means there is a LOOP in a LOOP
			if (!empty($templateStructure['loops'])) {

				// TRAVERSES (SUB) TEMPLATE STRUCTURE
				$loop = 0;
				foreach ($templateStructure['loops'] as &$subTemplateStructure) {

					$__content = '';

					// Searches for the correct subsds
					if (!empty($sds['records'][$index]['sds:subtables'])) {
						foreach ($sds['records'][$index]['sds:subtables'] as &$subSds) {
							if ($subSds['name'] == $subTemplateStructure['table']) {
								$__content = $this->getContent($subTemplateStructure, $subSds, $sds['records'][$index], $fieldMarkers);
								$_content = str_replace($subTemplateStructure['template'], $__content, $_content);
								break;
							}
						} // end foreach records structure
					}
					else {

						// Handles the case when there is no record -> replace with other content
						$fieldMarkers = array_merge($fieldMarkers, $this->getLabelMarkers($sds['name']), array('###SUBCOUNTER###' => '0'));
						$__content = $this->getEmptyValue($sds, $subTemplateStructure, $loop, $fieldMarkers);
						$_content = str_replace($subTemplateStructure['template'], $__content, $_content);
					} // end else
					$loop ++;
				} // end foreach template structure
			} // end if

			// Defines wheter we are in the first level or other...
			if ($this->structure['name'] == $sds['name']) {
				$counterName = 'COUNTER';
			}
			else {
				$counterName = 'SUBCOUNTER';
			}

			// Increment counter + Merges array(FIELD, LABEL, COUNTER)
			$fieldMarkers = array_merge($fieldMarkers, $this->getLabelMarkers($sds['name']), array('###' . $counterName . '###' => $index));

			// Substitues content
			$content .= t3lib_parsehtml::substituteMarkerArray($_content, $fieldMarkers);

		} // end for (records)

		return $content;
	}

	/**
	 *
	 * @param	array	$sds: standard data structure
	 * @param	array	$templateStructure
	 * @param	int		$index
	 * @param	array	$markers
	 * @return	string
	 */
	protected function getEmptyValue(&$sds, &$templateStructure, $index, $markers) {
		$content = '';
		if ($templateStructure['emptyLoops'][$index] != '') {
			$content = $templateStructure['emptyLoops'][$index];
		}
		else {
			// Checks the configuration
			$this->conf += array('parseEmptyLoops' => 0);
			$parseEmptyLoops = $this->conf['parseEmptyLoops'];
			if ((boolean) $parseEmptyLoops) {
				$content = t3lib_parsehtml::substituteMarkerArray($templateStructure['content'], $markers);

				// Removes remaining ###FIELD###
				$content = preg_replace('/#{3}FIELD.+#{3}/isU','',$content);
			}
		}
		return $content;
	}

	/**
	 * Important method! Formats the $value given as input according to the $key.
	 * The variable $key will tell the type of $value. Then format the $value whenever there is TypoScript configuration.
	 *
	 * @param	string	$key
	 * @param	string	$value
	 * @return	string
	 */
	protected function getValue($key, $value, &$sds) {

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

				// Defines parameter
				if (!isset($configuration['parameter'])) {
					$configuration['parameter'] = $value;
				}

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
		return $markers;
	}

	protected function debug($markers, $templateStructure) {
		if (isset($GLOBALS['_GET']['debug']['markers']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug($markers);
		}

		if (isset($GLOBALS['_GET']['debug']['template']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug($templateStructure);
		}

		if (isset($GLOBALS['_GET']['debug']['structure']) && $GLOBALS['BE_USER']) {
			t3lib_div::debug($this->structure);
		}

		if ($this->configuration['debug'] || TYPO3_DLOG) {
			t3lib_div::devLog('Markers: "' . $this->consumerData['title'] . '"', $this->extKey, -1, $markers);
			t3lib_div::devLog('Template structure: "' . $this->consumerData['title'] . '"', $this->extKey, -1, $templateStructure);
			t3lib_div::devLog('Data structure: ' . $this->pObj->cObj->data['header'] . '"', $this->extKey, -1, $this->structure);
		}

		if ($this->consumerData['debug_markers'] && !$this->configuration['debug']) {
			t3lib_div::devLog('Markers: "' . $this->consumerData['title'] . '"', $this->extKey, -1, $markers);
		}

		if ($this->consumerData['debug_markers'] && !$this->configuration['debug']) {
			t3lib_div::devLog('Template structure: "' . $this->consumerData['title'] . '"', $this->extKey, -1, $templateStructure);
		}

		if ($this->consumerData['debug_markers'] && !$this->configuration['debug']) {
			t3lib_div::devLog('Data structure: ' . $this->pObj->cObj->data['header'] . '"', $this->extKey, -1, $this->structure);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']){
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay.php']);
}

?>
