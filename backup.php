<?php
/**
 * Backup tool
 * ver.: 1.2.6
 * date: 9 July 2018
 */
// Init
define("VERSION", "1.2.6");

register_shutdown_function('Shutdown_result');

ignore_user_abort(true);
error_reporting( 0 );
set_time_limit ( 10800 );
ini_set('post_max_size', '256M');
ini_set('upload_max_filesize', '256M');
ini_set('memory_limit', '512M');

$task = strtolower(trim($_REQUEST['task']));
$session_key = trim($_REQUEST['session_key']);


if (file_exists('config.php')) include_once('config.php');
if (file_exists('backup_config.php')) include_once('backup_config.php');
if ( !defined('WEBSITE_KEY') )
{ 
    PrintResultOutput('WEBSITE_KEY is absent', false); 
    exit; 
}

if (strtoupper(md5(WEBSITE_KEY)) != strtoupper($session_key))
{ 
    PrintResultOutput('KEY is wrong', false); 
    exit; 
}

if (!defined('DEBUG_LOG')) define("DEBUG_LOG", false);
if (!defined('MAX_FILE_SIZE')) define("MAX_FILE_SIZE", 20971520);   // in bytes 
if (!defined('BACKUP_EXCLUDE_FILE_EXTENSIONS')) define("BACKUP_EXCLUDE_FILE_EXTENSIONS", "*.avi;*.zip;*.tar;*.gz;*.bak;error_log;");


$scan_path = dirname(__FILE__);
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
{	// Windows
	if (!defined('DIRSEP')) define('DIRSEP', '\\');
	$scan_path = str_replace(DIRSEP."webanalyze", DIRSEP, $scan_path);
    $scan_path = $scan_path.DIRSEP;
}
else {
	// Unix
	if (!defined('DIRSEP')) define('DIRSEP', '/');
	$scan_path = substr( $scan_path, 1, strrpos($scan_path, DIRSEP) );
    $scan_path = DIRSEP.$scan_path;
}
$scan_path = str_replace( DIRSEP.DIRSEP, DIRSEP, $scan_path );
define('SCAN_PATH', $scan_path);

$backups_folder = dirname(__FILE__).DIRSEP;
$add_time_stamp = '';

if (isset($_REQUEST['folder'])) 
{
    $backups_folder = trim($_REQUEST['folder']).DIRSEP;
    $add_time_stamp = '__'.date("Y_m_d_H_i_s").'__';
}

define('BACKUP_FILENAME_SQL', $backups_folder.'backup_sql_'.md5(WEBSITE_KEY).$add_time_stamp.'.sql');
define('BACKUP_FILELIST', $backups_folder.'backup_filelist_'.md5(WEBSITE_KEY).$add_time_stamp.'.txt');
define('BACKUP_FILENAME_FILES_TAR', $backups_folder.'backup_files_'.md5(WEBSITE_KEY).$add_time_stamp.'.tar');
define('BACKUP_FILENAME_FILES_ZIP', $backups_folder.'backup_files_'.md5(WEBSITE_KEY).$add_time_stamp.'.zip');

if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 20971520);

if (DEBUG_LOG)
{
    DebugLog('Start', true);
    DebugLog('SCAN_PATH '.SCAN_PATH);
    DebugLog('BACKUP_FILENAME_SQL '.BACKUP_FILENAME_SQL);
    DebugLog('BACKUP_FILELIST '.BACKUP_FILELIST);
    DebugLog('BACKUP_FILENAME_FILES_TAR '.BACKUP_FILENAME_FILES_TAR);
    DebugLog('BACKUP_FILENAME_FILES_ZIP '.BACKUP_FILENAME_FILES_ZIP);
    DebugLog('line');
    DebugLog('task: '.$task);
}


