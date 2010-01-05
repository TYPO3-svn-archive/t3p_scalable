<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2009 Fernando Arconada fernando.arconada at gmail dot com
*  All rights reserved
*
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
* Extends the functionality of TYPO3_DB - the general database handler.
*
* $Id$
*
* @author Fernando Arconada fernando.arconada at gmail dot com
* @version 0.9
 */
class ux_t3lib_DB extends t3lib_DB {
	/**
	 * The default link resource (write/master databases)
	 * @var	resource
	 */
	public $link = FALSE;

	/**
	 * The alternative link resoure (read/slave databases) 
	 * @var	resource
	 */
	public $linkRead = FALSE;

	/**
	 * The controlling t3p_scalable object
	 * @var	tx_t3pscalable
	 */
	protected $t3pscalable;

	/**
	 * Constructs this object.
	 */
	public function __construct() {
		$this->t3pscalable = t3lib_div::makeInstance('tx_t3pscalable');
	}


	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param	string		List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param	string		Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param	string		Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param	string		Optional GROUP BY field(s), if none, supply blank string.
	 * @param	string		Optional ORDER BY field(s), if none, supply blank string.
	 * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return	pointer		MySQL result pointer / DBAL object
	 */
	public function exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy='', $orderBy='', $limit='') {
		if (!$this->t3pscalable->isAssuredWriteBackendSession() && !$this->t3pscalable->isAssuredWriteCliDispatch() && !$this->t3pscalable->isAssuredWriteTable($from_table)) {
			$query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
			$res = mysql_query($query, $this->linkRead);

			if ($this->debugOutput) {
				$this->debug('exec_SELECTquery');
			}
			if ($this->explainOutput) {
				$this->explain($query, $from_table, $this->sql_num_rows($res));
			}
		} else {
			$res = $this->exec_SELECTquery_master($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
		}

		return $res;
	}

	/**
	 * Creates and executes a SELECT SQL-statement on the master/regular link
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param	string		List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param	string		Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param	string		Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param	string		Optional GROUP BY field(s), if none, supply blank string.
	 * @param	string		Optional ORDER BY field(s), if none, supply blank string.
	 * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return	pointer		MySQL result pointer / DBAL object
	 */
	public function exec_SELECTquery_master($select_fields, $from_table, $where_clause, $groupBy='', $orderBy='', $limit='') {
		return parent::exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Open a (persistent) connection to a MySQL server
	 * mysql_pconnect() wrapper function
	 * Usage count/core: 12
	 *
	 * @param	string		Database host IP/domain
	 * @param	string		Username to connect with.
	 * @param	string		Password to connect with.
	 * @return	pointer		Returns a positive MySQL persistent link identifier on success, or FALSE on error.
	 */
	public function sql_pconnect($TYPO3_db_host, $TYPO3_db_username, $TYPO3_db_password) {
			// mysql_error() is tied to an established connection
			// if the connection fails we need a different method to get the error message
		ini_set('track_errors', 1);
		ini_set('html_errors', 0);

		// you need 2 links to database one for read/write queries (link) and other for read only queries (linkRead)
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect']) {
			$this->linkRead = $this->t3pscalable->getDbReadConnection($GLOBALS['t3p_scalable_conf']['db']['readAttempts']);
			$this->link = $this->t3pscalable->getDbWriteConnection($GLOBALS['t3p_scalable_conf']['db']['writeAttempts']);
		} else {
			// You cant balance if it uses persistent connections
			$this->link = @mysql_pconnect($TYPO3_db_host, $TYPO3_db_username, $TYPO3_db_password);
			$this->linkRead = $this->link;
		}
		$error_msg = $php_errormsg;
		ini_restore('track_errors');
		ini_restore('html_errors');

		if (!$this->linkRead) {
				// Using default link  as fallback if read only link is not available:
			t3lib_div::sysLog('Could not connect to MySQL server ' . $TYPO3_db_host . ' with user ' . $TYPO3_db_username . ': ' . $error_msg, 'Core', 4);
			$this->linkRead = $this->link;
		} else {
			$setDBinit = t3lib_div::trimExplode(chr(10), $GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit'],TRUE);
			foreach ($setDBinit as $v) {
				if (mysql_query($v, $this->linkRead) === FALSE) {
					t3lib_div::sysLog('Could not initialize DB connection with query "' . $v . '": ' . mysql_error($this->linkRead), 'Core', 3);
				}
			}
			foreach ($setDBinit as $v) {
				if (mysql_query($v, $this->link) === FALSE)	{
					t3lib_div::sysLog('Could not initialize DB connection with query "' . $v . '": ' . mysql_error($this->link), 'Core', 3);
				}
			}
		}

		return $this->linkRead;
	}

	/**
	 * Select a MySQL database
	 * mysql_select_db() wrapper funct
	 *
	 * @param	string		Database to connect to.
	 * @return	boolean		Returns TRUE on success or FALSE on failure.
	 */
	public function sql_select_db($TYPO3_db) {
			// very important: use the same databasename in all servers
		$ret = @mysql_select_db($TYPO3_db, $this->link);

		if (!$ret) {
			t3lib_div::sysLog('Could not select MySQL database ' . $TYPO3_db . ': ' . mysql_error(), 'Core', 4);
		}
		$ret = @mysql_select_db($TYPO3_db, $this->linkRead);
		if (!$ret) {
			t3lib_div::sysLog('Could not select MySQL database ' . $TYPO3_db . ': ' . mysql_error(), 'Core', 4);
		}

		return $ret;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/typo3versions/4.2.FF/class.ux_t3lib_db.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/typo3versions/4.2.FF/class.ux_t3lib_db.php']);
}

?>