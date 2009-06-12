<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

//---- includes ----------------------------------------------------------------
	/**
	* Scalable lib
	*/
	require_once(t3lib_extMgm::extPath($_EXTKEY) . 'libs/class.t3pscalable.php');

//---- logic -------------------------------------------------------------------
// code of XClasses varies between TYPO3 versions
switch ($TYPO_VERSION) {
	case '4.2.0':
		$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_db.php']= t3lib_extMgm::extPath($_EXTKEY) . 'typo3versions/4.2.FF/class.ux_t3lib_db.php';
		break;

	default:
		// unsupported version. do nothing.
} // end: switch

?>
