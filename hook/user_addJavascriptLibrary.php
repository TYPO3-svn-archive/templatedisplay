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
***************************************************************/

/**
 * Add a javascript library under certain conditions
 * 
 * @param array $parameters
 * @param object $pObj (template object)
 */
function user_addJavascriptLibrary($parameters, $pObj) {
	if(isset($parameters['title'])){
		if($parameters['title'] == 'TYPO3 Edit Document'){
			$pObj->loadJavascriptLib(t3lib_extMgm::extRelPath('templatedisplay') . 'resources/javascript/templatedisplay.js');
        }
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/hook/class.tx_templatedisplay_backendHeader.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/hook/class.tx_templatedisplay_backendHeader.php']);
}

?>