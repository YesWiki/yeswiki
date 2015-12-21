<?php
require_once('wakka.config.php');

// Une classe minimal wiki pour l'acces ? la base de donn?e

class Wiki
{
	var $dblink;
	var $VERSION;

	// constructor
	function Wiki($config)
	{
		$this->config = $config;
		// some host do not allow mysql_pconnect
		$this->dblink = @mysql_connect (
			$this->config["mysql_host"],
			$this->config["mysql_user"],
			$this->config["mysql_password"]);
		if ($this->dblink)
		{
			if (!@mysql_select_db($this->config["mysql_database"], $this->dblink))
			{
				@mysql_close($this->dblink);
				$this->dblink = false;
			}
		}
		$this->VERSION = WAKKA_VERSION;

	}


	// DATABASE
	function Query($query)
	{
		if (!$result = mysql_query($query, $this->dblink))
		{
			ob_end_clean();
			die("Query failed: ".$query." (".mysql_error().")");
		}
		return $result;
	}
	function LoadSingle($query) { if ($data = $this->LoadAll($query)) return $data[0]; }
	function LoadAll($query)
	{
	$data=array();
	if ($r = $this->Query($query))
		{
			while ($row = mysql_fetch_assoc($r)) $data[] = $row;
			mysql_free_result($r);
		}
		return $data;
	}
	
	// Quelque fonctions utiles ...
	
	function DeleteOrphanedPage($tag) {
		
		$this->Query("delete from ".$this->config["table_prefix"]."pages where tag='".mysqli_real_escape_string($this->dblink, $tag)."' ");
		$this->Query("delete from ".$this->config["table_prefix"]."links where from_tag='".mysqli_real_escape_string($this->dblink, $tag)."' ");
		$this->Query("delete from ".$this->config["table_prefix"]."acls where page_tag='".mysqli_real_escape_string($this->dblink, $tag)."' ");
		$this->Query("delete from ".$this->config["table_prefix"]."referrers where page_tag='".mysqli_real_escape_string($this->dblink, $tag)."' ");
	}

}
?>