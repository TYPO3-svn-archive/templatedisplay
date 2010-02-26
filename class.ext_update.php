<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
***************************************************************/

/**
 * Class for updating the display controller
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_templatedisplay
 *
 * $Id$
 */
class ext_update {

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string	HTML to display
	 */
	function main() {
			// Get all templatedisplay records
		$templates = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid, title, template', 'tx_templatedisplay_displays', '', '', '', '', 'uid');
		$wrongExpressions = array();
		foreach ($templates as $uid => $templateRecord) {
			$html = $templateRecord['template'];
				// Loads the template file
			if (preg_match('/^FILE:/isU', $html)) {
				$filePath = str_replace('FILE:', '' , $html);
				$filePath = t3lib_div::getFileAbsFileName($filePath);
				if (is_file($filePath)) {
					$html = file_get_contents($filePath);
				}
			}
			$matches = array();
			if (preg_match_all('/#{3}EXPRESSION:\.(.+)#{3}/isU', $html, $matches, PREG_SET_ORDER)) {
				if (count($matches) > 0) {
					$wrongExpressions[] = $uid;
				}
			}
		}
		$content = '<h2>Checking for wrong EXPRESSION markers</h2>';
		if (count($wrongExpressions) > 0) {
			$content .= '<p>Wrong EXPRESSION markers have been found in the following templates:</p>';
			$content .= '<ul>';
			foreach ($wrongExpressions as $uid) {
				$content .= '<li>' . $templates[$uid]['title'] . ' [' . $uid . ']' . '</li>';
			}
			$content .= '</ul>';
			$content .= '<p>Wrong EXPRESSION markers use a colon and a dot instead of just a dot after &quot;EXPRESSION&quot;.</p>';
			$content .= '<p>Wrong: <em>###EXPRESSION:.foo|bar###</em>. Correct: <em>###EXPRESSION.foo|bar###</em>';
		} else {
			$content .= '<p>No wrong EXPRESSION markers were found.</p>';
		}
		return $content;
	}

	/**
	 * This method checks whether it is necessary to display the UPDATE option at all
	 *
	 * @param	string	$what: What should be updated
	 */
	function access($what = 'all') {
		return TRUE;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.ext_update.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.ext_update.php']);
}
?>
