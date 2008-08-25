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
* $Id: class.tx_templatedisplay_ajax.php 3977 2008-07-20 17:28:23Z fsuter $
***************************************************************/


/**
 * This class answers to AJAX calls from the 'templatedisplay' extension
 *
 * @author	Fabien Udriot <fabien.udriot@ecodev.ch>
 * @package	TYPO3
 * @subpackage	tx_externalimport
 */
class tx_templatedisplay_ajax {

	/**
	 * This method answers to the AJAX call and starts the synchronisation of a given table
	 *
	 * @return	array	list of messages ordered by status (error, warning, success)
	 * @return	void	(with 4.2)
	 */
	public function saveConfiguration($params, $ajaxObj) {
		print_r($params);
		$theTable = t3lib_div::_GP('table');
		print_r(&$ajaxObj);

		return true;
		$importer = t3lib_div::makeInstance('tx_externalimport_importer');
		$messages = $importer->synchronizeData($theTable);

// Pre-4.2 calling method

		if (get_class($ajaxObj) == 'tx_templatedisplay_ajax') {
			return $messages;
		}

// Post-4.2 calling method

		else {
			$ajaxObj->setContentFormat('json');
			foreach ($messages as $code => $messageList) {
				if (count($messageList) > 0) {
					$ajaxObj->addContent($code, $messageList);
				}
			}
		}
	}
}
?>