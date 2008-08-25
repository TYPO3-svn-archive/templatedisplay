/***************************************************************
 *
 *  javascript functions regarding the templatedisplay extension
 *  relies on the javascript library "prototype"
 *
 *
 *  Copyright notice
 *
 *  (c) 2006-2008	Benjamin Mack <www.xnos.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 t3lib/ library provided by
 *  Kasper Skaarhoj <kasper@typo3.com> together with TYPO3
 *
 *  Released under GNU/GPL (see license file in tslib/)
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *  This copyright notice MUST APPEAR in all copies of this script
 *
 * $Id: $
 ***************************************************************/

/**
 *
 * @author	Fabien Udriot
 */
function saveConfiguration(theID, theTable) {
    //$("result" + theID).update("'.$GLOBALS['LANG']->getLL('running').'");
    //$("link" + theID).update(syncRunningIcon);

    new Ajax.Request("ajax.php", {
        method: "get",
        parameters: {
            "ajaxID": "templatedisplay::saveConfiguration",
            "table" : "theTable"
        },
        onComplete: function(xhr) {
            console.log(xhr.responseText);
            return true;
            var response = xhr.responseText.evalJSON();
            var messages = "";
            if (response["error"]) {
                for (i = 0; i < response["error"].length; i++) {
                    messages += "Error: " + response["error"][i] + "<br />";
                }
            }
            if (response["warning"]) {
                for (i = 0; i < response["warning"].length; i++) {
                    messages += "Error: " + response["error"][i] + "<br />";
                }
            }
            if (response["success"]) {
                for (i = 0; i < response["success"].length; i++) {
                    messages += response["error"][i] + "<br />";
                }
            }
        //$("result" + theID).update(messages);
        //$("link" + theID).update(syncStoppedIcon);
        }.bind(this),
        onT3Error: function(xhr) {
        //$("result" + theID).update("Failed");
        }.bind(this)
    });
    return false;
}

