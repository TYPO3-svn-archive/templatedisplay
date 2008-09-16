<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_templatedisplay_displays=1
');

// Register method with generic BE ajax calls handler
// (as from TYPO3 4.2)

$TYPO3_CONF_VARS['BE']['AJAX']['templatedisplay::saveConfiguration'] = 'typo3conf/ext/templatedisplay/class.tx_temlatedisplay_ajax.php:tx_templatedisplay_ajax->saveConfiguration';
$TYPO3_CONF_VARS['BE']['AJAX']['templatedisplay::saveTemplate'] = 'typo3conf/ext/templatedisplay/class.tx_temlatedisplay_ajax.php:tx_templatedisplay_ajax->saveTemplate';
/*
 * Hook for loading Javascript and CSS in the backend
 */
if (TYPO3_MODE == 'BE')	{
	require_once(t3lib_extMgm::extPath('templatedisplay').'hook/user_addBackendLibrary.php');
}
$TYPO3_CONF_VARS['SC_OPTIONS']['typo3/template.php']['preStartPageHook'][] = 'user_addBackendLibrary'; 

// Register templatedisplay with the Display Controller as a Data Consumer
#if (TYPO3_MODE == 'BE')	{
#	require_once(t3lib_extMgm::extPath('templatedisplay').'hook/class.tx_infomodule_mappings.php');
#}
#$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'tx_infomodule_mappings'; 

// Register as Data Consumer service
// Note that the subtype corresponds to the name of the database table

t3lib_extMgm::addService($_EXTKEY,  'dataconsumer' /* sv type */,  'tx_templatedisplay_dataconsumer' /* sv key */,
	array(

		'title' => 'Data Display Engine',
		'description' => 'Generic Data Consumer for recordset-type data structures',

		'subtype' => 'tx_templatedisplay_displays',

		'available' => TRUE,
		'priority' => 50,
		'quality' => 50,

		'os' => '',
		'exec' => '',

		'classFile' => t3lib_extMgm::extPath($_EXTKEY, 'class.tx_templatedisplay.php'),
		'className' => 'tx_templatedisplay',
	)
);
?>
