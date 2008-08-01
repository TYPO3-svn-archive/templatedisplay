<?php
/***************************************************************
*  Copyright notice
*  
*  (c)  2007 Fabien Udriot <fabien.udriot@ecodev.ch>
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
 * Class to check wheter the mappings field should be cleared or not.
 * This is the case when a *new* template file is uploaded.
 *
 * @author  Fabien Udriot <fabien.udriot@ecodev.ch>
 */
class tx_infomodule_mappings {

    /**
     * When a new template file is uploaded, clear the field mappings.
     *
     * @param	string		action status: new/update is relevant for us
     * @param	string		db table
     * @param	integer		record uid
     * @param	array		record
     * @param	object		parent object
     * @return	void
     */
    function processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, $pObj) {
        if($table == 'tx_templatedisplay_displays' && ($status == 'new' || $status == 'update')) {
			
			// Retrieves the uploaded file value.
            $uploadedFile = $pObj->uploadedFileArray['tx_templatedisplay_displays'][1]['template'];
			
			// If a name exists, means the mappings has to be reset.
			if($uploadedFile['name'] != ''){
				$fieldArray['mappings'] = '';
            }

        }
    }

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tx_templatedisplay/class.tx_templatedisplay_mappings.php'])	{
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tx_templatedisplay/class.tx_templatedisplay_mappings.php']);
}

?>