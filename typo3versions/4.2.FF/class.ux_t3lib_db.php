<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Fernando Arconada fernando.arconada at gmail dot com
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
*
* @author Fernando Arconada fernando.arconada at gmail dot com
* @version 0.9
 */



class ux_t3lib_DB extends t3lib_DB {



		// Default link identifier:
	var $link = FALSE;
		// link to SELECT queries
	var $linkRead = FALSE;
		// t3pscalable object;
	var $t3pscalable=FALSE;
		//memcached connection
	var $memcached_obj=FALSE;
		// last SELECT query
	var $memcached_lastSelectQuery='';
	var $memcached_md5lastSelectQuery='';
	var $memcached_lastQueryObject='';
	var $memcached_tryMemcached=TRUE;
	var $memcached_lastQueryNumRows=0;
	var $memcached_ttl=0;
	var $memcached_realttl=0;
	
	private static $memcached_cursor=0;

	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 * Usage count/core: 340
	 *
	 * @param	string		List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param	string		Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param	string		Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param	string		Optional GROUP BY field(s), if none, supply blank string.
	 * @param	string		Optional ORDER BY field(s), if none, supply blank string.
	 * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return	pointer		MySQL result pointer / DBAL object
	 */
	function exec_SELECTquery($select_fields,$from_table,$where_clause,$groupBy='',$orderBy='',$limit='',$tryMemcached='')	{
		$query = $this->SELECTquery($select_fields,$from_table,$where_clause,$groupBy,$orderBy,$limit,$tryMemcached);
		$res='MC';
		// Disable memcached if there is a backend session
		if ($GLOBALS['BE_USER']->user['uid']){
			$GLOBALS['t3p_scalable_conf']['memcached']['enabled']=FALSE;
		}
		
		// First try from memcached if enabled and if its you you have to try in memecahed by default
		// you can disable memcached for a single query with a HINT setting $tryMemcached=false
		if ($GLOBALS['t3p_scalable_conf']['memcached']['enabled'] && $this->memcached_tryMemcached){
			$this->memcached_lastQueryObject=$this->memcached_obj->get($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery);
			$this->memcached_lastQueryNumRows=$this->memcached_lastQueryObject;
			$memcache_stale = $this->memcached_obj->get($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-STALE');
			if (!$memcache_stale){
				//cache isnt fresh, start regeneration
				$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-STALE',1,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$GLOBALS['t3p_scalable_conf']['memcached']['queryGenerationTime']);
				$this->memcached_lastQueryObject=FALSE;
			}
		}
	
		// There isnt memcached or memcached couldnt find the object or you have disabled memcached for this query
		if(!$GLOBALS['t3p_scalable_conf']['memcached']['enabled'] || !$this->memcached_lastQueryObject || !$this->memcached_tryMemcached){
			$res = mysql_query($query, $this->linkRead);
			if($GLOBALS['t3p_scalable_conf']['memcached']['enabled'] && $this->memcached_tryMemcached){
				$this->memcached_lastQueryNumRows=mysql_num_rows($res);
			}
			if ($this->debugOutput) {
				$this->debug('exec_SELECTquery');
			}
			if ($this->explainOutput) {
				$this->explain($query, $from_table, $this->sql_num_rows($res));
			}
		}

		return $res;
	}

	/**
	 * Creates a SELECT SQL-statement
	 * Usage count/core: 11
	 *
	 * @param	string		See exec_SELECTquery()
	 * @param	string		See exec_SELECTquery()
	 * @param	string		See exec_SELECTquery()
	 * @param	string		See exec_SELECTquery()
	 * @param	string		See exec_SELECTquery()
	 * @param	string		See exec_SELECTquery()
	 * @return	string		Full SQL query for SELECT
	 * @deprecated			use exec_SELECTquery() instead if possible!
	 */
	function SELECTquery($select_fields,$from_table,$where_clause,$groupBy='',$orderBy='',$limit='',$tryMemcached='')	{
			// Table and fieldnames should be "SQL-injection-safe" when supplied to this function
			// Build basic query:
		$query = 'SELECT '.$select_fields.'
			FROM '.$from_table.
			(strlen($where_clause)>0 ? '
			WHERE
				'.$where_clause : '');

			// Group by:
		if (strlen($groupBy)>0)	{
			$query.= '
			GROUP BY '.$groupBy;
		}
			// Order by:
		if (strlen($orderBy)>0)	{
			$query.= '
			ORDER BY '.$orderBy;
		}
			// Group by:
		if (strlen($limit)>0)	{
			$query.= '
			LIMIT '.$limit;
		}

			// Return query:
		if ($this->debugOutput || $this->store_lastBuiltQuery) $this->debug_lastBuiltQuery = $query;
		
		// If memcached its enabled
		if ($GLOBALS['t3p_scalable_conf']['memcached']['enabled']){
			if($tryMemcached===''){
				// if you havent set a HINT for this query in memcached try the default behavior
				$this->memcached_tryMemcached=$GLOBALS['t3p_scalable_conf']['memcached']['defaultTryMemcached'];
			}else{
				// you have set a HINT for this query with memcached
				$this->memcached_tryMemcached=$tryMemcached;
			}
			if($this->memcached_tryMemcached){
				$this->memcached_lastSelectQuery=$query;
				$this->memcached_md5lastSelectQuery=md5($query);
			}else{
				$this->memcached_lastSelectQuery='';
				$this->memcached_md5lastSelectQuery='';
			}
			self::$memcached_cursor=0;
		}
		return $query;
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
	function sql_pconnect($TYPO3_db_host, $TYPO3_db_username, $TYPO3_db_password)	{
			// mysql_error() is tied to an established connection
			// if the connection fails we need a different method to get the error message
		ini_set('track_errors', 1);
		ini_set('html_errors', 0);
		$this->t3pscalable = t3lib_div::makeInstance('t3pscalable');
		$this->t3pscalable->init($GLOBALS['t3p_scalable_conf']);
		if($GLOBALS['t3p_scalable_conf']['memcached']['enabled']){
			$this->memcached_obj = $this->t3pscalable->getMemcachedConnection($GLOBALS['t3p_scalable']['memcached']['connectionAttempts']);
			$this->memcached_ttl = $GLOBALS['t3p_scalable_conf']['memcached']['objectTimeout'];
			$this->memcached_realttl = $this->memcached_ttl + $GLOBALS['t3p_scalable_conf']['memcached']['queryGenerationTime']*2;
		}
		
		// you need 2 links to database one for read/write queries (link) and other for read only queries (linkRead)
		if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['no_pconnect'])	{
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
			t3lib_div::sysLog('Could not connect to MySQL server '.$TYPO3_db_host.' with user '.$TYPO3_db_username.': '.$error_msg,'Core',4);
		} else {
			$setDBinit = t3lib_div::trimExplode(chr(10), $GLOBALS['TYPO3_CONF_VARS']['SYS']['setDBinit'],TRUE);
			foreach ($setDBinit as $v)	{
				if (mysql_query($v, $this->linkRead) === FALSE)	{
					t3lib_div::sysLog('Could not initialize DB connection with query "'.$v.'": '.mysql_error($this->linkRead),'Core',3);
				}
			}
			foreach ($setDBinit as $v)	{
				if (mysql_query($v, $this->link) === FALSE)	{
					t3lib_div::sysLog('Could not initialize DB connection with query "'.$v.'": '.mysql_error($this->link),'Core',3);
				}
			}
		}

		return $this->linkRead;
	}

        /** 
         * Select a MySQL database
         * mysql_select_db() wrapper function
         * Usage count/core: 8
         *
         * @param       string          Database to connect to.
         * @return      boolean         Returns TRUE on success or FALSE on failure.
         */
    function sql_select_db($TYPO3_db)       {
        		// very important: use the same databasename in all servers
                $ret = @mysql_select_db($TYPO3_db, $this->link);
                if (!$ret) {
                        t3lib_div::sysLog('Could not select MySQL database '.$TYPO3_db.': '.mysql_error(),'Core',4);
	        }
                $ret = @mysql_select_db($TYPO3_db, $this->linkRead);
                if (!$ret) {
                        t3lib_div::sysLog('Could not select MySQL database '.$TYPO3_db.': '.mysql_error(),'Core',4);
	        }
                return $ret;
        }

 	 /** Returns the number of selected rows.
	 * mysql_num_rows() wrapper function
	 * Usage count/core: 85
	 *
	 * @param	pointer		MySQL result pointer (of SELECT query) / DBAL object
	 * @return	integer		Number of resulting rows
	 */
	function sql_num_rows($res)	{
		$this->debug_check_recordset($res);
		if($GLOBALS['t3p_scalable_conf']['memcached']['enabled'] && $this->memcached_tryMemcached && $res=='MC'){
			return $this->memcached_lastQueryNumRows;
		}
		return mysql_num_rows($res);
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * mysql_fetch_assoc() wrapper function
	 * Usage count/core: 307
	 *
	 * @param	pointer		MySQL result pointer (of SELECT query) / DBAL object
	 * @return	array		Associative array of result row.
	 */
	function sql_fetch_assoc($res)	{
		if($GLOBALS['t3p_scalable_conf']['memcached']['enabled'] && $this->memcached_tryMemcached){
			if(!$this->memcached_lastQueryObject){
				/**
				 * $this->memcached_lastQueryObject is set in exec_SELECTquery
				 * The object isnt in memcahed: save it to be able to get from memcached the next time 
			 	 * I have to store all rows one by one in memcached to be able to use sql_fetch_assoc transparently
			 	 */
				$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery,$this->memcached_lastQueryNumRows,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$this->memcached_realttl);
				$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-STALE',1,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$this->memcached_ttl);
				$row=mysql_fetch_assoc($res);
				if($row){
					$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-'.self::$memcached_cursor, $row,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$GLOBALS['t3p_scalable_conf']['memcached']['objectTimeout']);
				}
			}else{
				// just get the row from memcached
				$row=$this->memcached_obj->get($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-'.self::$memcached_cursor);		
			}

			self::$memcached_cursor++;
			return $row;
		}else{
			$this->debug_check_recordset($res);
			return mysql_fetch_assoc($res);
		}
		
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * mysql_fetch_row() wrapper function
	 * Usage count/core: 56
	 *
	 * @param	pointer		MySQL result pointer (of SELECT query) / DBAL object
	 * @return	array		Array with result rows.
	 */
	function sql_fetch_row($res)	{
			if($GLOBALS['t3p_scalable_conf']['memcached']['enabled'] && $this->memcached_tryMemcached){
			if(!$this->memcached_lastQueryObject){
				/**
				 * $this->memcached_lastQueryObject is set in exec_SELECTquery
				 * The object isnt in memcahed: save it to be able to get from memcached the next time 
			 	 * I have to store all rows one by one in memcached to be able to use sql_fetch_assoc transparently
			 	 */
				$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery,$this->memcached_lastQueryNumRows,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$this->memcached_realttl);
				$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-STALE',1,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$this->memcached_ttl);
				$row=mysql_fetch_assoc($res);
				if($row){
					$this->memcached_obj->set($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-'.self::$memcached_cursor, $row,$GLOBALS['t3p_scalable_conf']['memcached']['compressed'],$GLOBALS['t3p_scalable_conf']['memcached']['objectTimeout']);
				}
			}else{
				// just get the row from memcached
				$row=$this->memcached_obj->get($this->t3pscalable->getMemcachedKeyPrefix().$this->memcached_md5lastSelectQuery.'-'.self::$memcached_cursor);		
			}
			self::$memcached_cursor++;
			return $row;
		}else{
			$this->debug_check_recordset($res);
			return mysql_fetch_row($res);
		}
	}

	/**
	 * Free result memory
	 * mysql_free_result() wrapper function
	 * Usage count/core: 3
	 *
	 * @param	pointer		MySQL result pointer to free / DBAL object
	 * @return	boolean		Returns TRUE on success or FALSE on failure.
	 */
	function sql_free_result($res)	{
		if($GLOBALS['t3p_scalable_conf']['memcached']['enabled']){
			if($res && ($res != 'MC')){
				$this->debug_check_recordset($res);
				return mysql_free_result($res);
			}else{
				return TRUE;
			}
		}else{
			$this->debug_check_recordset($res);
			return mysql_free_result($res);
		}
		
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/typo3versions/4.2.FF/class.ux_t3lib_db.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/typo3versions/4.2.FF/class.ux_t3lib_db.php']);
}
?>
