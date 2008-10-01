<?php
/***************************************************************
*  Copyright notice
*  
*  (c)  2008 Fabien Udriot <fabien.udriot@ecodev.ch>
* 
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
*
*  $Id$
***************************************************************/

/**
 * Add a javascript library under certain conditions
 *
 * @param array $parameters
 * @param object $pObj (template object)
 */
function user_addBackendLibrary($parameters, $pObj) {

	if (isset($parameters['title'])) {
		if($parameters['title'] == 'TYPO3 Edit Document'){

			$_editArray = t3lib_div::GPvar('edit');
			if (is_array($_editArray)) {
				$table = key($_editArray);
				if ($table == 'tx_templatedisplay_displays') {
					$pObj->loadJavascriptLib('js/common.js');
					$pObj->loadJavascriptLib(t3lib_extMgm::extRelPath('templatedisplay') . 'resources/javascript/formatJson.js');
					$pObj->loadJavascriptLib(t3lib_extMgm::extRelPath('templatedisplay') . 'resources/javascript/templatedisplay.js');
					$pObj->inDocStylesArray['templatedisplay'] = file_get_contents(t3lib_extMgm::extPath('templatedisplay').'resources/css/templatedisplay.css');
				}
			}
		}
	}
}
?>
