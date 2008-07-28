<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_templatedisplay_displays=1
');

if (TYPO3_MODE == 'BE')	{
	require_once(t3lib_extMgm::extPath('templatedisplay').'hook/user_addJavascriptLibrary.php');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/template.php']['preStartPageHook'][] = 'user_addJavascriptLibrary'; 

?>