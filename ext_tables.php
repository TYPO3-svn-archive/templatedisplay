<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

t3lib_extMgm::allowTableOnStandardPages('tx_templatedisplay_displays');

	// TCA ctrl for new table
$TCA['tx_templatedisplay_displays'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays',
		'label'     => 'title',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
		),
		'searchFields' => 'title,description,template',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_templatedisplay_displays.png',
	),
);

	// Add context sensitive help (csh) for this table
t3lib_extMgm::addLLrefForTCAdescr('tx_templatedisplay_displays', t3lib_extMgm::extPath($_EXTKEY) . 'Resources/Private/Language/locallang_csh_txtemplatedisplaydisplays.xml');

	// Add a wizard for adding a data consumer
$addTemplateDisplayWizard = array(
						'type' => 'script',
						'title' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:wizards.add_templatedisplay',
						'script' => 'wizard_add.php',
						'icon' => 'EXT:templatedisplay/wizard_icon.gif',
						'params' => array(
								'table' => 'tx_templatedisplay_displays',
								'pid' => '###CURRENT_PID###',
								'setValue' => 'set'
							)
						);
$TCA['tt_content']['columns']['tx_displaycontroller_consumer']['config']['wizards']['add_templatedisplay'] = $addTemplateDisplayWizard;


	// Register templatedisplay with the Display Controller as a Data Consumer
t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['columns']['tx_displaycontroller_consumer']['config']['allowed'] .= ',tx_templatedisplay_displays';

	// Define the path to the static TS files
t3lib_extMgm::addStaticFile($_EXTKEY, 'static/', 'Template Display');
?>