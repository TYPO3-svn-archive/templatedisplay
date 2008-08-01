<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_templatedisplay_displays=1
');

/*
 * Hook for loading Javascript and CSS in the backend
 */
if (TYPO3_MODE == 'BE')	{
	require_once(t3lib_extMgm::extPath('templatedisplay').'hook/user_addBackendLibrary.php');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/template.php']['preStartPageHook'][] = 'user_addBackendLibrary'; 

    
if (TYPO3_MODE == 'BE')	{
	require_once(t3lib_extMgm::extPath('templatedisplay').'hook/class.tx_infomodule_mappings.php');
}
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'tx_infomodule_mappings'; 

?>