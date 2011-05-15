<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;

use \PDO;

/**
 * Wrapped for PDO class
 * to perform common tasks
 *
 * @author Dmitri Snytkine
 *
 */
class DB extends LampcmsObject
{
	protected $oRegistry;

	/**
	 * Instance of this object
	 * @var object
	 */
	protected static $oDb = null;

	/**
	 * PDO object
	 *
	 * @var object PDO object
	 */
	protected $dbh;

	/**
	 * Array of DB section
	 * in !config.inc
	 *
	 * @var mixed null|array
	 */
	protected $aDB = null;

	/**
	 * Array to log
	 * queries during the life
	 * of the object
	 * @var array
	 */
	protected $aLog = array();

	/**
	 * Timestamp in microseconds
	 * this var initiated just
	 * before the query starts
	 * @var float
	 */
	protected $ts = null;

	/**
	 * Constructor
	 *
	 * @return object
	 */
	public function __construct(Registry $oRegistry){
		$this->oRegistry = $oRegistry;
		$this->oIni = $oRegistry->Ini;
	}


	/**
	 * Release resource when this object terminates
	 *
	 * @todo keep an eye on the possible problems related to
	 * this destructor.
	 *
	 * It may cause some problems that have to do with serializing
	 * and unserializing an object, in which case this descructor
	 * may be triggered, unsetting the dbh and since this object is singleton,
	 * it may affect other objects that are still alive and need
	 * and access to dbh through the instance of this class.
	 *
	 * This is complicated, this should not really happened, but
	 * if there are any weired errors that are traced to call to
	 * member function on non-object and it's traced to dbh
	 * not being an object where it's supposed to be an object for sure,
	 * then the problem is almost certainly due to this destructor
	 *
	 * @return void
	 */
	public function __destruct(){

	}

