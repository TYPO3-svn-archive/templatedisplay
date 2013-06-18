<?php
namespace Tesseract\Templatedisplay\Service;

/***************************************************************
*  Copyright notice
*
*  (c) 2013	Francois Suter (Cobweb) <typo3@cobweb.ch>
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

/**
 * Soft-reference parser for the template field of the 'templatedisplay' extension.
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_templatedisplay
 *
 * $Id: class.tx_templatedisplay.php 74477 2013-04-12 09:28:48Z francois $
 */
class SoftReferenceParser implements \TYPO3\CMS\Core\SingletonInterface {
	/**
	 * Parses the template field for a "file:xxx" pattern. If found it will try to  match it to a sys_file
	 * entry and return the relevant reference.
	 *
	 * @param string $table Database table name
	 * @param string $field Field name for which processing occurs
	 * @param integer $uid UID of the record
	 * @param string $content The content/value of the field
	 * @param string $spKey The softlink parser key. In this case, will always be "templatedisplay".
	 * @param array $parameters Parameters of the softlink parser (none, in this case).
	 * @param string $structurePath If running from inside a FlexForm structure, this is the path of the tag.
	 * @return array Result array on positive matches, see description in \TYPO3\CMS\Core\Database\SoftReferenceIndex. Otherwise FALSE.
	 * @see \TYPO3\CMS\Core\Database\SoftReferenceIndex
	 */
	public function findRef($table, $field, $uid, $content, $spKey, $parameters, $structurePath = '') {
		$elements = array();
		try {
			$elements[] = $this->parseForSysFile($content);
		}
		catch (\Exception $e) {
			// Nothing to do
		}

		// If at least one reference was found, return the list of references
		// Otherwise return false
		if (count($elements) > 0) {
			return array(
				'content' => $content,
				'elements' => $elements
			);
		} else {
			return FALSE;
		}
	}

	/**
	 * Searches the given content for a pattern like "file:xxx" to define a reference based on it.
	 *
	 * @param string $content The content to parse
	 * @return array Reference to the found element
	 * @throws \Exception
	 */
	protected function parseForSysFile($content) {
		// If the content starts with "FILE:" (or "file:"), we may have a file reference
		if (stripos($content, 'FILE:') === 0) {
			// Remove the "FILE:" key and cast the rest to int
			$sysFileId = intval(str_ireplace('FILE:', '' , $content));
			// If it's a positive number, prepare information for registering a reference
			if ($sysFileId > 0) {
				return array(
					'matchString' => $content,
					'subst' => array(
						'type' => 'db',
						'recordRef' => 'sys_file:' . $sysFileId
					)
				);
			}
		}
		throw new \Exception('No system file reference found', 1371471101);
	}
}

?>