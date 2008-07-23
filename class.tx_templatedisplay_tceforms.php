<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Francois Suter (Cobweb) <typo3@cobweb.ch>
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
* $Id: class.tx_datadisplay_pi1.php 3938 2008-06-04 08:39:01Z fsuter $
***************************************************************/

/**
 * TCEform custom field for template mapping
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_templatedisplay
 */
class tx_templatedisplay_tceforms {
	protected $extKey = 'templatedisplay';

	/**
	 * This method renders the user-defined mapping field,
     * i.e. the screen where data is mapped to the template markers
     *
     * @param	array			$PA: information related to the field
     * @param	t3lib_tceform	$fobj: reference to calling TCEforms object
	 *
	 * @return	string	The HTML for the form field
	 */
	public function mappingField($PA, $fobj) {
		$formField = '';
		$formField .= '<input type="hidden" name="'.$PA['itemFormElName'].'" value="Hello" />';
//		$formField .= t3lib_div::view_array($PA);
//		$formField .= t3lib_div::view_array($GLOBALS['TYPO3_CONF_VARS']);
		$formField .= $this->getMMTableName();
		return $formField;
	}

	/**
     * This method returns the name of the table where the relations between
     * Data Providers and Controllers are saved
     *
     * @return	string	Name of the table
     */
	protected function getMMTableName() {
		return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['mm_table'];
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/templatedisplay/class.tx_templatedisplay_tceforms.php']);
}

?>