// Execute tasks
switch ($task)
{
	// */webanalyze/backup.php?task=status&session_key=e5d5ccd60d9e59204466c5adace6093f&answer=xxx
	case 'status':
		$result = GetStatus();
		PrintResultOutput($result, true);	
		break;
	
	// */webanalyze/backup.php?task=backup_sql&session_key=e43c132d47dd6c5013b19a0f7fa83f25
	case 'backup_sql':


            	/** Get connection charset
            	* @param Min_DB
            	* @return string
            	*/
            	function charset($connection) {
            		return (version_compare($connection->server_info, "5.5.3") >= 0 ? "utf8mb4" : "utf8"); // SHOW CHARSET would require an extra query
            	}
            
            
            	/** Shortcut for $connection->quote($string)
            	* @param string
            	* @return string
            	*/
            	function q($string) {
            		global $connection;
            		return $connection->quote($string);
            	}
            
            
            	/** Get keys from first column and values from second
            	* @param string
            	* @param Min_DB
            	* @param float
            	* @return array
            	*/
            	function get_key_vals($query, $connection2 = null, $timeout = 0) {
            		global $connection;
            		if (!is_object($connection2)) {
            			$connection2 = $connection;
            		}
            		$return = array();
            		$connection2->timeout = $timeout;
            		$result = $connection2->query($query);
            		$connection2->timeout = 0;
            		if (is_object($result)) {
            			while ($row = $result->fetch_row()) {
            				$return[$row[0]] = $row[1];
            			}
            		}
            		return $return;
            	}
            
            	/** Get all rows of result
            	* @param string
            	* @param Min_DB
            	* @param string
            	* @return array associative
            	*/
            	function get_rows($query, $connection2 = null, $error = "<p class='error'>") {
            		global $connection;
            		$conn = (is_object($connection2) ? $connection2 : $connection);
            		$return = array();
            		$result = $conn->query($query);
            		if (is_object($result)) { // can return true
            			while ($row = $result->fetch_assoc()) {
            				$return[] = $row;
            			}
            		} elseif (!$result && !is_object($connection2) && $error && defined("PAGE_HEADER")) {
            			echo $error . "\n";
            		}
            		return $return;
            	}
            
            	/** Get select clause for convertible fields
            	* @param array
            	* @param array
            	* @param array
            	* @return string
            	*/
            	function convert_fields($columns, $fields, $select = array()) {
            		$return = "";
            		foreach ($columns as $key => $val) {
            			if ($select && !in_array(idf_escape($key), $select)) {
            				continue;
            			}
            			$as = convert_field($fields[$key]);
            			if ($as) {
            				$return .= ", $as AS " . idf_escape($key);
            			}
            		}
            		return $return;
            	}
            
            	/** Print CSV row
            	* @param array
            	* @return null
            	*/
            	function dump_csv($row) {
            		foreach ($row as $key => $val) {
            			if (preg_match("~[\"\n,;\t]~", $val) || $val === "") {
            				$row[$key] = '"' . str_replace('"', '""', $val) . '"';
            			}
            		}
            		echo implode(($_POST["format"] == "csv" ? "," : ($_POST["format"] == "tsv" ? "\t" : ";")), $row) . "\r\n";
            	}
            	
            	/** Escape database identifier
            	* @param string
            	* @return string
            	*/
            	function idf_escape($idf) {
            		return "`" . str_replace("`", "``", $idf) . "`";
            	}
            
            	/** Get escaped table name
            	* @param string
            	* @return string
            	*/
            	function table($idf) {
            		return idf_escape($idf);
            	}
            
            	/** Connect to the database
            	* @return mixed Min_DB or string for error
            	*/
            	function connect() {
            		global $adminer;
            		$connection = new Min_DB;
            		$credentials = $adminer->credentials();
            		if ($connection->connect($credentials[0], $credentials[1], $credentials[2])) {
            			$connection->set_charset(charset($connection)); // available in MySQLi since PHP 5.0.5
            			$connection->query("SET sql_quote_show_create = 1, autocommit = 1");
            			return $connection;
            		}
            		$return = $connection->error;
            		if (function_exists('iconv') && !is_utf8($return) && strlen($s = iconv("windows-1250", "utf-8", $return)) > strlen($return)) { // windows-1250 - most common Windows encoding
            			$return = $s;
            		}
            		return $return;
            	}
            
            	/** Get tables list
            	* @return array array($name => $type)
            	*/
            	function tables_list() {
            		global $connection;
            		return get_key_vals($connection->server_info >= 5
            			? "SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
            			: "SHOW TABLES"
            		);
            	}
            
            	/** Get table status
            	* @param string
            	* @param bool return only "Name", "Engine" and "Comment" fields
            	* @return array array($name => array("Name" => , "Engine" => , "Comment" => , "Oid" => , "Rows" => , "Collation" => , "Auto_increment" => , "Data_length" => , "Index_length" => , "Data_free" => )) or only inner array with $name
            	*/
            	function table_status($name = "", $fast = false) {
            		global $connection;
            		$return = array();
            		foreach (get_rows($fast && $connection->server_info >= 5
            			? "SELECT TABLE_NAME AS Name, Engine, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() " . ($name != "" ? "AND TABLE_NAME = " . q($name) : "ORDER BY Name")
            			: "SHOW TABLE STATUS" . ($name != "" ? " LIKE " . q(addcslashes($name, "%_\\")) : "")
            		) as $row) {
            			if ($row["Engine"] == "InnoDB") {
            				// ignore internal comment, unnecessary since MySQL 5.1.21
            				$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["Comment"]);
            			}
            			if (!isset($row["Engine"])) {
            				$row["Comment"] = "";
            			}
            			if ($name != "") {
            				return $row;
            			}
            			$return[$row["Name"]] = $row;
            		}
            		return $return;
            	}
            
            	/** Find out whether the identifier is view
            	* @param array
            	* @return bool
            	*/
            	function is_view($table_status) {
            		return $table_status["Engine"] === null;
            	}
            
            	/** Get information about fields
            	* @param string
            	* @return array array($name => array("field" => , "full_type" => , "type" => , "length" => , "unsigned" => , "default" => , "null" => , "auto_increment" => , "on_update" => , "collation" => , "privileges" => , "comment" => , "primary" => ))
            	*/
            	function fields($table) {
            		$return = array();
            		foreach (get_rows("SHOW FULL COLUMNS FROM " . table($table)) as $row) {
            			preg_match('~^([^( ]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
            			$return[$row["Field"]] = array(
            				"field" => $row["Field"],
            				"full_type" => $row["Type"],
            				"type" => $match[1],
            				"length" => $match[2],
            				"unsigned" => ltrim($match[3] . $match[4]),
            				"default" => ($row["Default"] != "" || preg_match("~char|set~", $match[1]) ? $row["Default"] : null),
            				"null" => ($row["Null"] == "YES"),
            				"auto_increment" => ($row["Extra"] == "auto_increment"),
            				"on_update" => (preg_match('~^on update (.+)~i', $row["Extra"], $match) ? $match[1] : ""), //! available since MySQL 5.1.23
            				"collation" => $row["Collation"],
            				"privileges" => array_flip(preg_split('~, *~', $row["Privileges"])),
            				"comment" => $row["Comment"],
            				"primary" => ($row["Key"] == "PRI"),
            			);
            		}
            		return $return;
            	}
            
            	
            	/** Get SQL command to create table
            	* @param string
            	* @param bool
            	* @return string
            	*/
            	function create_sql($table, $auto_increment) {
            		global $connection;
            		$return = $connection->result("SHOW CREATE TABLE " . table($table), 1);
            		if (!$auto_increment) {
            			$return = preg_replace('~ AUTO_INCREMENT=\\d+~', '', $return); //! skip comments
            		}
            		return $return;
            	}
            	
            	/** Convert field in select and edit
            	* @param array one element from fields()
            	* @return string
            	*/
            	function convert_field($field) {
            		if (preg_match("~binary~", $field["type"])) {
            			return "HEX(" . idf_escape($field["field"]) . ")";
            		}
            		if ($field["type"] == "bit") {
            			return "BIN(" . idf_escape($field["field"]) . " + 0)"; // + 0 is required outside MySQLnd
            		}
            		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
            			return "AsWKT(" . idf_escape($field["field"]) . ")";
            		}
            	}
            
            	/** Convert value in edit after applying functions back
            	* @param array one element from fields()
            	* @param string
            	* @return string
            	*/
            	function unconvert_field($field, $return) {
            		if (preg_match("~binary~", $field["type"])) {
            			$return = "UNHEX($return)";
            		}
            		if ($field["type"] == "bit") {
            			$return = "CONV($return, 2, 10) + 0";
            		}
            		if (preg_match("~geometry|point|linestring|polygon~", $field["type"])) {
            			$return = "GeomFromText($return)";
            		}
            		return $return;
            	}
            	
            	
            
            	/** Remove current user definer from SQL command
            	 * @param string
            	 * @return string
            	 */
            	function remove_definer($query) {
            		return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\\1)', logged_user()) . '`~', '\\1', $query); //! proper escaping of user
            	}
            
            
            	/** Print SET NAMES if utf8mb4 might be needed
            	* @param string
            	* @return null
            	*/
            	function set_utf8mb4($create) {
            	  global $connection;
            		static $set = false;
            		if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
            			$set = true;
            			file_put_contents(DUMP_FILENAME, "SET NAMES " . charset($connection) . ";\n\n", FILE_APPEND);
            		}
            	}
            
            	
            	// PDO can be used in several database drivers
            	if (extension_loaded('pdo')) {
            		/*abstract*/ class Min_PDO extends PDO {
            			var $_result, $server_info, $affected_rows, $errno, $error;
            			
            			function __construct() {
            				global $adminer;
            				$pos = array_search("SQL", $adminer->operators);
            				if ($pos !== false) {
            					unset($adminer->operators[$pos]);
            				}
            			}
            			
            			function dsn($dsn, $username, $password) {
            				try {
            					parent::__construct($dsn, $username, $password);
            				} catch (Exception $ex) {
            					auth_error($ex->getMessage());
            				}
            				$this->setAttribute(13, array('Min_PDOStatement')); // 13 - PDO::ATTR_STATEMENT_CLASS
            				$this->server_info = $this->getAttribute(4); // 4 - PDO::ATTR_SERVER_VERSION
            			}
            			
            
            			function store_result($result = null) {
            				if (!$result) {
            					$result = $this->_result;
            					if (!$result) {
            						return false;
            					}
            				}
            				if ($result->columnCount()) {
            					$result->num_rows = $result->rowCount(); // is not guaranteed to work with all drivers
            					return $result;
            				}
            				$this->affected_rows = $result->rowCount();
            				return true;
            			}
            			
            				
            			
            			function query($query, $unbuffered = false) {
            				$result = parent::query($query);
            				$this->error = "";
            				if (!$result) {
            					list(, $this->errno, $this->error) = $this->errorInfo();
            					return false;
            				}
            				$this->store_result($result);
            				return $result;
            			}
            
            			function result($query, $field = 0) {
            				$result = $this->query($query);
            				if (!$result) {
            					return false;
            				}
            				$row = $result->fetch();
            				return $row[$field];
            			}
            		}
            		
            		class Min_PDOStatement extends PDOStatement {
            			var $_offset = 0, $num_rows;
            			
            			function fetch_assoc() {
            				return $this->fetch(2); // PDO::FETCH_ASSOC
            			}
            			
            			function fetch_row() {
            				return $this->fetch(3); // PDO::FETCH_NUM
            			}
            			
            			function fetch_field() {	
            				$row = (object) $this->getColumnMeta($this->_offset++);
            				$row->orgtable = $row->table;
            				$row->orgname = $row->name;
            				$row->charsetnr = (in_array("blob", (array) $row->flags) ? 63 : 0);
            				return $row;
            			}
            		}
            
            	}
            
            
            	if (extension_loaded("mysqli")) {
            		$VERSION = 'Ext: MYSQLI';
            		class Min_DB extends MySQLi {
            			var $extension = "MySQLi";
            
            			function __construct() {
            				parent::init();
            			}
            
            			function connect($server = "", $username = "", $password = "", $database = null, $port = null, $socket = null) {
            				mysqli_report(MYSQLI_REPORT_OFF); // stays between requests, not required since PHP 5.3.4
            				list($host, $port) = explode(":", $server, 2); // part after : is used for port or socket
            				$return = @$this->real_connect(
            					($server != "" ? $host : ini_get("mysqli.default_host")),
            					($server . $username != "" ? $username : ini_get("mysqli.default_user")),
            					($server . $username . $password != "" ? $password : ini_get("mysqli.default_pw")),
            					$database,
            					(is_numeric($port) ? $port : ini_get("mysqli.default_port")),
            					(!is_numeric($port) ? $port : $socket)
            				);
            				return $return;
            			}
            
            			function set_charset($charset) {
            				if (parent::set_charset($charset)) {
            					return true;
            				}
            				// the client library may not support utf8mb4
            				parent::set_charset('utf8');
            				return $this->query("SET NAMES $charset");
            			}
            
            			function result($query, $field = 0) {
            				$result = $this->query($query);
            				if (!$result) {
            					return false;
            				}
            				$row = $result->fetch_array();
            				return $row[$field];
            			}
            			
            			function quote($string) {
            				return "'" . $this->escape_string($string) . "'";
            			}
            		}
            
            	} elseif (extension_loaded("mysql") && !(ini_get("sql.safe_mode") && extension_loaded("pdo_mysql"))) {
            		$VERSION = 'Ext: MYSQL';
            		class Min_DB {
            			var
            				$extension = "MySQL", ///< @var string extension name
            				$server_info, ///< @var string server version
            				$affected_rows, ///< @var int number of affected rows
            				$errno, ///< @var int last error code
            				$error, ///< @var string last error message
            				$_link, $_result ///< @access private
            			;
            
            			/** Connect to server
            			* @param string
            			* @param string
            			* @param string
            			* @return bool
            			*/
            			function connect($server, $username, $password) {
            				$this->_link = @mysql_connect(
            					($server != "" ? $server : ini_get("mysql.default_host")),
            					("$server$username" != "" ? $username : ini_get("mysql.default_user")),
            					("$server$username$password" != "" ? $password : ini_get("mysql.default_password")),
            					true,
            					131072 // CLIENT_MULTI_RESULTS for CALL
            				);
            				if ($this->_link) {
            					$this->server_info = mysql_get_server_info($this->_link);
            				} else {
            					$this->error = mysql_error();
            				}
            				return (bool) $this->_link;
            			}
            
            			/** Sets the client character set
            			* @param string
            			* @return bool
            			*/
            			function set_charset($charset) {
            				if (function_exists('mysql_set_charset')) {
            					if (mysql_set_charset($charset, $this->_link)) {
            						return true;
            					}
            					// the client library may not support utf8mb4
            					mysql_set_charset('utf8', $this->_link);
            				}
            				return $this->query("SET NAMES $charset");
            			}
            
            			/** Quote string to use in SQL
            			* @param string
            			* @return string escaped string enclosed in '
            			*/
            			function quote($string) {
            				return "'" . mysql_real_escape_string($string, $this->_link) . "'";
            			}
            
            			/** Select database
            			* @param string
            			* @return bool
            			*/
            			function select_db($database) {
            				return mysql_select_db($database, $this->_link);
            			}
            
            			/** Send query
            			* @param string
            			* @param bool
            			* @return mixed bool or Min_Result
            			*/
            			function query($query, $unbuffered = false) {
            				$result = @($unbuffered ? mysql_unbuffered_query($query, $this->_link) : mysql_query($query, $this->_link)); // @ - mute mysql.trace_mode
            				$this->error = "";
            				if (!$result) {
            					$this->errno = mysql_errno($this->_link);
            					$this->error = mysql_error($this->_link);
            					return false;
            				}
            				if ($result === true) {
            					$this->affected_rows = mysql_affected_rows($this->_link);
            					$this->info = mysql_info($this->_link);
            					return true;
            				}
            				return new Min_Result($result);
            			}
            
            
            			/** Get single field from result
            			* @param string
            			* @param int
            			* @return string
            			*/
            			function result($query, $field = 0) {
            				$result = $this->query($query);
            				if (!$result || !$result->num_rows) {
            					return false;
            				}
            				return mysql_result($result->_result, 0, $field);
            			}
            		}
            
            		class Min_Result {
            			var
            				$num_rows, ///< @var int number of rows in the result
            				$_result, $_offset = 0 ///< @access private
            			;
            
            			/** Constructor
            			* @param resource
            			*/
            			function __construct($result) {
            				$this->_result = $result;
            				$this->num_rows = mysql_num_rows($result);
            			}
            
            			/** Fetch next row as associative array
            			* @return array
            			*/
            			function fetch_assoc() {
            				return mysql_fetch_assoc($this->_result);
            			}
            
            			/** Fetch next row as numbered array
            			* @return array
            			*/
            			function fetch_row() {
            				return mysql_fetch_row($this->_result);
            			}
            
            			/** Fetch next field
            			* @return object properties: name, type, orgtable, orgname, charsetnr
            			*/
            			function fetch_field() {
            				$return = mysql_fetch_field($this->_result, $this->_offset++); // offset required under certain conditions
            				$return->orgtable = $return->table;
            				$return->orgname = $return->name;
            				$return->charsetnr = ($return->blob ? 63 : 0);
            				return $return;
            			}
            
            			/** Free result set
            			*/
            			function __destruct() {
            				mysql_free_result($this->_result);
            			}
            		}
            
            	} elseif (extension_loaded("pdo_mysql")) {
            		$VERSION = 'Ext: PDO';
            		class Min_DB extends Min_PDO {
            			var $extension = "PDO_MySQL";
            
            			function connect($server, $username, $password) {
            				$this->dsn("mysql:charset=utf8;host=" . str_replace(":", ";unix_socket=", preg_replace('~:(\\d)~', ';port=\\1', $server)), $username, $password);
            				return true;
            			}
            
            			function set_charset($charset) {
            				$this->query("SET NAMES $charset"); // charset in DSN is ignored before PHP 5.3.6
            			}
            
            			function select_db($database) {
            				// database selection is separated from the connection so dbname in DSN can't be used
            				return $this->query("USE " . idf_escape($database));
            			}
            
            			function query($query, $unbuffered = false) {
            				$this->setAttribute(1000, !$unbuffered); // 1000 - PDO::MYSQL_ATTR_USE_BUFFERED_QUERY
            				return parent::query($query, $unbuffered);
            			}
            		}
            
            	} else {
            	    if (DEBUG_LOG) DebugLog('SQL Necessary extension not found!');
            	}
            
            
            
            
            	class Adminer {
            		/** @var array operators used in select, null for all operators */
            		var $operators;
            
            
            		/** Connection parameters
            		* @return array ($server, $username, $password)
            		*/
            		function credentials() {
            			return array(SERVER, USERNAME, PASSWORD);
            		}
            
            		/** Export table structure
            		* @param string
            		* @param string
            		* @param int 0 table, 1 view, 2 temporary view table
            		* @return null prints data
            		*/
            		function dumpTable($table, $style, $is_view = 0) {
            			if ($_POST["format"] != "sql") {
            						file_put_contents(DUMP_FILENAME, "\xef\xbb\xbf", FILE_APPEND);
            				if ($style) {
            					dump_csv(array_keys(fields($table)));
            				}
            			} else {
            				if ($is_view == 2) {
            					$fields = array();
            					foreach (fields($table) as $name => $field) {
            						$fields[] = idf_escape($name) . " $field[full_type]";
            					}
            					$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
            				} else {
            					$create = create_sql($table, $_POST["auto_increment"]);
            				}
            				set_utf8mb4($create);
            				if ($style && $create) {
            					if ($style == "DROP+CREATE" || $is_view == 1) {
            						file_put_contents(DUMP_FILENAME, "DROP " . ($is_view == 2 ? "VIEW" : "TABLE") . " IF EXISTS " . table($table) . ";\n", FILE_APPEND);
            					}
            					if ($is_view == 1) {
            						$create = remove_definer($create);
            					}
            					file_put_contents(DUMP_FILENAME, "$create;\n\n", FILE_APPEND);
            				}
            			}
            		}
            
            		/** Export table data
            		* @param string
            		* @param string
            		* @param string
            		* @return null prints data
            		*/
            		function dumpData($table, $style, $query) {
            			global $connection, $jush;
            			$max_packet = ($jush == "sqlite" ? 0 : 1048576); // default, minimum is 1024
            			if ($style) {
            				if ($_POST["format"] == "sql") {
            					if ($style == "TRUNCATE+INSERT") {
            						file_put_contents(DUMP_FILENAME, truncate_sql($table) . ";\n", FILE_APPEND);
            					}
            					$fields = fields($table);
            				}
            				$result = $connection->query($query, 1); // 1 - MYSQLI_USE_RESULT //! enum and set as numbers
            				if ($result) {
            					$insert = "";
            					$buffer = "";
            					$keys = array();
            					$suffix = "";
            					$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
            					while ($row = $result->$fetch_function()) {
            						if (!$keys) {
            							$values = array();
            							foreach ($row as $val) {
            								$field = $result->fetch_field();
            								$keys[] = $field->name;
            								$key = idf_escape($field->name);
            								$values[] = "$key = VALUES($key)";
            							}
            							$suffix = ($style == "INSERT+UPDATE" ? "\nON DUPLICATE KEY UPDATE " . implode(", ", $values) : "") . ";\n";
            						}
            						if ($_POST["format"] != "sql") {
            							if ($style == "table") {
            								dump_csv($keys);
            								$style = "INSERT";
            							}
            							dump_csv($row);
            						} else {
            							if (!$insert) {
            								$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('idf_escape', $keys)) . ") VALUES";
            							}
            							foreach ($row as $key => $val) {
            								$field = $fields[$key];
            								$row[$key] = ($val !== null
            									? unconvert_field($field, preg_match('~(^|[^o])int|float|double|decimal~', $field["type"]) && $val != '' ? $val : q($val))
            									: "NULL"
            								);
            							}
            							$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
            							if (!$buffer) {
            								$buffer = $insert . $s;
            							} elseif (strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet) { // 4 - length specification
            								$buffer .= ",$s";
            							} else {
            								file_put_contents(DUMP_FILENAME, $buffer . $suffix, FILE_APPEND);
            								$buffer = $insert . $s;
            							}
            						}
            					}
            					if ($buffer) {
            						file_put_contents(DUMP_FILENAME, $buffer . $suffix, FILE_APPEND);
            					}
            				} elseif ($_POST["format"] == "sql") {
            					file_put_contents(DUMP_FILENAME, "-- " . str_replace("\n", " ", $connection->error) . "\n", FILE_APPEND);
            				}
            			}
            		}
            
            		/** Set export filename
            		* @param string
            		* @return string filename without extension
            		*/
            		function dumpFilename($identifier) {
            			return friendly_url($identifier != "" ? $identifier : (SERVER != "" ? SERVER : "localhost"));
            		}
            
            	}
            
            
                if (DEBUG_LOG) DebugLog('Start task Backup_SQL', true);
                
                // Remove old backups
                if (file_exists(BACKUP_FILENAME_SQL)) unlink(BACKUP_FILENAME_SQL);
                if (file_exists(BACKUP_FILENAME_SQL.".zip")) unlink(BACKUP_FILENAME_SQL.".zip");
                
                $host = 'localhost';
                if (defined('SQL_DB_HOST')) $host = SQL_DB_HOST;
                $sql_user = '';
                if (defined('SQL_DB_USER')) $sql_user = SQL_DB_USER;
                $sql_pass = '';
                if (defined('SQL_DB_PASSWORD')) $sql_pass = SQL_DB_PASSWORD;
                $database = ''; 
                if (defined('SQL_DB_NAME')) $database = SQL_DB_NAME;
                
                
                if ($sql_user == '' || $sql_pass == '' || $database == '')
                {
					if (file_exists(SCAN_PATH.'index.php')) $indexContentArray = file(SCAN_PATH.'index.php');
					foreach ($indexContentArray as $currentLine) {
						if (!strpos($currentLine, "blog-header.php")) continue;
						$line = $currentLine;
					}
					
                    // Check for Joomla and WordPress CMS
					if ($line || file_exists(SCAN_PATH.'wp-config.php')) {
						
						// WordPress
						if (DEBUG_LOG) DebugLog('WordPress CMS found');
						
						if (file_exists(SCAN_PATH.'wp-config.php'))
						{
							$config_data = file(SCAN_PATH.'wp-config.php');
							$confic_php_code = '';
							foreach ($config_data as $row)
							{
								if (strpos($row, "DB_") !== false) 
								{ 
									$confic_php_code .= $row."\n";
								}
							}
							eval($confic_php_code);
							if (defined('DB_NAME')) $database = DB_NAME;
							if (defined('DB_USER')) $sql_user = DB_USER;
							if (defined('DB_PASSWORD')) $sql_pass = DB_PASSWORD;
							if (defined('DB_HOST')) $host = DB_HOST;
						}
						elseif ($line) 
						{
							$start = strpos($line, "/") + 1;
							$line = substr($line, $start);
							$line = trim(str_replace(array("'", "\"", ")", ";", "\r", "\n"), "", $line));
							$line = str_replace(array("/", "\\"), DIRSEP, $line);
							$pathToFile = SCAN_PATH . str_replace("blog-header", "config", $line);
							if (file_exists($pathToFile)) 
							{
								$config_data = file($pathToFile);
								$confic_php_code = '';
								foreach ($config_data as $row)
								{
									if (strpos($row, "DB_") !== false) 
									{ 
										$confic_php_code .= $row."\n";
									}
								}
								eval($confic_php_code);
								if (defined('DB_NAME')) $database = DB_NAME;
								if (defined('DB_USER')) $sql_user = DB_USER;
								if (defined('DB_PASSWORD')) $sql_pass = DB_PASSWORD;
								if (defined('DB_HOST')) $host = DB_HOST;
							}
						}
					}
 
                    if (file_exists(SCAN_PATH.'configuration.php'))
                    {
                        // Joomla
                        if (DEBUG_LOG) DebugLog('Joomla CMS found');
                        
                        include(SCAN_PATH.'configuration.php');
                        
                        $db = new JConfig();
                        $host = $db->host;
                        $sql_user = $db->user;
                        $sql_pass = $db->password;
                        $database = $db->db;
                    }
            		
            		if(file_exists(SCAN_PATH."app/etc/local.xml"))
            		{
            			// Magento
                        if (DEBUG_LOG) DebugLog('Magento CMS found');
            			
            			$xml = simplexml_load_file(SCAN_PATH.'app/etc/local.xml');
            			if ($xml !== false)
            			{
            				$host =  $xml->global->resources->default_setup->connection->host;
            				$sql_user =  $xml->global->resources->default_setup->connection->username;
            				$sql_pass =  $xml->global->resources->default_setup->connection->password;
            				$database =  $xml->global->resources->default_setup->connection->dbname;
            			}
            		}
                    
                	if (file_exists(SCAN_PATH.'config.php') && file_exists(SCAN_PATH.'vqmod/vqmod.php'))
                    {
                        // Opencart
                        if (DEBUG_LOG) DebugLog('Opencart CMS found');
                        
                        include(SCAN_PATH.'config.php');
                        
                        $host = DB_HOSTNAME;
                        $sql_user = DB_USERNAME;
                        $sql_pass = DB_PASSWORD;
                        $database = DB_DATABASE;
                    }
					
					if(file_exists(SCAN_PATH . "application" . DIRSEP . "config" . DIRSEP . "database.php")){
						
						// CodeIgniter						
						if (DEBUG_LOG) DebugLog('CodeIgniter CMS found');
						
						define('BASEPATH', 'BASEPATH');
						include_once(SCAN_PATH . "application" . DIRSEP . "config" . DIRSEP . "database.php");
						$host =  $db['default']['hostname'];
						$sql_user =  $db['default']['username'];
						$sql_pass =  $db['default']['password'];
						$database =  $db['default']['database'];
						
					}
					
					if(file_exists(SCAN_PATH . "include" . DIRSEP . "config.inc.php")){
						
						// Coppermine
						if (DEBUG_LOG) DebugLog('Coppermine CMS found');
						
						include_once(SCAN_PATH . "include" . DIRSEP . "config.inc.php");
						$host =  HOST;
						$sql_user =  USER;
						$sql_pass =  PASSWORD;
						$database =  DATABASE;
						
					}
					
					if(file_exists(SCAN_PATH . "includes" . DIRSEP . "configure.php")){
						
						// Smarty
						if (DEBUG_LOG) DebugLog('Smarty CMS found');
						
						include_once(SCAN_PATH . "includes" . DIRSEP . "configure.php");
						$host =  DB_SERVER;
						$sql_user =  DB_SERVER_USERNAME;
						$sql_pass =  DB_SERVER_PASSWORD;
						$database =  DB_DATABASE;
						
					}

					if(file_exists(SCAN_PATH . "app" . DIRSEP . "etc" . DIRSEP . "env.php")){
						
						//Magento 2
						if (DEBUG_LOG) DebugLog('Magento 2 CMS found');
						
						$data = include_once(SCAN_PATH . "app" . DIRSEP . "etc" . DIRSEP . "env.php");
						$host =  $data['db']['connection']['default']['host'];
						$sql_user =  $data['db']['connection']['default']['username'];
						$sql_pass =  $data['db']['connection']['default']['password'];
						$database =  $data['db']['connection']['default']['dbname'];
						
					}		
                }
                    
                    $db_port = '3306';
                    if (strpos($host, ":") !== false)
                    {
                        $host = explode(":", $host);
                        $db_port = intval($host[1]);
                        if ($db_port == 0) $db_port = '3306';
                        $host = trim($host[0]);
                    }
                    
                    if ($db_port == '3306') $db_id = @mysqli_connect( $host, $sql_user, $sql_pass, $database );
                    else  $db_id = @mysqli_connect( $host, $sql_user, $sql_pass, $database, $db_port );
                    
                    if (!$db_id)
                    {
                        if (DEBUG_LOG) DebugLog('Connect to SQL is failed [HOST: '.$host.', USER: '.$sql_user.', PASS: '.$sql_pass.', DB: '.$database.', port: '.$db_port.']');
                        $result = false;
                        PrintResultOutput('Executed backup_sql', $result);
                        break;
                    }
                    else if (DEBUG_LOG) DebugLog('Connected to SQL');
                    
                    // Combine host:port
                    if ($db_port != '3306') $host = $host.":".$db_port;
                    
                    if (DEBUG_LOG) DebugLog('BACKUP_FILENAME_SQL='.BACKUP_FILENAME_SQL);
                    
                    if (DEBUG_LOG) DebugLog('Try SQL method: mysqldump');
                    $result=exec('mysqldump '.$database.' --password='.$sql_pass.' --user='.$sql_user.' --host='.$host.' --single-transaction >'.BACKUP_FILENAME_SQL, $output);
                    
                    sleep(5);
                    
                    
                    if (file_exists(BACKUP_FILENAME_SQL) && filesize(BACKUP_FILENAME_SQL) > 10000)
                    {
                        // Finished OK
                        if (DEBUG_LOG) DebugLog('SQL mysqldump - OK');
                        $result = true;
                    }
                    else {
                        // Try method 2
            
                        if (DEBUG_LOG) DebugLog('Try SQL method: adminer');
                    	define('DUMP_FILENAME', BACKUP_FILENAME_SQL);
                    	define('SERVER', $host);
                    	define('USERNAME', $sql_user);
                    	define('PASSWORD', $sql_pass);
                    	define('DB', $database);
                    
                
                    
                    	$adminer = new Adminer;
                    
                    	$connection = connect();
                    	$connection->select_db(DB);
                    	$tables = tables_list();
                    
                    	$_POST = array(
                    		'output' => 'text',
                    		'format' => 'sql',
                    		'table_style' => 'DROP+CREATE',
                    		'data_style' => 'INSERT',
                    		'tables' => array_keys($tables),
                    		'data' => array_keys($tables),
                    	);
                    
                    	
                    	file_put_contents(DUMP_FILENAME, '');
                    
                    
                    	if ($_POST && !$error) {
                    		$cookie = "";
                    		foreach (array("output", "format", "db_style", "table_style", "auto_increment", "triggers", "data_style") as $key) {
                    			$cookie .= "&$key=" . urlencode($_POST[$key]);
                    		}
                    
                    		$tables = array_flip((array) $_POST["tables"]) + array_flip((array) $_POST["data"]);
                    		$is_sql = preg_match('~sql~', $_POST["format"]);
                    
                    		if ($is_sql) {
                    			file_put_contents(DUMP_FILENAME, "-- Adminer Mysql DB dump. $VERSION\n\n", FILE_APPEND);
                    				file_put_contents(DUMP_FILENAME, "SET NAMES utf8;
                    	SET time_zone = '+00:00';
                    	" . ($_POST["data_style"] ? "SET foreign_key_checks = 0;
                    	SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
                    	" : "") . "
                    	", FILE_APPEND);
                    				$connection->query("SET time_zone = '+00:00';");
                    
                    		}
                    
                    		$databases = array(DB);
                    		if (DB == "") {
                    			$databases = $_POST["databases"];
                    			if (is_string($databases)) {
                    				$databases = explode("\n", rtrim(str_replace("\r", "", $databases), "\n"));
                    			}
                    		}
                    
                    		foreach ((array) $databases as $db) {
                    
                    			if ($connection->select_db($db)) {
                    
                    				if ($_POST["table_style"] || $_POST["data_style"]) {
                    					$views = array();
                    					foreach (table_status('', true) as $name => $table_status) {
                    						$table = (DB == "" || in_array($name, (array) $_POST["tables"]));
                    						$data = (DB == "" || in_array($name, (array) $_POST["data"]));
                    						if ($table || $data) {
                    							$adminer->dumpTable($name, ($table ? $_POST["table_style"] : ""), (is_view($table_status) ? 2 : 0));
                    							if (is_view($table_status)) {
                    								$views[] = $name;
                    							} elseif ($data) {
                    								$fields = fields($name);
                    								$adminer->dumpData($name, $_POST["data_style"], "SELECT *" . convert_fields($fields, $fields) . " FROM " . table($name));
                    							}
                    							if ($is_sql) {
                    								file_put_contents(DUMP_FILENAME, "\n", FILE_APPEND);
                    							}
                    						}
                    					}
                    
                    					foreach ($views as $view) {
                    						$adminer->dumpTable($view, $_POST["table_style"], 1);
                    					}
                    
                    				}
                    			}
                    		}
                    
                    		if ($is_sql) {
                    			file_put_contents(DUMP_FILENAME, "-- " . $connection->result("SELECT NOW()") . "\n", FILE_APPEND);
                    		}
                
                
                        sleep(5);
                        
                        
                        if (file_exists(BACKUP_FILENAME_SQL) && filesize(BACKUP_FILENAME_SQL) > 10000)
                        {
                            // Finished OK
                            if (DEBUG_LOG) DebugLog('SQL adminer - OK');
                            $result = true;
                        }
                        else {
                            // Finished with error
                            if (DEBUG_LOG) DebugLog('SQL adminer - Error');
                            $result = false;
                        }
                    
                    }
                    else {
                            // Finished with error
                            if (DEBUG_LOG) DebugLog('SQL adminer - Error 2');
                            $result = false;
                        }
                }



		//$result = Backup_SQL();
        if ($result === true) ZipFiles(BACKUP_FILENAME_SQL.'.zip', array(BACKUP_FILENAME_SQL));
		PrintResultOutput('Executed backup_sql', $result);
		break;
    
    // */webanalyze/backup.php?task=backup_files&session_key=e43c132d47dd6c5013b19a0f7fa83f25    
    case 'backup_files':
		$result = Backup_Files(array_merge($BACKUP_EXCLUDE_FOLDERS, $CONFIG_EXCLUDE_FOLDERS));
		PrintResultOutput($result, true);
		break;
        
	default:
		PrintResultOutput('Wrong Task', false);	
}
exit;



/**
 * Functions
 */
 
 
function GetStatus()
{
    $a = array();
    $a['version'] = VERSION;
    
    if (file_exists(BACKUP_FILENAME_SQL) && filesize(BACKUP_FILENAME_SQL) > 10000) $a['sql'] = basename(BACKUP_FILENAME_SQL);
    if (file_exists(BACKUP_FILENAME_SQL.".zip") && filesize(BACKUP_FILENAME_SQL.".zip") > 10000) $a['sql_zip'] = basename(BACKUP_FILENAME_SQL.".zip");
    if (file_exists(BACKUP_FILENAME_FILES_TAR) && filesize(BACKUP_FILENAME_FILES_TAR) > 10000) $a['backup'][] = basename(BACKUP_FILENAME_FILES_TAR);
    if (file_exists(BACKUP_FILENAME_FILES_ZIP) && filesize(BACKUP_FILENAME_FILES_ZIP) > 10000) $a['backup'][] = basename(BACKUP_FILENAME_FILES_ZIP);
    
    return $a;
}

function Backup_Files($exclude_folders = array())
{
    // Remove old backups
    if (file_exists(BACKUP_FILENAME_FILES_TAR)) unlink(BACKUP_FILENAME_FILES_TAR);
    if (file_exists(BACKUP_FILENAME_FILES_ZIP)) unlink(BACKUP_FILENAME_FILES_ZIP);
    
	foreach ($exclude_folders as $k => $ex_folder)
	{
		$ex_folder = SCAN_PATH.trim($ex_folder);
		$exclude_folders[$k] = str_replace(DIRSEP.DIRSEP, DIRSEP, $ex_folder);
	}
    
    if (DEBUG_LOG && count($exclude_folders) > 0) DebugLog('Exclude folders :'."\n".print_r($exclude_folders, true));
    if (DEBUG_LOG && defined('BACKUP_EXCLUDE_FILE_EXTENSIONS')) DebugLog('Exclude file extensions :'.BACKUP_EXCLUDE_FILE_EXTENSIONS);
    
    $scanner = new SiteGuarding_files();
    $scanner->scan_path = SCAN_PATH;
    $files_list = $scanner->GetFileList($exclude_folders);
    
 	$ssh_flag = false;
	if ( function_exists('exec') ) 
	{
		// Pack files with ssh 
		$ssh_flag = true;
	}
    
    if (count($files_list) == 0)
    {
        if (DEBUG_LOG) DebugLog('Empty file list');
        return false;
    }
	$fp = fopen(BACKUP_FILELIST, 'w');
	$status = fwrite($fp, implode("\n", $files_list));
	fclose($fp);
	if ($status === false)
	{
		// Turn ZIP mode
		$ssh_flag = false;
	}
    

	if ($ssh_flag)
	{
		// SSH way
			$error_msg = 'Start - Pack with SSH';
			if (DEBUG_LOG) DebugLog($error_msg);
			
		$cmd = 'cd '.SCAN_PATH.''."\n".'tar -czf '.BACKUP_FILENAME_FILES_TAR.' -T '.BACKUP_FILELIST;
		$output = array();
		$result = exec($cmd, $output);
		
		if (file_exists(BACKUP_FILENAME_FILES_TAR) === false) 
		{
			$ssh_flag = false;
			
			$error_msg = 'Change pack method from SSH to PHP (ZipArchive)';
			if (DEBUG_LOG) DebugLog($error_msg);
		}
	}
    
	if (!$ssh_flag) 
	{
		// PHP way
			$error_msg = 'Start - Pack with ZipArchive';
			if (DEBUG_LOG) DebugLog($error_msg);
		
	    	$file_zip = BACKUP_FILENAME_FILES_ZIP;
	    	if (file_exists($file_zip)) unlink($file_zip);
	    	$pack_dir = $scan_path;
            
            	
	    if (class_exists('ZipArchive'))
	    {
	        // open archive
	        $zip = new ZipArchive;
	        if ($zip->open($file_zip, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) === TRUE) 
	        {
	            foreach ($files_list as $file_name) 
	            {
	            	//$file_name = $this->scan_path.$file_name;
	                if( strstr(realpath($file_name), "stark") == FALSE) 
					{
						$short_key = str_replace(SCAN_PATH, "", $file_name);
	                	$s = $zip->addFile(realpath($file_name), $short_key);
		                if (!$s) 
		                {
		                	$error_msg = 'Couldnt add file: '.$file_name; 
		                	if (DEBUG_LOG) DebugLog($error_msg);
		                }
	            	}
					
				}
	             // close and save archive
	            $zip->close();
	            
	            //$result['msg'][] = 'Archive created successfully'; 
	        }
	        else {
	        	$error_msg = 'Error: Couldnt open ZIP archive.';
				if (DEBUG_LOG) DebugLog($error_msg);
				return false;
	        }
	
	    }
	    else {
	    	$error_msg =  'Error: ZipArchive class is not exist.';
	        if (DEBUG_LOG) DebugLog($error_msg);
	    }
	    
		$error_msg = 'ZipArchive method - finished';
		if (DEBUG_LOG) DebugLog($error_msg);
		
		// Check if zip file exists
		if (!file_exists($file_zip))
		{
			$error_msg = 'Error: zip file is not exists. Use OwnZipClass';
			if (DEBUG_LOG) DebugLog($error_msg);
			
			$error_msg = 'OwnZipClass method - started';
			if (DEBUG_LOG) DebugLog($error_msg);
            
				$zip = new Zip();
				$zip->setZipFile($file_zip);
	            foreach ($files_list as $file_name) 
	            {
	            	$handle = fopen($file_name, "r");
	            	if (filesize($file_name) > 0) $zip->addFile(fread($handle, filesize($file_name)), $file_name, filectime($file_name), NULL, TRUE, Zip::getFileExtAttr($file_name));
	            	fclose($handle);
	           	}
	           	$zip->finalize();
           	
			$error_msg = 'OwnZipClass method - finished';
			if (DEBUG_LOG) DebugLog($error_msg);
            
            $ssh_flag = false; 
		}
		
	}
    
    if ($ssh_flag)
    {
        if (file_exists(BACKUP_FILENAME_FILES_TAR) && filesize(BACKUP_FILENAME_FILES_TAR) > 10000)
        {
            // Finished OK
            if (DEBUG_LOG) DebugLog('Backup (tar) - OK');
            return true;
        }
        else {
            // File backup sql is absent
            if (DEBUG_LOG) DebugLog('Backup (tar) - Error');
            return false;    
        } 
    }
    else
    {
        if (file_exists(BACKUP_FILENAME_FILES_ZIP) && filesize(BACKUP_FILENAME_FILES_ZIP) > 10000)
        {
            // Finished OK
            if (DEBUG_LOG) DebugLog('Backup (zip) - OK');
            return true;
        }
        else {
            // File backup sql is absent
            if (DEBUG_LOG) DebugLog('Backup (zip) - Error');
            return false;    
        } 
    }
}



function ZipFiles($file_zip, $files = array())
{
    if (count($files) == 0) return;
    
	$zip = new Zip();
	$zip->setZipFile($file_zip);
    foreach ($files as $file_name) 
    {
    	$handle = fopen($file_name, "r");
    	if (filesize($file_name) > 0) $zip->addFile(fread($handle, filesize($file_name)), $file_name, filectime($file_name), NULL, TRUE, Zip::getFileExtAttr($file_name));
    	fclose($handle);
   	}
   	$zip->finalize();
}


function DebugLog($txt, $clean_log_file = false)
{
    if ($txt == 'line') $txt = '-----------------------------------------------------------------------';
	if ($clean_log_file) $fp = fopen(dirname(__FILE__).DIRSEP.'backup_debug_'.md5(WEBSITE_KEY).'.log', 'w');
	else $fp = fopen(dirname(__FILE__).DIRSEP.'backup_debug_'.md5(WEBSITE_KEY).'.log', 'a');
	$a = date("Y-m-d H:i:s")." ".$txt."\n";
	fwrite($fp, $a);
	fclose($fp);
}

function Shutdown_result()
{
    if (DEBUG_LOG) DebugLog('line');
    
    $reason = error_get_last();
    if (DEBUG_LOG) DebugLog(print_r($reason, true));
	if (DEBUG_LOG) DebugLog('PHP process has been terminated');
}



function Backup_SQL()
{
    // Moved to body part
}








function PrintResultOutput($msg, $type = false) // false = error, true - ok
{
    if ($type)
    {
        // Success
        if (is_array($msg))
        {
        	$a = $msg;
			$a['status'] = 'ok';
        }
        else {
        	$a = array(
            	'status' => 'ok',
            	'msg' => $msg
        	);
       	}
    } 
    else {
        // Error
        if (is_array($msg))
        {
        	$a = $msg;
			$a['status'] = 'error';
        }
        else {
        	$a = array(
            	'status' => 'error',
            	'msg' => $msg
        	);
       	}
    }
    
    echo json_encode($a);
}



class SiteGuarding_files
{
    var $scan_path = '';
    var $exclude_folders_real = array();

    
    
	public function GetFileList($exclude_folders = array())
	{
	    // Start scanning process
        if (!defined('DIRSEP'))
        {
    	    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define('DIRSEP', '\\');
    		else define('DIRSEP', '/');
        }
        
        $scan_path = $this->scan_path;

        $this->exclude_folders_real = $exclude_folders; 
        
        
        $files_list = array();
        $dirList = array();
        $dirList[] = $scan_path;
        
 
                
        // Scan all dirs
        while (true) 
        {
            $dirList = array_merge(self::ScanFolder(array_shift($dirList), $files_list), $dirList);
            if (count($dirList) < 1) break;
        }
        
        return $files_list;
        
    }
    
    
    
    
    
    function ScanFolder($path, &$files_list)
    {
        $dirList = array();
    
        if ($currentDir = opendir($path)) 
        {
            while ( false !== ( $file = readdir($currentDir) ) )
            {
                if ( $file === '.' || $file === '..' || is_link($path) ) continue;
                $file = $path . '/' . $file;
    
                
                if (is_dir($file)) 
                {
                    $folder = $file.DIRSEP;
                    $folder = str_replace(DIRSEP.DIRSEP, DIRSEP, $folder);
                    
                    // Exclude folders
                    if (count($this->exclude_folders_real))
                    {
                 		if (in_array($folder, $this->exclude_folders_real)) 
        				{
                            continue;
        				}
                    }
                    $dirList[] = $file;
                }
                else {
                    
                    if (is_link($file))
                    {
                        continue;
                    }
                    
                    // Add file
                    if (defined('BACKUP_EXCLUDE_FILE_EXTENSIONS') && BACKUP_EXCLUDE_FILE_EXTENSIONS != "")
                    {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $ext = "*.".$ext.";";
                        if (stripos(BACKUP_EXCLUDE_FILE_EXTENSIONS, $ext) !== false) continue;     // exclude this file
                    }
                    
                    if (filesize($file) <= MAX_FILE_SIZE) $files_list[] = $file;
                    
                }
                

            }
            closedir($currentDir);
        }
    
        return $dirList;
    }


}



class Zip {
    const VERSION = 1.62;

    const ZIP_LOCAL_FILE_HEADER = "\x50\x4b\x03\x04"; // Local file header signature
    const ZIP_CENTRAL_FILE_HEADER = "\x50\x4b\x01\x02"; // Central file header signature
    const ZIP_END_OF_CENTRAL_DIRECTORY = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record

    const EXT_FILE_ATTR_DIR = 010173200020;  // Permission 755 drwxr-xr-x = (((S_IFDIR | 0755) << 16) | S_DOS_D);
    const EXT_FILE_ATTR_FILE = 020151000040; // Permission 644 -rw-r--r-- = (((S_IFREG | 0644) << 16) | S_DOS_A);

    const ATTR_VERSION_TO_EXTRACT = "\x14\x00"; // Version needed to extract
    const ATTR_MADE_BY_VERSION = "\x1E\x03"; // Made By Version

	// UID 1000, GID 0
	const EXTRA_FIELD_NEW_UNIX_GUID = "\x75\x78\x0B\x00\x01\x04\xE8\x03\x00\x00\x04\x00\x00\x00\x00";

	// Unix file types
	const S_IFIFO  = 0010000; // named pipe (fifo)
	const S_IFCHR  = 0020000; // character special
	const S_IFDIR  = 0040000; // directory
	const S_IFBLK  = 0060000; // block special
	const S_IFREG  = 0100000; // regular
	const S_IFLNK  = 0120000; // symbolic link
	const S_IFSOCK = 0140000; // socket

	// setuid/setgid/sticky bits, the same as for chmod:

	const S_ISUID  = 0004000; // set user id on execution
	const S_ISGID  = 0002000; // set group id on execution
	const S_ISTXT  = 0001000; // sticky bit

	// And of course, the other 12 bits are for the permissions, the same as for chmod:
	// When addding these up, you can also just write the permissions as a simgle octal number
	// ie. 0755. The leading 0 specifies octal notation.
	const S_IRWXU  = 0000700; // RWX mask for owner
	const S_IRUSR  = 0000400; // R for owner
	const S_IWUSR  = 0000200; // W for owner
	const S_IXUSR  = 0000100; // X for owner
	const S_IRWXG  = 0000070; // RWX mask for group
	const S_IRGRP  = 0000040; // R for group
	const S_IWGRP  = 0000020; // W for group
	const S_IXGRP  = 0000010; // X for group
	const S_IRWXO  = 0000007; // RWX mask for other
	const S_IROTH  = 0000004; // R for other
	const S_IWOTH  = 0000002; // W for other
	const S_IXOTH  = 0000001; // X for other
	const S_ISVTX  = 0001000; // save swapped text even after use

	// Filetype, sticky and permissions are added up, and shifted 16 bits left BEFORE adding the DOS flags.

	// DOS file type flags, we really only use the S_DOS_D flag.

	const S_DOS_A  = 0000040; // DOS flag for Archive
	const S_DOS_D  = 0000020; // DOS flag for Directory
	const S_DOS_V  = 0000010; // DOS flag for Volume
	const S_DOS_S  = 0000004; // DOS flag for System
	const S_DOS_H  = 0000002; // DOS flag for Hidden
	const S_DOS_R  = 0000001; // DOS flag for Read Only

    private $zipMemoryThreshold = 1048576; // Autocreate tempfile if the zip data exceeds 1048576 bytes (1 MB)

    private $zipData = NULL;
    private $zipFile = NULL;
    private $zipComment = NULL;
    private $cdRec = array(); // central directory
    private $offset = 0;
    private $isFinalized = FALSE;
    private $addExtraField = TRUE;

    private $streamChunkSize = 65536;
    private $streamFilePath = NULL;
    private $streamTimestamp = NULL;
    private $streamFileComment = NULL;
    private $streamFile = NULL;
    private $streamData = NULL;
    private $streamFileLength = 0;
	private $streamExtFileAttr = null;
	/**
	 * A custom temporary folder, or a callable that returns a custom temporary file.
	 * @var string|callable
	 */
	public static $temp = null;

    /**
     * Constructor.
     *
     * @param boolean $useZipFile Write temp zip data to tempFile? Default FALSE
     */
    function __construct($useZipFile = FALSE) {
        if ($useZipFile) {
            $this->zipFile = tmpfile();
        } else {
            $this->zipData = "";
        }
    }

    function __destruct() {
        if (is_resource($this->zipFile)) {
            fclose($this->zipFile);
        }
        $this->zipData = NULL;
    }

    /**
     * Extra fields on the Zip directory records are Unix time codes needed for compatibility on the default Mac zip archive tool.
     * These are enabled as default, as they do no harm elsewhere and only add 26 bytes per file added.
     *
     * @param bool $setExtraField TRUE (default) will enable adding of extra fields, anything else will disable it.
     */
    function setExtraField($setExtraField = TRUE) {
        $this->addExtraField = ($setExtraField === TRUE);
    }

    /**
     * Set Zip archive comment.
     *
     * @param string $newComment New comment. NULL to clear.
     * @return bool $success
     */
    public function setComment($newComment = NULL) {
        if ($this->isFinalized) {
            return FALSE;
        }
        $this->zipComment = $newComment;

        return TRUE;
    }

    /**
     * Set zip file to write zip data to.
     * This will cause all present and future data written to this class to be written to this file.
     * This can be used at any time, even after the Zip Archive have been finalized. Any previous file will be closed.
     * Warning: If the given file already exists, it will be overwritten.
     *
     * @param string $fileName
     * @return bool $success
     */
    public function setZipFile($fileName) {
        if (is_file($fileName)) {
            unlink($fileName);
        }
        $fd=fopen($fileName, "x+b");
        if (is_resource($this->zipFile)) {
            rewind($this->zipFile);
            while (!feof($this->zipFile)) {
                fwrite($fd, fread($this->zipFile, $this->streamChunkSize));
            }

            fclose($this->zipFile);
        } else {
            fwrite($fd, $this->zipData);
            $this->zipData = NULL;
        }
        $this->zipFile = $fd;

        return TRUE;
    }

    /**
     * Add an empty directory entry to the zip archive.
     * Basically this is only used if an empty directory is added.
     *
     * @param string $directoryPath Directory Path and name to be added to the archive.
     * @param int    $timestamp     (Optional) Timestamp for the added directory, if omitted or set to 0, the current time will be used.
     * @param string $fileComment   (Optional) Comment to be added to the archive for this directory. To use fileComment, timestamp must be given.
	 * @param int    $extFileAttr   (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addDirectory($directoryPath, $timestamp = 0, $fileComment = NULL, $extFileAttr = self::EXT_FILE_ATTR_DIR) {
        if ($this->isFinalized) {
            return FALSE;
        }
        $directoryPath = str_replace("\\", "/", $directoryPath);
        $directoryPath = rtrim($directoryPath, "/");

        if (strlen($directoryPath) > 0) {
            $this->buildZipEntry($directoryPath.'/', $fileComment, "\x00\x00", "\x00\x00", $timestamp, "\x00\x00\x00\x00", 0, 0, $extFileAttr);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Add a file to the archive at the specified location and file name.
     *
     * @param string $data        File data.
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
     * @param bool   $compress    (Optional) Compress file, if set to FALSE the file will only be stored. Default TRUE.
	 * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addFile($data, $filePath, $timestamp = 0, $fileComment = NULL, $compress = TRUE, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if ($this->isFinalized) {
            return FALSE;
        }

        if (is_resource($data) && get_resource_type($data) == "stream") {
            $this->addLargeFile($data, $filePath, $timestamp, $fileComment, $extFileAttr);
            return FALSE;
        }

        $gzData = "";
        $gzType = "\x08\x00"; // Compression type 8 = deflate
        $gpFlags = "\x00\x00"; // General Purpose bit flags for compression type 8 it is: 0=Normal, 1=Maximum, 2=Fast, 3=super fast compression.
        $dataLength = strlen($data);
        $fileCRC32 = pack("V", crc32($data));

        if ($compress) {
            $gzTmp = gzcompress($data);
            $gzData = substr(substr($gzTmp, 0, strlen($gzTmp) - 4), 2); // gzcompress adds a 2 byte header and 4 byte CRC we can't use.
            // The 2 byte header does contain useful data, though in this case the 2 parameters we'd be interrested in will always be 8 for compression type, and 2 for General purpose flag.
            $gzLength = strlen($gzData);
        } else {
            $gzLength = $dataLength;
        }

        if ($gzLength >= $dataLength) {
            $gzLength = $dataLength;
            $gzData = $data;
            $gzType = "\x00\x00"; // Compression type 0 = stored
            $gpFlags = "\x00\x00"; // Compression type 0 = stored
        }

        if (!is_resource($this->zipFile) && ($this->offset + $gzLength) > $this->zipMemoryThreshold) {
            $this->zipflush();
        }

        $this->buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr);

        $this->zipwrite($gzData);

        return TRUE;
    }

    /**
     * Add the content to a directory.
     *
     * @author Adam Schmalhofer <Adam.Schmalhofer@gmx.de>
     * @author A. Grandt
     *
     * @param string $realPath       Path on the file system.
     * @param string $zipPath        Filepath and name to be used in the archive.
     * @param bool   $recursive      Add content recursively, default is TRUE.
     * @param bool   $followSymlinks Follow and add symbolic links, if they are accessible, default is TRUE.
     * @param array &$addedFiles     Reference to the added files, this is used to prevent duplicates, efault is an empty array.
     *                               If you start the function by parsing an array, the array will be populated with the realPath
     *                               and zipPath kay/value pairs added to the archive by the function.
	 * @param bool   $overrideFilePermissions Force the use of the file/dir permissions set in the $extDirAttr
	 *							     and $extFileAttr parameters.
	 * @param int    $extDirAttr     Permissions for directories.
	 * @param int    $extFileAttr    Permissions for files.
     */
    public function addDirectoryContent($realPath, $zipPath, $recursive = TRUE, $followSymlinks = TRUE, &$addedFiles = array(),
					$overrideFilePermissions = FALSE, $extDirAttr = self::EXT_FILE_ATTR_DIR, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if (file_exists($realPath) && !isset($addedFiles[realpath($realPath)])) {
            if (is_dir($realPath)) {
				if ($overrideFilePermissions) {
	                $this->addDirectory($zipPath, 0, null, $extDirAttr);
				} else {
					$this->addDirectory($zipPath, 0, null, self::getFileExtAttr($realPath));
				}
            }

            $addedFiles[realpath($realPath)] = $zipPath;

            $iter = new DirectoryIterator($realPath);
            foreach ($iter as $file) {
                if ($file->isDot()) {
                    continue;
                }
                $newRealPath = $file->getPathname();
                $newZipPath = self::pathJoin($zipPath, $file->getFilename());

                if (file_exists($newRealPath) && ($followSymlinks === TRUE || !is_link($newRealPath))) {
                    if ($file->isFile()) {
                        $addedFiles[realpath($newRealPath)] = $newZipPath;
						if ($overrideFilePermissions) {
							$this->addLargeFile($newRealPath, $newZipPath, 0, null, $extFileAttr);
						} else {
							$this->addLargeFile($newRealPath, $newZipPath, 0, null, self::getFileExtAttr($newRealPath));
						}
                    } else if ($recursive === TRUE) {
                        $this->addDirectoryContent($newRealPath, $newZipPath, $recursive, $followSymlinks, $addedFiles, $overrideFilePermissions, $extDirAttr, $extFileAttr);
                    } else {
						if ($overrideFilePermissions) {
							$this->addDirectory($zipPath, 0, null, $extDirAttr);
						} else {
							$this->addDirectory($zipPath, 0, null, self::getFileExtAttr($newRealPath));
						}
                    }
                }
            }
        }
    }

    /**
     * Add a file to the archive at the specified location and file name.
     *
     * @param string $dataFile    File name/path.
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
	 * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @return bool $success
     */
    public function addLargeFile($dataFile, $filePath, $timestamp = 0, $fileComment = NULL, $extFileAttr = self::EXT_FILE_ATTR_FILE)   {
        if ($this->isFinalized) {
            return FALSE;
        }

        if (is_string($dataFile) && is_file($dataFile)) {
            $this->processFile($dataFile, $filePath, $timestamp, $fileComment, $extFileAttr);
        } else if (is_resource($dataFile) && get_resource_type($dataFile) == "stream") {
            $fh = $dataFile;
            $this->openStream($filePath, $timestamp, $fileComment, $extFileAttr);

            while (!feof($fh)) {
                $this->addStreamData(fread($fh, $this->streamChunkSize));
            }
            $this->closeStream($this->addExtraField);
        }
        return TRUE;
    }

    /**
     * Create a stream to be used for large entries.
     *
     * @param string $filePath    Filepath and name to be used in the archive.
     * @param int    $timestamp   (Optional) Timestamp for the added file, if omitted or set to 0, the current time will be used.
     * @param string $fileComment (Optional) Comment to be added to the archive for this file. To use fileComment, timestamp must be given.
     * @param int    $extFileAttr (Optional) The external file reference, use generateExtAttr to generate this.
     * @throws Exception Throws an exception in case of errors
     * @return bool $success
     */
    public function openStream($filePath, $timestamp = 0, $fileComment = null, $extFileAttr = self::EXT_FILE_ATTR_FILE)   {
        if (!function_exists('sys_get_temp_dir')) {
            throw new Exception("Zip " . self::VERSION . " requires PHP version 5.2.1 or above if large files are used.");
        }

        if ($this->isFinalized) {
            return FALSE;
        }

        $this->zipflush();

        if (strlen($this->streamFilePath) > 0) {
            $this->closeStream();
        }

        $this->streamFile = self::getTemporaryFile();
        $this->streamData = fopen($this->streamFile, "wb");
        $this->streamFilePath = $filePath;
        $this->streamTimestamp = $timestamp;
        $this->streamFileComment = $fileComment;
        $this->streamFileLength = 0;
		$this->streamExtFileAttr = $extFileAttr;

        return TRUE;
    }

    /**
     * Add data to the open stream.
     *
     * @param string $data
     * @throws Exception Throws an exception in case of errors
     * @return mixed length in bytes added or FALSE if the archive is finalized or there are no open stream.
     */
    public function addStreamData($data) {
        if ($this->isFinalized || strlen($this->streamFilePath) == 0) {
            return FALSE;
        }

        $length = fwrite($this->streamData, $data, strlen($data));
        if ($length != strlen($data)) {
			throw new Exception("File IO: Error writing; Length mismatch: Expected " . strlen($data) . " bytes, wrote " . ($length === FALSE ? "NONE!" : $length));
		}
		$this->streamFileLength += $length;
        
		return $length;
    }

    /**
     * Close the current stream.
     *
     * @return bool $success
     */
    public function closeStream() {
        if ($this->isFinalized || strlen($this->streamFilePath) == 0) {
            return FALSE;
        }

        fflush($this->streamData);
        fclose($this->streamData);

        $this->processFile($this->streamFile, $this->streamFilePath, $this->streamTimestamp, $this->streamFileComment, $this->streamExtFileAttr);

        $this->streamData = null;
        $this->streamFilePath = null;
        $this->streamTimestamp = null;
        $this->streamFileComment = null;
        $this->streamFileLength = 0;
		$this->streamExtFileAttr = null;

        // Windows is a little slow at times, so a millisecond later, we can unlink this.
        unlink($this->streamFile);

        $this->streamFile = null;

        return TRUE;
    }

    private function processFile($dataFile, $filePath, $timestamp = 0, $fileComment = null, $extFileAttr = self::EXT_FILE_ATTR_FILE) {
        if ($this->isFinalized) {
            return FALSE;
        }

        $tempzip = self::getTemporaryFile();

        $zip = new ZipArchive;
        if ($zip->open($tempzip) === TRUE) {
            $zip->addFile($dataFile, 'file');
            $zip->close();
        }

        $file_handle = fopen($tempzip, "rb");
        $stats = fstat($file_handle);
        $eof = $stats['size']-72;

        fseek($file_handle, 6);

        $gpFlags = fread($file_handle, 2);
        $gzType = fread($file_handle, 2);
        fread($file_handle, 4);
        $fileCRC32 = fread($file_handle, 4);
        $v = unpack("Vval", fread($file_handle, 4));
        $gzLength = $v['val'];
        $v = unpack("Vval", fread($file_handle, 4));
        $dataLength = $v['val'];

        $this->buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr);

        fseek($file_handle, 34);
        $pos = 34;

        while (!feof($file_handle) && $pos < $eof) {
            $datalen = $this->streamChunkSize;
            if ($pos + $this->streamChunkSize > $eof) {
                $datalen = $eof-$pos;
            }
            $data = fread($file_handle, $datalen);
            $pos += $datalen;

            $this->zipwrite($data);
        }

        fclose($file_handle);

        unlink($tempzip);
    }

    /**
     * Close the archive.
     * A closed archive can no longer have new files added to it.
     *
     * @return bool $success
     */
    public function finalize() {
        if (!$this->isFinalized) {
            if (strlen($this->streamFilePath) > 0) {
                $this->closeStream();
            }
            $cd = implode("", $this->cdRec);

            $cdRecSize = pack("v", sizeof($this->cdRec));
            $cdRec = $cd . self::ZIP_END_OF_CENTRAL_DIRECTORY
            . $cdRecSize . $cdRecSize
            . pack("VV", strlen($cd), $this->offset);
            if (!empty($this->zipComment)) {
                $cdRec .= pack("v", strlen($this->zipComment)) . $this->zipComment;
            } else {
                $cdRec .= "\x00\x00";
            }

            $this->zipwrite($cdRec);

            $this->isFinalized = TRUE;
            $this->cdRec = NULL;

            return TRUE;
        }
        return FALSE;
    }

    /**
     * Get the handle ressource for the archive zip file.
     * If the zip haven't been finalized yet, this will cause it to become finalized
     *
     * @return zip file handle
     */
    public function getZipFile() {
        if (!$this->isFinalized) {
            $this->finalize();
        }

        $this->zipflush();

        rewind($this->zipFile);

        return $this->zipFile;
    }

    /**
     * Get the zip file contents
     * If the zip haven't been finalized yet, this will cause it to become finalized
     *
     * @return zip data
     */
    public function getZipData() {
        if (!$this->isFinalized) {
            $this->finalize();
        }
        if (!is_resource($this->zipFile)) {
            return $this->zipData;
        } else {
            rewind($this->zipFile);
            $filestat = fstat($this->zipFile);
            return fread($this->zipFile, $filestat['size']);
        }
    }

	/**
	 * Send the archive as a zip download
	 *
	 * @param String $fileName The name of the Zip archive, in ISO-8859-1 (or ASCII) encoding, ie. "archive.zip". Optional, defaults to NULL, which means that no ISO-8859-1 encoded file name will be specified.
	 * @param String $contentType Content mime type. Optional, defaults to "application/zip".
	 * @param String $utf8FileName The name of the Zip archive, in UTF-8 encoding. Optional, defaults to NULL, which means that no UTF-8 encoded file name will be specified.
	 * @param bool $inline Use Content-Disposition with "inline" instead of "attached". Optional, defaults to FALSE.
	 * @throws Exception Throws an exception in case of errors
	 * @return bool Always returns true (for backward compatibility).
	*/
	function sendZip($fileName = null, $contentType = "application/zip", $utf8FileName = null, $inline = false) {
		if (!$this->isFinalized) {
			$this->finalize();
		}
		$headerFile = null;
		$headerLine = null;
		if(headers_sent($headerFile, $headerLine)) {
        	throw new Exception("Unable to send file '$fileName'. Headers have already been sent from '$headerFile' in line $headerLine");
		}
		if(ob_get_contents() !== false && strlen(ob_get_contents())) {
			throw new Exception("Unable to send file '$fileName'. Output buffer contains the following text (typically warnings or errors):\n" . ob_get_contents());
		}
		if(@ini_get('zlib.output_compression')) {
			@ini_set('zlib.output_compression', 'Off');
		}
		header("Pragma: public");
		header("Last-Modified: " . @gmdate("D, d M Y H:i:s T"));
		header("Expires: 0");
		header("Accept-Ranges: bytes");
		header("Connection: close");
		header("Content-Type: " . $contentType);
		$cd = "Content-Disposition: ";
		if ($inline) {
			$cd .= "inline";
		} else {
			$cd .= "attached";
		}
		if ($fileName) {
			$cd .= '; filename="' . $fileName . '"';
		}
		if ($utf8FileName) {
			$cd .= "; filename*=UTF-8''" . rawurlencode($utf8FileName);
		}
		header($cd);
		header("Content-Length: ". $this->getArchiveSize());
		if (!is_resource($this->zipFile)) {
			echo $this->zipData;
		} else {
			rewind($this->zipFile);
			while (!feof($this->zipFile)) {
				echo fread($this->zipFile, $this->streamChunkSize);
			}
		}
		return true;
	}

    /**
     * Return the current size of the archive
     *
     * @return $size Size of the archive
     */
    public function getArchiveSize() {
        if (!is_resource($this->zipFile)) {
            return strlen($this->zipData);
        }
        $filestat = fstat($this->zipFile);

        return $filestat['size'];
    }

    /**
     * Calculate the 2 byte dostime used in the zip entries.
     *
     * @param int $timestamp
     * @return 2-byte encoded DOS Date
     */
    private function getDosTime($timestamp = 0) {
        $timestamp = (int)$timestamp;
        $oldTZ = @date_default_timezone_get();
        date_default_timezone_set('UTC');
        $date = ($timestamp == 0 ? getdate() : getdate($timestamp));
        date_default_timezone_set($oldTZ);
        if ($date["year"] >= 1980) {
            return pack("V", (($date["mday"] + ($date["mon"] << 5) + (($date["year"]-1980) << 9)) << 16) |
                    (($date["seconds"] >> 1) + ($date["minutes"] << 5) + ($date["hours"] << 11)));
        }
        return "\x00\x00\x00\x00";
    }

    /**
     * Build the Zip file structures
     *
     * @param string $filePath
     * @param string $fileComment
     * @param string $gpFlags
     * @param string $gzType
     * @param int    $timestamp
     * @param string $fileCRC32
     * @param int    $gzLength
     * @param int    $dataLength
     * @param int    $extFileAttr Use self::EXT_FILE_ATTR_FILE for files, self::EXT_FILE_ATTR_DIR for Directories.
     */
    private function buildZipEntry($filePath, $fileComment, $gpFlags, $gzType, $timestamp, $fileCRC32, $gzLength, $dataLength, $extFileAttr) {
        $filePath = str_replace("\\", "/", $filePath);
        $fileCommentLength = (empty($fileComment) ? 0 : strlen($fileComment));
        $timestamp = (int)$timestamp;
        $timestamp = ($timestamp == 0 ? time() : $timestamp);

        $dosTime = $this->getDosTime($timestamp);
        $tsPack = pack("V", $timestamp);

        if (!isset($gpFlags) || strlen($gpFlags) != 2) {
            $gpFlags = "\x00\x00";
        }

        $isFileUTF8 = mb_check_encoding($filePath, "UTF-8") && !mb_check_encoding($filePath, "ASCII");
        $isCommentUTF8 = !empty($fileComment) && mb_check_encoding($fileComment, "UTF-8") && !mb_check_encoding($fileComment, "ASCII");
		
		$localExtraField = "";
		$centralExtraField = "";
		
		if ($this->addExtraField) {
            $localExtraField .= "\x55\x54\x09\x00\x03" . $tsPack . $tsPack . Zip::EXTRA_FIELD_NEW_UNIX_GUID;
			$centralExtraField .= "\x55\x54\x05\x00\x03" . $tsPack . Zip::EXTRA_FIELD_NEW_UNIX_GUID;
		}
		
		if ($isFileUTF8 || $isCommentUTF8) {
            $flag = 0;
            $gpFlagsV = unpack("vflags", $gpFlags);
            if (isset($gpFlagsV['flags'])) {
                $flag = $gpFlagsV['flags'];
            }
            $gpFlags = pack("v", $flag | (1 << 11));
			
			if ($isFileUTF8) {
				$utfPathExtraField = "\x75\x70"
					. pack ("v", (5 + strlen($filePath)))
					. "\x01" 
					.  pack("V", crc32($filePath))
					. $filePath;

				$localExtraField .= $utfPathExtraField;
				$centralExtraField .= $utfPathExtraField;
			}
			if ($isCommentUTF8) {
				$centralExtraField .= "\x75\x63" // utf8 encoded file comment extra field
					. pack ("v", (5 + strlen($fileComment)))
					. "\x01"
					. pack("V", crc32($fileComment))
					. $fileComment;
			}
        }

        $header = $gpFlags . $gzType . $dosTime. $fileCRC32
			. pack("VVv", $gzLength, $dataLength, strlen($filePath)); // File name length

        $zipEntry  = self::ZIP_LOCAL_FILE_HEADER
			. self::ATTR_VERSION_TO_EXTRACT
			. $header
			. pack("v", strlen($localExtraField)) // Extra field length
			. $filePath // FileName
			. $localExtraField; // Extra fields

		$this->zipwrite($zipEntry);

        $cdEntry  = self::ZIP_CENTRAL_FILE_HEADER
			. self::ATTR_MADE_BY_VERSION
			. ($dataLength === 0 ? "\x0A\x00" : self::ATTR_VERSION_TO_EXTRACT)
			. $header
			. pack("v", strlen($centralExtraField)) // Extra field length
			. pack("v", $fileCommentLength) // File comment length
			. "\x00\x00" // Disk number start
			. "\x00\x00" // internal file attributes
			. pack("V", $extFileAttr) // External file attributes
			. pack("V", $this->offset) // Relative offset of local header
			. $filePath // FileName
			. $centralExtraField; // Extra fields

		if (!empty($fileComment)) {
            $cdEntry .= $fileComment; // Comment
        }

        $this->cdRec[] = $cdEntry;
        $this->offset += strlen($zipEntry) + $gzLength;
    }

    private function zipwrite($data) {
        if (!is_resource($this->zipFile)) {
            $this->zipData .= $data;
        } else {
            fwrite($this->zipFile, $data);
            fflush($this->zipFile);
        }
    }

    private function zipflush() {
        if (!is_resource($this->zipFile)) {
            $this->zipFile = tmpfile();
            fwrite($this->zipFile, $this->zipData);
            $this->zipData = NULL;
        }
    }

    /**
     * Join $file to $dir path, and clean up any excess slashes.
     *
     * @param string $dir
     * @param string $file
     */
    public static function pathJoin($dir, $file) {
        if (empty($dir) || empty($file)) {
            return self::getRelativePath($dir . $file);
        }
        return self::getRelativePath($dir . '/' . $file);
    }

    /**
     * Clean up a path, removing any unnecessary elements such as /./, // or redundant ../ segments.
     * If the path starts with a "/", it is deemed an absolute path and any /../ in the beginning is stripped off.
     * The returned path will not end in a "/".
	 *
	 * Sometimes, when a path is generated from multiple fragments, 
	 *  you can get something like "../data/html/../images/image.jpeg"
	 * This will normalize that example path to "../data/images/image.jpeg"
     *
     * @param string $path The path to clean up
     * @return string the clean path
     */
    public static function getRelativePath($path) {
        $path = preg_replace("#/+\.?/+#", "/", str_replace("\\", "/", $path));
        $dirs = explode("/", rtrim(preg_replace('#^(?:\./)+#', '', $path), '/'));

        $offset = 0;
        $sub = 0;
        $subOffset = 0;
        $root = "";

        if (empty($dirs[0])) {
            $root = "/";
            $dirs = array_splice($dirs, 1);
        } else if (preg_match("#[A-Za-z]:#", $dirs[0])) {
            $root = strtoupper($dirs[0]) . "/";
            $dirs = array_splice($dirs, 1);
        }

        $newDirs = array();
        foreach ($dirs as $dir) {
            if ($dir !== "..") {
                $subOffset--;
                $newDirs[++$offset] = $dir;
            } else {
                $subOffset++;
                if (--$offset < 0) {
                    $offset = 0;
                    if ($subOffset > $sub) {
                        $sub++;
                    }
                }
            }
        }

        if (empty($root)) {
            $root = str_repeat("../", $sub);
        }
        return $root . implode("/", array_slice($newDirs, 0, $offset));
    }

	/**
	 * Create the file permissions for a file or directory, for use in the extFileAttr parameters.
	 *
	 * @param int   $owner Unix permisions for owner (octal from 00 to 07)
	 * @param int   $group Unix permisions for group (octal from 00 to 07)
	 * @param int   $other Unix permisions for others (octal from 00 to 07)
	 * @param bool  $isFile
	 * @return EXTRERNAL_REF field.
	 */
	public static function generateExtAttr($owner = 07, $group = 05, $other = 05, $isFile = true) {
		$fp = $isFile ? self::S_IFREG : self::S_IFDIR;
		$fp |= (($owner & 07) << 6) | (($group & 07) << 3) | ($other & 07);

		return ($fp << 16) | ($isFile ? self::S_DOS_A : self::S_DOS_D);
	}

	/**
	 * Get the file permissions for a file or directory, for use in the extFileAttr parameters.
	 *
	 * @param string $filename
	 * @return external ref field, or FALSE if the file is not found.
	 */
	public static function getFileExtAttr($filename) {
		if (file_exists($filename)) {
			$fp = fileperms($filename) << 16;
			return $fp | (is_dir($filename) ? self::S_DOS_D : self::S_DOS_A);
		}
		return FALSE;
	}
	/**
	 * Returns the path to a temporary file.
	 * @return string
	 */
	private static function getTemporaryFile() {
		if(is_callable(self::$temp)) {
			$temporaryFile = @call_user_func(self::$temp);
			if(is_string($temporaryFile) && strlen($temporaryFile) && is_writable($temporaryFile)) {
				return $temporaryFile;
			}
		}
		$temporaryDirectory = (is_string(self::$temp) && strlen(self::$temp)) ? self::$temp : sys_get_temp_dir();
		return tempnam($temporaryDirectory, 'Zip');
	}
}

?>