	protected function connect(){
		$sDsn = $this->makeDsn();

		d('$this->aDB: '.print_r($this->aDB, 1).' DSN: '.$sDsn);
		$aOptions = array(PDO::ATTR_PERSISTENT => false);

		if (isset($this->aDB['Persistent']) && (true === (bool)$this->aDB['Persistent'])) {
			d('Instantiating persistent connection');
			$aOptions[PDO::ATTR_PERSISTENT] = true;
		}

		if ('mysql' === $this->aDB['Database_type']) {
			$aOptions[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
		}
		d('trying to connect to database with options: '.print_r($aOptions, true));

		try {
			$this->dbh = new PDO($sDsn, $this->aDB['Database_username'], $this->aDB['Database_password'], $aOptions);
			$this->dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			/**
			 * In order to have all queries be done in utf8 charset, uncomment
			 * the line below
			 * and MAKE sure the default charset in mysql tables is set to utf8
			 * which should be done anyway, regardless...
			 *
			 * The query below will only set the connection (php's client lib)
			 * to use utf8, so mysql server will know that all data coming from
			 * php is already in utf8 and will not try to convert it into utf8
			 *
			 */
			$this->dbh->exec('SET NAMES utf8'); // now using this as connection option!
		} catch(\PDOException $e) {
				
			throw new DBException('Cannot connect to database: '.$e->getMessage());
		}
	}


	/**
	 * Getter of PDO dbh object
	 * @return object of type PDO
	 */
	public function getDbh(){
		if(!isset($this->dbh)){
			$this->connect();
		}

		return $this->dbh;
	}


	public function __clone(){
		throw new DevException('cloning DB object not allowed');
	}


	/**
	 * Sets the object var $this->aDB
	 * and returns the dsn string
	 *
	 * @return string dsn
	 *
	 * @throws LampcmsIniException if
	 * some required elements are missing
	 * in the !config.ini file
	 */
	protected function makeDsn(){
		$this->aDB = $this->oIni->getSection('DB');

		if (null === $this->aDB) {
				
			throw new IniException('section "DB" does not exist in aIni');
		}

		if (!isset($this->aDB['Database_username']) || !isset($this->aDB['Database_password'])) {
				
			throw new IniException('Database_username OR Database_password not set');
		}

		return $this->getDSN();
	}


	/**
	 * Creates a dsn string from
	 * values in DB section of ini file
	 *
	 * @return string DSN string
	 * @throws LampcmsIniException if some
	 * required values in DB section are not set
	 */
	protected function getDSN(){

		if ( empty($this->aDB['Database_name']) || empty($this->aDB['Database_host']) ||
		empty ($this->aDB['Database_type'])) {
				
			throw new IniException('Cannot create dsn because some required dns params are missing: '.
			print_r($this->aDB, true));
		}

		$dbhost = strtolower($this->aDB['Database_host']);

		$ret = strtolower($this->aDB['Database_type']).':host='.$dbhost;
		if ('localhost' !== $dbhost) {

			if ( empty ($this->aDB['TCP_Port_number'])) {

				throw new IniException('If Database_host is not "localhost" then "TCP_Port_number" MUST be defined');
			}

			$ret .= ';port='.$this->aDB['TCP_Port_number'];

		}

		$ret .= ';dbname='.$this->aDB['Database_name'];

		return $ret;

	}


	/**
	 * This function is executing sql statement
	 * with database
	 * and return an resultset if it executed
	 * successfully or log message on error
	 *
	 * @param string $strSql Sql Statement
	 * @param string $strErr2 optional string
	 * for additional logging.
	 * usually used to pass the information
	 * with class name, line from
	 * where this function was called.
	 *
	 * @param string $fetchmode the MDB2
	 * class-specific fetchmode
	 * @param boolean $rekey turn the result
	 * array into associative array
	 * where the first value
	 * (result of first column in select)
	 * becomes an array key.
	 *
	 * @param boolean $force_array
	 * @param boolean $group
	 *
	 * @return array with one mysql
	 * row per element,
	 * each row is an associative
	 * array or empty array
	 */
	public function getQueryResult($strSql, $strErr2 = '', $types = null, $m = PDO::FETCH_ASSOC,
	$rekey = false, $force_array = false, $group = false){
		$aRes = array();

		if (true === $force_array) {
				
			return $this->getKeyVal($strSql, $strErr2);
		} elseif ($rekey) {
				
			return $this->getRekeyed($strSql, $strErr2);
		}

		try {
			$aRes = $this->initTimer()->getDbh()->query($strSql, $m)->fetchAll();
			$this->logQuery($strSql);
		} catch(\PDOException $e) {
			d('Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo, true).
			"\nSQL Error code: ".$e->getCode().' called from '.$strErr2);
			$aRes = array();
		}

		return $aRes;
	}


	/**
	 * Return result of
	 * sql query where only 2
	 * columns are returns in a form
	 * of an associative array where the
	 * value of the first column is key
	 * and value of second column is value
	 * in the result array
	 *
	 * @return mixed array associative array
	 * or false if did not work. Most likely
	 * reason would be is when the result of
	 * sql query contains other than 2 columns
	 *
	 * @param object $sql
	 * @param string $sql the sql to execute
	 */
	public function getKeyVal($sql, $err = ''){
		$aRes = array();
			
		try {
			$aRes = $this->initTimer()->getDbh()->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
			$this->logQuery($sql);
		} catch(\PDOException $e) {
			d('Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo, 1).
			"\nSQL Error code: ".$e->getCode().' called from '.$err);

			throw new DevException('failed to fetch result using PDO::FETCH_KEY_PAIR. Error: '.$e->getMessage().' Called from '.$err);

		}

		return $aRes;
	}


	/**
	 * Get the result of fetch All
	 * where the array key is the value of
	 * the first column
	 * and array value is array with key [0]
	 * under which the result is the array
	 * of the rest of the values.
	 *
	 * @return
	 * @param string $sql
	 * @param string $err extra string
	 * for logging/debugging
	 */
	public function getRekeyed($sql, $strErr2 = ''){
		$aRes = array();

		try {
			$aRes = $this->initTimer()->getDbh()->query($sql)->fetchAll(PDO::FETCH_GROUP);
			$this->logQuery($sql);
		} catch(\PDOException $e) {
			d('Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo, true).
			"\nSQL Error code: ".$e->getCode().' called from '.$strErr2);

		}

		return $aRes;
	}


	/**
	 * Fetch the first column from
	 * the first row in the result set
	 *
	 * @param string $strSql
	 *
	 * @param string $strErr2 optional string for additional logging.
	 * usually used to pass the information with class name, line from
	 * where this function was called.
	 *
	 * @return mixed. On success a single record from a single row.
	 * For example 'select userid from USER where id=121
	 * will return a nickname of the user with id 121
	 * On failure it returns
	 * false
	 *
	 */
	public function fetchOne($strSql, $strErr2 = ''){
		$ret = false;

		d('sql: '.$strSql);

		try {
			$sth = $this->initTimer()->getDbh()->prepare($strSql);
			$sth->execute();
			$ret = $sth->fetchColumn();
			$sth = null;
			$this->logQuery($strSql);
			d('$ret: '.$ret);
		} catch(\PDOException $e) {
			d('Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo,
			true)."\nSQL Error code: ".$e->getCode().' called from '.$strErr2);
		}

		return $ret;
	}

	
	/**
	 * Set the value of $this->ts
	 * to the current time in milliseconds
	 *
	 * @return object $this
	 */
	protected function initTimer(){
		$this->ts = microtime(true);

		return $this;
	}


	public function execPrepared(\PDOStatement $sth){
		$this->initTimer();
		try{
			$ret = $sth->execute();
		} catch (\PDOException $e){
			$arr = $sth->errorInfo();
			$err = 'Error executing sth: '.$e->getMessage().' PDOException errorInfo: '.print_r($e->errorInfo, true). ' sth errorInfo: '.print_r($arr, 1);

			e($err);

			throw new DevException($err);
		}
		$endTs = microtime(true);
		$sql = 'Executed prepared statement ';
		if (true === LAMPCMS_DEBUG) {
			ob_start();
			$sth->debugDumpParams();
			$sql .= "\r\n".ob_get_clean();
		}
		/**
		 * New on Dec 31, 09
		 */
		$sth = null;
		unset($sth);

		$this->logQuery($sql, $endTs);

		return $ret;
	}

