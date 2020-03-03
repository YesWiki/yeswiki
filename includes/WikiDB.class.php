<?php
namespace YesWiki;
class Database
{
	// Properties
	protected $link;
	protected $debug = False;
	protected $queryLog = array();
	protected $missingArg = ''; // If empty False, otherwise True and contains the name of the class missing attributes
	public $error = '';

	/* Constructor
		Exemple :
			Either
				$myDB = new \YesWiki\Database($config, $queryLog);
			Or
				$myDB = new \YesWiki\Database();
				$myDB->setSQLConnection($config);
				$myDB->setQueryLog($queryLog);
		Anyway, if either $config or $queryLog are missing in the end, you will get an error
		Sets :
			$cookiePath
	*/
	public function __construct($config = null, &$queryLog = null)
	{
		if ($config == null) {
			$this->missingArg = 'Wiki_config';
		} else {
			$this->setSQLConnection($config);
		}
		if ($queryLog == null) {
			$this->missingArg .= 'Wiki_queryLog';
		} else {
			$this->setQueryLog($queryLog);
		}
	}

	public function setSQLConnection($config)
	{
		// Set link to MySQL db
		$this->link = mysqli_connect(
			 $config['mysql_host'],
			 $config['mysql_user'],
			 $config['mysql_password'],
			 $config['mysql_database']
		);
		// Fetch value of yeswiki debug status
		if (isset($config['debug'])) {
			$paramValue = trim($config['debug']);
			if (strtolower($paramValue) == 'no') {
				$this->debug = False;
			} else {
				$this->debug = boolval($paramValue);
			}
		}
		$this->missingArg = trim(str_ireplace('Wiki_config', '', $this->missingArg));
	}

	public function setQueryLog(&$queryLog)
	{
		// sets the db query log, in case we are in debug mode
		$this->queryLog = $queryLog;
		$this->missingArg = trim(str_ireplace('Wiki_queryLog', '', $this->missingArg));
	}

	protected function getMicroTime()
	{
		 list($usec, $sec) = explode(' ', microtime());
		 return ((float) $usec + (float) $sec);
	}

	public function escapeString($string)
	{
		return (mysqli_real_escape_string($this->link, $string));
	}


	/*	Should it Returns FALSE on failure? => For the time being dies in case of failure
		For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query() will return a mysqli_result object.
		For other successful will return TRUE.
		In case of failure $this->error contains the error message
	*/
	public function query($query)
	{
		if ($this->missingArg){
			$this->error = _t('DATABASE_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg._t('DATABASE_MISSING_ARGUMENT').'.';
			die($this->error);
		} else {
			if ($this->debug) {
				$start = $this->getMicroTime();
			}
			if (! $result = mysqli_query($this->link, $query)) {
				ob_end_clean();
				$this->error = _t('DATABASE_QUERY_FAILED').$query.' ('.mysqli_error($this->link).')';
				die($this->error);
			}
			if ($this->debug) {
				$duration = $this->getMicroTime() - $start;
				$this->queryLog[] = array(
					'query' => $query,
					'time' => $duration
				);
			}
			return $result;
		}
	}

	/*	If object properties haven't been set properly
			dies with a message
		If query fails
			returns null => FALSE
		Otherwise
			returns the first result of the query => True
	*/
	public function loadSingle($query)
	{
		if ($this->missingArg){
			$this->error = _t('DATABASE_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg._t('DATABASE_MISSING_ARGUMENT').'.';
			die($this->error); // Expected returned value here is null on failure.
		} else {
			if ($data = $this->loadAll($query)) {
				return $data[0];
			}
			return null;
		}
	}

	/*	If object properties haven't been set properly
			dies with a message
		fills and returns a table with the results of the query
		Frees the SQL resultset afterwards
	*/
	public function loadAll($query)
	{
		$data = array();
		if ($this->missingArg){
			$this->error = _t('DATABASE_YOU_MUST_FIRST_SET_ARGUMENT').' '.$this->missingArg._t('DATABASE_MISSING_ARGUMENT').'.';
			// $data['ERROR'] = $error; // Expected returned value here is an associative array.
			die($this->error);
		} else {
			if ($r = $this->query($query)) {
				while ($row = mysqli_fetch_assoc($r)) {
					$data[] = $row;
				}
				mysqli_free_result($r);
			}
		}
		return $data;
	}

} //end Database class
?>
