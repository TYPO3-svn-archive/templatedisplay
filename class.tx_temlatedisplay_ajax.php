<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Fabien Udriot <fabien.udriot@ecodev.ch>
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


/**
 * This class answers to AJAX calls from the 'templatedisplay' extension
 *
 * @author	Fabien Udriot <fabien.udriot@ecodev.ch>
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay_ajax {

	/**
	 * This method answers to the AJAX call and saves the mappings configuration
	 *
	 * @param	array	$params
	 * @param	Object	$ajaxObj
	 * @return	void	(with 4.2)
	 */
	public function saveConfiguration($params, $ajaxObj) {
		$uid = t3lib_div::_GP('uid');
		$mappings = t3lib_div::_GP('mappings');
		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', 'tx_templatedisplay_displays', 'uid = '. $uid);
		
		$result = 0;
		
		if (!empty($record)) {
			$updateArray['mappings'] = $mappings;
			$msg = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templatedisplay_displays', 'uid = '. $uid, $updateArray);
			
			if ($msg == 1) {
				$result = 1;
			}
		}
		$ajaxObj->addContent('templatedisplay', $result);
	}

	/**
	 * This method answers to the AJAX call and saves the template
	 *
	 * @param	array	$params
	 * @param	Object	$ajaxObj
	 * @return	void	(with 4.2)
	 */
	public function saveTemplate($params, $ajaxObj) {
		$uid = t3lib_div::_GP('uid');
		$template = trim(t3lib_div::_GP('template'));
		$record = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid','tx_templatedisplay_displays','uid = '. $uid);

		$result = 0;
		$tceforms = t3lib_div::makeInstance('tx_templatedisplay_tceforms');
		
		if (!empty($record)) {
			# Replaces tabulations by spaces. It takes less room on the screen
			$template = str_replace('	', '  ',$template);
			$updateArray['template'] = $template;
			$msg = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templatedisplay_displays', 'uid = '. $uid, $updateArray);

			if ($msg == 1) {
			
                if (preg_match('/^FILE:/isU', $template)) {
                
                	$filePath = str_replace('FILE:', '' , $template);
                	$filePath = t3lib_div::getFileAbsFileName($filePath);
                	
                	if (is_file($filePath)) {
	                	$template = file_get_contents($filePath);
						$template = str_replace('	', '  ', $template);
                	}
                	else {
                		global $LANG;
	                	$template = $LANG->sL('LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.fileNotFound') . ' ' . $template;
                	}
                }
				
				$result = $tceforms->transformTemplateContent($template);
			}
		}
		$ajaxObj->addContent('templatedisplay', $result);
	}
}
?>