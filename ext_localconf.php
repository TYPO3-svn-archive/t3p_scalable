<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

	// Include the base class for t3p_scalable:
require_once t3lib_extMgm::extPath($_EXTKEY) . 'class.tx_t3pscalable.php';

	// Define XCLASS accordant to current TYPO3 version:
switch (TYPO3_branch) {
	case '4.2':
	case '4.3':
	case '4.4':
	case '4.5':
		$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_db.php'] = t3lib_extMgm::extPath($_EXTKEY) . 'typo3versions/4.2.FF/class.ux_t3lib_db.php';
		break;

		// unsupported version. do nothing:
	default:
		t3lib_div::sysLog(
			$_EXTKEY . ' is not compatible to the TYPO3 version you are running!',
			$_EXTKEY,
			3
		);
}

?>