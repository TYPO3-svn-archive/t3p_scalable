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
* Class to have a TYPO3 more scalable
*
* $Id$
*
* @author Fernando Arconada fernando.arconada at gmail dot com
* @version 0.9
*/
class tx_t3pscalable {
	/**
	 * Database servers configurations
	 *
	 * @var array
	 */
	var $db_config=null;
	/**
	 * Memcached servers configurations
	 *
	 * @var array
	 */
	var $memcached_config=null;

	/**
	 * Initializes the objects of this class
	 *
	 * @param array $conf
	 */
	public function init($conf){
		$this->db_config = $conf['db'];
		$this->memcached_config = $conf['memcached'];
	}
	/**
	 * Private function to get a DB (both, read and write servers)
	 *
	 * @param string $type Type of connection Enum{'read','write'}
	 * @param int $attempts number of times to try to connect to a db server
	 * @return resource_id DB link resource
	 */
	private function getDbConnection($type,$attempts=1){
	/* $attempts : number of times to try to connect to a db server
		1..n : 1 or more tries choosing servers in a pseudo random fashion
	*/
		$db_server=null;
		$link=null;
		switch($type):
			case 'read':
				$db_server=$this->getReadHost();
				break;
			case 'write':
				$db_server=$this->getWriteHost();
				break;
		endswitch;
		while(!$link && $attempts>0){
			$link = @mysql_connect($db_server['host'].':'.$db_server['port'],$db_server['user'],$db_server['pass']);
			$attempts--;
		}
		return $link;


	}

	/**
	 * Public wrapper function for getDbConnection only for 'read' servers
	 *
	 * @param int $attempts number of times to try to connect to a db server
	 * @return resource_id DB link resource or FALSE
	 */
	public function getDbReadConnection($attempts){
		return $this->getDbConnection('read',$attempts);
	}

	/**
	 * Public wrapper function for getDbConnection only for 'write' servers
	 *
	 * @param int $attempts number of times to try to connect to a db server
	 * @return resource_id DB link resource
	 */
	public function getDbWriteConnection($attempts){
		return $this->getDbConnection('write',$attempts);
	}

	/**
	 * Private function to get DB server config array in a random way, the server its selected depending of its weight
	 *
	 * @param string $type Type of connection Enum{'read','write'}
	 * @return array db server config
	 */
	private function getDbHost($type){
		$db_hosts = array();
		foreach ($this->db_config[$type] as $host){
			if(isset($host['weight'])){
				for($i=1;$i<=intval($host['weight']);$i++){
					array_push($db_hosts,$host);
				}
			}else{
				array_push($db_hosts,$host);
			}
		}
		return $db_hosts[rand(0,count($db_hosts)-1)];
	}
	/**
	 * Public wrapper function for getDbHost only for 'read' servers
	 *
	 * @return array db server config
	 */
	public function getReadHost(){
		return $this->getDbHost('read');
	}

	/**
	 * Public wrapper function for getDbHost only for 'write' servers
	 *
	 * @return array db server config
	 */
	public function getWriteHost(){
		return $this->getDbHost('write');
	}
	/**
	 * function ti return a memcached conection
	 *
	 * @param int $attempts number of times to try to connect to a memcached server
	 * @return resource_id memcached connection resource or FALSE
	 */
	public function getMemcachedConnection($attempts=1){
	/* $attempts : number of times to try to connect to a memcached server
		1..n : 1 or more tries choosing servers in a pseudo random fashion
	*/
		$memcached_obj=FALSE;
		if ($this->memcached_config['firstLocalhost']){
			//get localhost memcached config
			$local_memcached = null;
			foreach($this->memcached_config['servers'] as $server){
				if($server['host']=='localhost'){
					$local_memcached=$server;
					break;
				}
			}

			if($local_memcached != null){
				$memcached_obj = @memcache_connect($local_memcached['host'], $local_memcached['port']);
				if($memcached_obj){
					return $memcached_obj;
				}elseif(!$attempts){
					// disable memcached if you couldnt get a connection
					return $GLOBALS['t3p_scalable_conf']['memcached']['enabled']=FALSE;
				}
			}
		}
		// at this point: no localhost server in first place or cant connect to it
		// just take a random memcached server
		$memcached_hosts = array();
		while (!$memcached_obj && $attemps>0){
			foreach ($this->memcached_config['servers'] as $host){
				if(isset($host['weight'])){
					for($i=1;$i<=intval($host['weight']);$i++){
						array_push($memcached_hosts,$host);
					}
				}else{
					array_push($memcached_hosts,$host);
				}
			}
			$server = $memcached_hosts[rand(0,count($db_hosts)-1)];
			$memcached_obj = @memcache_connect($server['host'], $server['port']);
			$attempts--;

		}

		return $memcached_obj;
	}

	/**
	 * Returns the memcached key prefix to avoid collision of serveral applications that share the same memcached server
	 *
	 * @return string
	 */
	public function getMemcachedKeyPrefix(){
		return $this->memcached_config['keyPrefix'];
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/class.tx_t3pscalable.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3p_scalable/class.tx_t3pscalable.php']);
}

?>