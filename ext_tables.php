<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

t3lib_extMgm::allowTableOnStandardPages('tx_templatedisplay_displays');

// Include class for custom TCEforms field

require_once(t3lib_extMgm::extPath('templatedisplay', 'class.tx_templatedisplay_tceforms.php'));

// TCA ctrl for new table

$TCA['tx_templatedisplay_displays'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:templatedisplay/locallang_db.xml:tx_templatedisplay_displays',		
		'label'     => 'title',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY crdate',
		'delete' => 'deleted',	
		'enablecolumns' => array(
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_templatedisplay_displays.png',
	),
);

// Register templatedisplay with the Display Controller as a Data Consumer

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['columns']['tx_displaycontroller_consumer']['config']['allowed'] .= ',tx_templatedisplay_displays';

// Define table name that contains the MM relationships

$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['mm_table'] = 'tx_displaycontroller_consumers_mm';
?>