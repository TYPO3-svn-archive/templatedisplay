<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter <typo3@cobweb.ch>
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
***************************************************************/

/**
 * Example class for custom element types in Template Display
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_templatedisplay
 *
 * $Id$
 */
class tx_templatedisplay_PhoneType implements tx_templatedisplay_CustomType {
	/**
	 * This method renders a custom object for Template Display
	 *
	 * @param	mixed				$value: The value of the field being rendered
	 * @param	array				$conf: TypoScript configuration for the rendering
	 * @param	tx_templatedisplay	$pObj: back-reference to the calling object
	 * @return	string				The HTML to display
	 */
	function render($value, $conf, tx_templatedisplay $pObj) {
		$rendering = '<a href="callto://' . rawurlencode($value) . '">' . $value . '</a>';
		return $rendering;
	}
}
?>
