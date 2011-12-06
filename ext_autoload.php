<?php
/*
 * Register necessary class names with autoloader
 *
 * $Id$
 */
$extensionPath = t3lib_extMgm::extPath('templatedisplay');
return array(
	'tx_templatedisplay_ajax'		=> $extensionPath . 'class.tx_temlatedisplay_ajax.php',
	'tx_templatedisplay_tceforms'	=> $extensionPath . 'class.tx_templatedisplay_tceforms.php',
	'tx_templatedisplay'			=> $extensionPath . 'class.tx_templatedisplay.php',
	'tx_templatedisplay_customtype'	=> $extensionPath . 'interfaces/interface.tx_templatedisplay_customtype.php',
);
?>
