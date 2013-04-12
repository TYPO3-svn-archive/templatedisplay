<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_templatedisplay_displays'] = array(
	'ctrl' => $TCA['tx_templatedisplay_displays']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,description,mappings'
	),
	'feInterface' => $TCA['tx_templatedisplay_displays']['feInterface'],
	'columns' => array(
		'hidden' => array(
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array(
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.title',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'required,trim',
			)
		),
		'description' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.description',
			'config' => array(
				'type' => 'text',
				'cols' => '40',
				'rows' => '4',
			)
		),
		'template' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.template',
			'config' => array(
				'type' => 'text',
				'cols' => '40',
				'rows' => '4',
			)
		),
		'mappings' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.mappings',
			'config' => array(
				'type' => 'user',
				'userFunc' => 'tx_templatedisplay_tceforms->mappingField',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden, title, mappings, description')
	),
);
?>