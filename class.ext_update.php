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
	var $deprecatedMarkers = array('GP', 'TSFE', 'VARS', 'PLUGIN', 'page');

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string	HTML to display
	 */
	function main() {
		$content = '';
		$update = t3lib_div::_GP('update');
		if ($update == 'wrongExpressions') {
			// Not handled for now
		} elseif ($update == 'deprecatedMarkers') {
				// Update deprecated markers
			$idList = t3lib_div::_GP('uids');
			$replacements = 0;
			if (!empty($idList)) {
				$templates = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid, title, template', 'tx_templatedisplay_displays', 'uid IN (' . $idList . ')', '', '', '', 'uid');
				foreach ($templates as $uid => $templateRecord) {
					$htmlCode = $templateRecord['template'];
						// If it's a file reference, don't handle it
					if (preg_match('/^FILE:/isU', $htmlCode)) {
						continue;
					} else {
						foreach ($this->deprecatedMarkers as $marker) {
							$pattern = '/#{3}(' . $marker . ':)(.+)#{3}/isU';
							$matches = array();
							if (preg_match_all($pattern, $htmlCode, $matches, PREG_SET_ORDER)) {
								if (count($matches) > 0) {
									foreach ($matches as $matchInfo) {
										$oldExpression = $matchInfo[0];
										$newExpression = '###EXPRESSION.' . $matchInfo[1] . $matchInfo[2] . '###';
										$htmlCode = str_replace($oldExpression, $newExpression, $htmlCode);
									}
									$fields = array();
									$fields['template'] = $htmlCode;
									$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templatedisplay_displays', 'uid = ' . $uid, $fields);
									if ($res) {
										$replacements++;
									}
								}
							}
						}
					}
				}
			}
			$content .= '<p>Number of updates performed: ' . $replacements . '</p>';
		}

		$html = array();
		$isInFile = array();
			// Get all templatedisplay records
		$templates = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid, title, template', 'tx_templatedisplay_displays', '', '', '', '', 'uid');
		$wrongExpressions = array();
		foreach ($templates as $uid => $templateRecord) {
			$html[$uid] = $templateRecord['template'];
				// Loads the template file
			if (preg_match('/^FILE:/isU', $html[$uid])) {
				$filePath = str_replace('FILE:', '', $html[$uid]);
				$filePath = t3lib_div::getFileAbsFileName($filePath);
				if (is_file($filePath)) {
					$html[$uid] = file_get_contents($filePath);
				}
				$isInFile[$uid] = TRUE;
			} else {
				$isInFile[$uid] = FALSE;
			}
			$matches = array();
			if (preg_match_all('/#{3}EXPRESSION:\.(.+)#{3}/isU', $html[$uid], $matches, PREG_SET_ORDER)) {
				if (count($matches) > 0) {
					$wrongExpressions[] = $uid;
				}
			}
		}
			// Check for existing EXPRESSION markes with wrong syntax
		$content .= '<h2>Checking for wrong EXPRESSION markers</h2>';
		if (count($wrongExpressions) > 0) {
			$content .= '<p>Wrong EXPRESSION markers have been found in the following templates:</p>';
			$content .= '<ul>';
			foreach ($wrongExpressions as $uid) {
				$content .= '<li>' . $templates[$uid]['title'] . ' [' . $uid . ']' . '</li>';
			}
			$content .= '</ul>';
			$content .= '<p>Wrong EXPRESSION markers use a colon and a dot instead of just a dot after &quot;EXPRESSION&quot;.</p>';
			$content .= '<p>Wrong: <em>###EXPRESSION:.foo:bar###</em>. Correct: <em>###EXPRESSION.foo:bar###</em>';
		} else {
			$content .= '<p>No wrong EXPRESSION markers were found.</p>';
		}
			 // Loop again on all templates to find deprecated markers
		$content .= '<h2>Checking for deprecated markers</h2>';
		$list = '';
		$possibleChanges = 0;
		$changeableUids = array();
		foreach ($this->deprecatedMarkers as $marker) {
			$pattern = '/#{3}(' . $marker . ':)(.+)#{3}/isU';
			foreach ($html as $uid => $htmlCode) {
				$matches = array();
				if (preg_match_all($pattern, $htmlCode, $matches, PREG_SET_ORDER)) {
					if (count($matches) > 0) {
						$displayMatches = '';
						foreach ($matches as $matchInfo) {
							if (!empty($displayMatches)) {
								$displayMatches .= ', ';
							}
							$displayMatches .= $matchInfo[0];
						}
						$list .= '<li>In item: ' . $templates[$uid]['title'] . ' [' . $uid . ']: ' . $displayMatches . '</li>';
						if (!$isInFile[$uid]) {
							$possibleChanges++;
							$changeableUids[] = $uid;
						}
					}
				}
			}
		}
		if (empty($list)) {
			$content .= '<p>No deprecated markers were found.</p>';
		} else {
			$content .= '<ul>' . $list . '</ul>';
			if ($possibleChanges > 0) {
				$content .= '<p>' . $possibleChanges . ' happen in records (and not in files), so they can be automatically modified. Click the update button below to change them.</p>';
				if (empty($update)) {
					$content .= '<form name="updateForm" action="" method ="post">';
					$content .= '<input type="hidden" name="update" value ="deprecatedMarkers">';
					$content .= '<input type="hidden" name="uids" value ="' . implode(',', $changeableUids) . '">';
					$content .= '<p><input type="submit" name="submitButton" value ="Update"></p>';
					$content .= '</form>';
				}
			}
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