	/**
	 * Add query to $this->aLog array
	 *
	 * @return object $this
	 * @param string $sql
	 */
	protected function logQuery($sql, $endTs = null){
		if (null === $this->ts) {
				
			throw new DevException('valus of $this->ts was not set. Unable to log query');
		}
		$endTs = (null === $endTs) ? microtime(true) : $endTs;
		$this->aLog[] = array('sql'=>$sql, 'ts'=> ($endTs - $this->ts));
		$this->ts = null;

		return $this;
	}


	/**
	 * Getter for $this->aLog
	 * @return array $this->aLog
	 */
	public function getDebugLog(){
		return $this->aLog;
	}

	
	/**
	 *
	 * @return string a debug output
	 * with info about queries
	 *
	 * @param bool $asHTML[optional]
	 * if true, then converts line feeds
	 * to <br>
	 */
	public function dumpLog($asHTML = false){
		$intTotalTime = 0;
		$numQueries = count($this->aLog);
		$ret = "\r\n".'SQL data: '."\r\n";
		$ret .= "\r\n"."\r\n".'Total queries in this page: ';
		$ret .= $numQueries."\r\n";
		arsort($this->aLog);
		foreach ($this->aLog as $aVal) {
			$strQuery = $aVal['sql'];
			$timeExec = (float)$aVal['ts'];
			$ret .= '<pre>Query '.$intQuery.': '.wordwrap($strQuery, 60)."\r\n".'</pre>SQL Execution time: '.$timeExec."\r\n";
			$intTotalTime += $timeExec;
		}
		$ret .= '<strong>Total of '.$numQueries.' queries executed in: '.$intTotalTime.' seconds</strong>'."\r\n";

		return ($asHTML) ? nl2br($ret) : $ret;
	}

	
	/**
	 * Creates a PDOStatement object
	 * if it has not been already created
	 *
	 * @return object of type PDOStatement
	 * @param object $key
	 * @param object $sql
	 * @param object $strErr2[optional]
	 */
	public function makePrepared($sql, $strErr2 = ''){

		try {
			$sth = $this->initTimer()->getDbh()->prepare($sql);
			$this->logQuery($sql, microtime(true));
		} catch(\PDOException $e) {
			e('Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo,
			true)."\nSQL Error code: ".$e->getCode().' called from '.$strErr2);
		}

		return $sth;
	}


	/**
	 * This function will fetch last inserted id
	 *
	 * @return int $intId last inserted id
	 */
	public function getLastInsertId(){
		$ret = false;
		$ret = $this->getDbh()->lastInsertId();

		return $ret;

	} // end getLastInsertId

	
	/**
	 * Just directly execute a query
	 * this is usefull for update, insert and delete queries
	 *
	 * @param string $strSql sql to execute
	 *
	 * @param string $strErr2 an extra string to add to log
	 *
	 * @return int count of affected rows
	 */
	public function exec($strSql, $strErr2 = ''){
		$count = 0;
		try {
			$count = $this->getDbh()->exec($strSql);
		} catch(\PDOException $e) {
			$err = ('Line: '.$e->getLine().
			' PDO Error: '.$e->getMessage().
			' ERROR: '.print_r($e->errorInfo, true).
			"\nSQL Error code: ".$e->getCode().' called from '.$strErr2);

			throw new \Lampcms\DevException($err);

		}

		return $count;
	}

	
	/**
	 * Returns associative array where
	 * keys are column names and
	 * values are default values
	 *
	 * @param string $strTableName
	 *
	 * @return array assosiative array
	 */
	public function getTableColumns($strTableName, $strErr2 = ''){
		$strTableName = filter_var($strTableName, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
		$strTableName = str_replace(';', '', $strTableName);
		$strTableName = addslashes($strTableName);

		$strSql = "SHOW FULL COLUMNS FROM $strTableName";

		try {
			$stmt = $this->initTimer()->getDbh()->prepare($strSql);
			$stmt->execute();

			$stmt->bindColumn(1, $name);
			$stmt->bindColumn('default', $val);
			$aRes = array();
			while ($row = $stmt->fetch(PDO::FETCH_BOUND)) {
				$aRes[$name] = $val;
			}

			$this->logQuery($strSql);
			d('$aRes: '.print_r($aRes, true));
		}
		catch(\PDOException $e) {
			$message = 'Line: '.$e->getLine().' PDO Error: '.$e->getMessage().' ERROR: '.print_r($e->errorInfo,
			true)."\nSQL Error code: ".$e->getCode().' called from '.$strErr2;
			e($message);

			return false;
		}

		return $aRes;
	}

}
