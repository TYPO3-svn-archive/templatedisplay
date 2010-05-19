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
		'debug_markers' => array(
			'exclude' => 1,
			#'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug',
			'config'  => array(
				'type'    => 'check',
				'default' => '0',
				'items' => array(
					array('LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug_markers', ''),
				),
			)
		),
		'debug_template_structure' => array(
			'exclude' => 1,
			#'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug_data_template',
			'config'  => array(
				'type'    => 'check',
				'default' => '0',
				'items' => array(
					array('LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug_template_structure', ''),
				),
			)
		),
		'debug_data_structure' => array(
			'exclude' => 1,
			#'label' => 'LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug_data_structure',
			'config'  => array(
				'type'    => 'check',
				'default' => '0',
				'items' => array(
					array('LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug_data_structure', ''),
				),
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
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, mappings, description')
	),
	'palettes' => array(
		'1' => array('showitem' => '')
	)
);

t3lib_extMgm::addToAllTCAtypes("tx_templatedisplay_displays","--palette--;LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.debug;10","","after:description");
t3lib_extMgm::addToAllTCAtypes("tx_templatedisplay_displays","--palette--;LLL:EXT:templatedisplay/Resources/Private/Language/locallang_db.xml:tx_templatedisplay_displays.pagebrowser;20","","after:description");

$TCA['tx_templatedisplay_displays']['palettes']['10'] = array(
	"showitem" => "debug_markers, debug_template_structure, debug_data_structure",
	"canNotCollapse" => 1
);
$TCA['tx_templatedisplay_displays']['palettes']['20'] = array(
	"showitem" => "pagebrowser_tpl, pagebrowser_pagesbefore, pagebrowser_pagesafter, pagebrowser_showmore, pagebrowser_showless",
	"canNotCollapse" => 1
);
?>