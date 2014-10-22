<?php
/*
$Id: wakka.php 864 2007-11-28 12:44:52Z nepote $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2003 Carlo Zottmann
Copyright 2002, 2003, 2005 David DELON
Copyright 2002, 2003, 2004, 2006 Charles N?POTE
Copyright 2002, 2003 Patrick PAUL
Copyright 2003 Eric DELORD
Copyright 2003 Eric FELDSTEIN
Copyright 2004-2006 Jean-Christophe ANDR?
Copyright 2005-2006 Didier LOISEAU
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
	Yes, most of the formatting used in this file is HORRIBLY BAD STYLE. However,
	most of the action happens outside of this file, and I really wanted the code
	to look as small as what it does. Basically. Oh, I just suck. :)
 */



// do not change this line, you fool. In fact, don't change anything! Ever!
define("WAKKA_VERSION", "0.1.1");
define("WIKINI_VERSION", "0.5.0");
define("YESWIKI_VERSION", "Cercopitheque");
define("YESWIKI_RELEASE", "2013.12.25");
require 'includes/constants.php';
include 'includes/urlutils.inc.php';
include 'includes/i18n.inc.php';

// start the compute time
list($g_usec, $g_sec) = explode(" ",microtime());
define ("t_start", (float)$g_usec + (float)$g_sec);
$t_SQL=0;



class Wiki
{
	var $dblink;
	var $page;
	var $tag;
	var $parameter = array();
	var $queryLog = array();
	var $interWiki = array();
	var $VERSION;
	var $CookiePath = '/';
	var $inclusions = array();
	/**
	 * an array containing all the actions that are implemented by an object
	 * @access private
	 */
	var $actionObjects;

	// LinkTrackink
	var $isTrackingLinks = false;
	var $linktable = array();

	var $pageCache = array();
	var $_groupsCache = array();
	var $_actionsAclsCache = array();

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

		// determine le chemin pour les cookies
		$a = parse_url($this->GetConfigValue('base_url'));
		$this->CookiePath = dirname($a['path']);
		// Fixe la gestion des cookie sous les OS utilisant le \ comme separateur de chemin
		$this->CookiePath = str_replace("\\","/",$this->CookiePath);
		// ajoute un '/' terminal sauf si on est a la racine web
		if ($this->CookiePath != '/') $this->CookiePath .= '/';
	}



	// DATABASE
	function Query($query)
	{
		if($this->GetConfigValue("debug")) $start = $this->GetMicroTime();
		if (!$result = mysql_query($query, $this->dblink))
		{
			ob_end_clean();
			die("Query failed: ".$query." (".mysql_error().")");
		}
		if($this->GetConfigValue("debug"))
		{
			$time = $this->GetMicroTime() - $start;
			$this->queryLog[] = array(
				"query"		=> $query,
				"time"		=> $time);
		}
		return $result;
	}
	function LoadSingle($query) {
		if ($data = $this->LoadAll($query)) return $data[0];
		return null;
	}
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



	// MISC
	function GetMicroTime()
	{
		list($usec, $sec) = explode(" ",microtime()); return ((float)$usec + (float)$sec);
	}
	function IncludeBuffered($filename, $notfoundText = "", $vars = "", $path = "")
	{
		if ($path) $dirs = explode(":", $path);
		else $dirs = array("");

		foreach($dirs as $dir)
		{
			if ($dir) $dir .= "/";
			$fullfilename = $dir.$filename;
			if (file_exists($fullfilename))
			{
				if (is_array($vars)) extract($vars);

				ob_start();
				include($fullfilename);
				$output = ob_get_contents();
				ob_end_clean();
				return $output;
			}
		}
		if ($notfoundText) return $notfoundText;
		else return false;
	}



	// VARIABLES
	function GetPageTag() { return $this->tag; }
	function GetPageTime() { return $this->page["time"]; }
	function GetMethod() { return $this->method; }
	function GetConfigValue($name) { return isset($this->config[$name]) ? trim($this->config[$name]) : ''; }
	function GetWakkaName() { return $this->GetConfigValue("wakka_name"); }
	function GetWakkaVersion() { return $this->VERSION; }
	function GetWikiNiVersion() { return WIKINI_VERSION; }

	/**
	 * Retrieves all the triples that match some criteria.
	 * This allows to search triples by their approximate resource or property names.
	 * The allowed operators are the sql LIKE and the sql =
	 * @param string $resource The resource of the triples
	 * @param string $property The property of the triple to retrieve or null
	 * @param string $res_op The operator of comparison between the effective resource and $resource (default: 'LIKE')
	 * @param string $prop_op The operator of comparison between the effective property and $property (default: '=')
	 * @return array The list of all the triples that match the asked criteria 
	 */
	function GetMatchingTriples($resource, $property = null, $res_op = 'LIKE', $prop_op = '=')
	{
		static $operators = array('=', 'LIKE'); // we might want to add other operators later
		$res_op = strtoupper($res_op);
		if (!in_array($res_op, $operators)) $res_op = '=';
		$sql = 'SELECT * FROM ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'WHERE resource ' . $res_op . ' "' . addslashes($resource) . '"';
		if ($property !== null)
		{
			$prop_op = strtoupper($prop_op);
			if (!in_array($prop_op, $operators)) $prop_op = '=';
			$sql .= ' AND property ' . $prop_op . ' "' . addslashes($property) . '"';
		}
		return $this->LoadAll($sql);
	}

	/**
	 * Retrieves all the values for a given couple (resource, property)
	 * @param string $resource The resource of the triples
	 * @param string $property The property of the triple to retrieve
	 * @param string $re_prefix The prefix to add to $resource (defaults to THISWIKI_PREFIX)
	 * @param string $prop_prefix The prefix to add to $property (defaults to WIKINI_VOC_PREFIX)
	 * @return array An array of the retrieved values, in the form
	 * array(
	 * 	0 => array(id = 7 , 'value' => $value1),
	 * 	1 => array(id = 34, 'value' => $value2),
	 * 	...
	 * )
	 */
	function GetAllTriplesValues($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		$sql = 'SELECT id, value FROM ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'WHERE resource = "' . addslashes($re_prefix . $resource) . '" '
			. 'AND property = "' . addslashes($prop_prefix . $property) . '" ';
		return $this->LoadAll($sql);
	} 

	/**
	 * Retrieves a single value for a given couple (resource, property)
	 * @param string $resource The resource of the triples
	 * @param string $property The property of the triple to retrieve
	 * @param string $re_prefix The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
	 * @param string $prop_prefix The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
	 * @return string The value corresponding to ($resource, $property) or null if
	 * there is no such couple in the triples table.
	 */
	function GetTripleValue($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		$res = $this->GetAllTriplesValues($resource, $property, $re_prefix, $prop_prefix);
		if ($res) return $res[0]['value'];
		return null;
	}

	/**
	 * Checks whether a triple exists or not
	 * @param string $resource The resource of the triple to find
	 * @param string $property The property of the triple to find
	 * @param string $value The value of the triple to find
	 * @param string $re_prefix The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
	 * @param string $prop_prefix The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
	 * @param int The id of the found triple or 0 if there is no such triple. 
	 */
	function TripleExists($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		$sql = 'SELECT id FROM ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'WHERE resource = "' . addslashes($re_prefix . $resource) . '" '
			. 'AND property = "' . addslashes($prop_prefix . $property) . '" '
			. 'AND value = "' . addslashes($value) . '"';
		$res = $this->LoadSingle($sql);
		if (!$res) return 0;
		return $res['id'];
	}

	/**
	 * Inserts a new triple ($resource, $property, $value) in the triples' table
	 * @param string $resource The resource of the triple to insert
	 * @param string $property The property of the triple to insert
	 * @param string $value The value of the triple to insert
	 * @param string $re_prefix The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
	 * @param string $prop_prefix The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
	 * @return int An error code: 0 (success), 1 (failure) or 3 (already exists)
	 */
	function InsertTriple($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		if ($this->TripleExists($resource, $property, $value, $re_prefix, $prop_prefix))
		{
			return 3;
		}
		$sql = 'INSERT INTO ' . $this->GetConfigValue('table_prefix') . 'triples (resource, property, value)'
			. 'VALUES ("' . addslashes($re_prefix . $resource) . '", "'
				. addslashes($prop_prefix . $property) . '", "'
				. addslashes($value) . '")';
		return $this->Query($sql) ? 0 : 1;
	}

	/**
	 * Updates a triple ($resource, $property, $value) in the triples' table
	 * @param string $resource The resource of the triple to update
	 * @param string $property The property of the triple to update
	 * @param string $oldvalue The old value of the triple to update
	 * @param string $newvalue The new value of the triple to update
	 * @param string $re_prefix The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
	 * @param string $prop_prefix The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
	 * @return int An error code: 0 (succ?s), 1 (?chec),
	 * 		2 ($resource, $property, $oldvalue does not exist)
	 * 		or 3 ($resource, $property, $newvalue already exists)
	 */
	function UpdateTriple($resource, $property, $oldvalue, $newvalue, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		$id = $this->TripleExists($resource, $property, $oldvalue, $re_prefix, $prop_prefix);
		if (!$id) return 2;
		if ($this->TripleExists($resource, $property, $newvalue, $re_prefix, $prop_prefix))
		{
			return 3;
		}
		$sql = 'UPDATE ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'SET value = "' . addslashes($newvalue) . '" '
			. 'WHERE id = ' . $id;
		return $this->Query($sql) ? 0 : 1;
	}

	/**
	 * Deletes a triple ($resource, $property, $value) from the triples' table
	 * @param string $resource The resource of the triple to delete
	 * @param string $property The property of the triple to delete
	 * @param string $value The value of the triple to delete. If set to <tt>null</tt>,
	 * deletes all the triples corresponding to ($resource, $property). (defaults to <tt>null</tt>)
	 * @param string $re_prefix The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
	 * @param string $prop_prefix The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
	 */
	function DeleteTriple($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
	{
		$sql = 'DELETE FROM ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'WHERE resource = "' . addslashes($re_prefix . $resource) . '" '
			. 'AND property = "' . addslashes($prop_prefix . $property) . '" ';
		if ($value !== null) $sql .= 'AND value = "' . addslashes($value) . '"';
		$this->Query($sql);
	}

	// inclusions
	/**
	 * Enregistre une nouvelle inclusion dans la pile d'inclusions.
	 * 
	 * @param string $pageTag Le nom de la page qui va etre inclue
	 * @return int Le nombre d'elements dans la pile
	 */
	function RegisterInclusion($pageTag)
	{
		return array_unshift($this->inclusions, strtolower(trim($pageTag)));
	} 
	/**
	 * Retire le dernier element de la pile d'inclusions.
	 * 
	 * @return string Le nom de la page dont l'inclusion devrait se terminer.
	 * null s'il n'y a plus d'inclusion dans la pile.
	 */
	function UnregisterLastInclusion()
	{
		return array_shift($this->inclusions);
	} 
	/**
	 * Renvoie le nom de la page en cours d'inclusion.
	 * 
	 * @example // dans le cas d'une action comme l'ActionEcrivezMoi
	 * if($inc = $this->CurrentInclusion() && strtolower($this->GetPageTag()) != $inc)
	 * 	echo 'Cette action ne peut etre appelee depuis une page inclue';
	 * @return string Le nom (tag) de la page (en minuscules)
	 * false si la pile est vide.
	 */
	function GetCurrentInclusion()
	{
		return isset($this->inclusions[0]) ? $this->inclusions[0]: false ;
	} 
	/**
	 * Verifie si on est a l'interieur d'une inclusion par $pageTag (sans tenir compte de la casse)
	 * 
	 * @param string $pageTag Le nom de la page a verifier
	 * @return bool True si on est a l'interieur d'une inclusion par $pageTag (false sinon)
	 */
	function IsIncludedBy($pageTag)
	{
		return in_array(strtolower($pageTag), $this->inclusions);
	} 
	/**
	 * 
	 * @return array La pile d'inclusions
	 * L'element 0 sera la derniere inclusion, l'element 1 sera son parent et ainsi de suite.
	 */
	function GetAllInclusions()
	{
		return $this->inclusions;
	} 
	/**
	 * Remplace la pile des inclusions par une nouvelle pile (par defaut une pile vide)
	 * Permet de formatter une page sans tenir compte des inclusions precedentes.
	 * 
	 * @param array $ La nouvelle pile d'inclusions.
	 * L'element 0 doit representer la derniere inclusion, l'element 1 son parent et ainsi de suite.
	 * @return array L'ancienne pile d'inclusions, avec les noms des pages en minuscules.
	 */
	function SetInclusions($pile = array())
	{
		$temp = $this->inclusions;
		$this->inclusions = $pile;
		return $temp;
	} 

	// PAGES
	function LoadPage($tag, $time = "", $cache = 1)
	{
		// retrieve from cache
		if (!$time && $cache && (($cachedPage = $this->GetCachedPage($tag)) !== false))
		{
			$page = $cachedPage;
		}
		else // load page
		{
			$sql = "SELECT * FROM ".$this->config["table_prefix"]."pages"
				. " WHERE tag = '".mysql_real_escape_string($tag)."' AND "
				. ($time ? "time = '".mysql_real_escape_string($time)."'" : "latest = 'Y'") . " LIMIT 1";
			$page = $this->LoadSingle($sql);

			// the database is in ISO-8859-15, it must be converted
			if (isset($page['body'])) {
				$page['body'] = _convert($page['body'], "ISO-8859-15");
			} 
			
			// cache result
			if (!$time) $this->CachePage($page, $tag);
		}
		return $page;
	}
	/**
	 * Retrieves the cached version of a page.
	 *
	 * Notice that this method null or false, use
	 * 	$this->GetCachedPage($tag) === false
	 * to check if a page is not in the cache.
	 * @return mixed The cached version of a page:
	 * 	- the page DB line if the page exists and is in cache
	 * 	- null if the cache knows that the page does not exists
	 * 	- false is the cache does not know the page
	 */
	function GetCachedPage($tag) {return (array_key_exists($tag, $this->pageCache) ? $this->pageCache[$tag] : false); }
	/**
	 * Caches a page's DB line.
	 *
	 * @param array $page The page (full) DB line or null if the page does not exists
	 * @param string $pageTag The tag of the page to cache. Defaults to $page['tag'] but is mendatory when $page === null
	 */
	function CachePage($page, $pageTag = null) {
		if ($pageTag === null)
		{
			$pageTag = $page["tag"];
		}
		$this->pageCache[$pageTag] = $page;
	}
	function SetPage($page) { $this->page = $page; if ($this->page["tag"]) $this->tag = $this->page["tag"]; }
	function LoadPageById($id) { return $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where id = '".mysql_real_escape_string($id)."' limit 1"); }
	function LoadRevisions($page) { return $this->LoadAll("select * from ".$this->config["table_prefix"]."pages where tag = '".mysql_real_escape_string($page)."' order by time desc"); }
	function LoadPagesLinkingTo($tag) { return $this->LoadAll("select from_tag as tag from ".$this->config["table_prefix"]."links where to_tag = '".mysql_real_escape_string($tag)."' order by tag"); }
	function LoadRecentlyChanged($limit=50)
	{
		$limit= (int) $limit;
		if ($pages = $this->LoadAll("select id, tag, time, user, owner from ".$this->config["table_prefix"]."pages where latest = 'Y' and comment_on = '' order by time desc limit $limit"))
		{
			foreach ($pages as $page)
			{
				$this->CachePage($page);
			}
			return $pages;
		}
	}
	function LoadAllPages() { return $this->LoadAll("select * from ".$this->config["table_prefix"]."pages where latest = 'Y' order by tag"); }
	function FullTextSearch($phrase) { return $this->LoadAll("select * from ".$this->config["table_prefix"]."pages where latest = 'Y' and match(tag, body) against('".mysql_real_escape_string($phrase)."')"); }
	function LoadWantedPages() {
		$p = $this->config["table_prefix"];
		$r = "SELECT ${p}links.to_tag AS tag, COUNT(${p}links.from_tag) AS count "
			. "FROM ${p}links LEFT JOIN ${p}pages ON ${p}links.to_tag = ${p}pages.tag "
			. "WHERE ${p}pages.tag IS NULL GROUP BY ${p}links.to_tag ORDER BY count DESC, tag ASC";
		return $this->LoadAll($r);
	}
	function LoadOrphanedPages() { return $this->LoadAll("select distinct tag from ".$this->config["table_prefix"]."pages as p left join ".$this->config["table_prefix"]."links as l on p.tag = l.to_tag where l.to_tag is NULL and p.comment_on = '' and p.latest = 'Y' order by tag"); }
	function IsOrphanedPage($tag) { return $this->LoadAll("select distinct tag from ".$this->config['table_prefix']."pages as p left join ".$this->config['table_prefix']."links as l on p.tag = l.to_tag where l.to_tag is NULL and p.latest = 'Y' and tag = '".mysql_real_escape_string($tag)."'"); }
	function DeleteOrphanedPage($tag) {
		$p = $this->config["table_prefix"];
		$this->Query("DELETE FROM ${p}pages WHERE tag='".mysql_real_escape_string($tag)."' OR comment_on='".mysql_real_escape_string($tag)."'");
		$this->Query("DELETE FROM ${p}links WHERE from_tag='".mysql_real_escape_string($tag)."' ");
		$this->Query("DELETE FROM ${p}acls WHERE page_tag='".mysql_real_escape_string($tag)."' ");
		$this->Query("DELETE FROM ${p}referrers WHERE page_tag='".mysql_real_escape_string($tag)."' ");
	}

	/**
	 * SavePage
	 * Sauvegarde un contenu dans une page donnee
	 *
	 * @param string $body Contenu a sauvegarder dans la page
	 * @param string $tag Nom de la page
	 * @param string $comment_on Indication si c'est un commentaire
	 * @param boolean $bypass_acls Indication si on bypasse les droits d'ecriture
	 * @return int Code d'erreur : 0 (succes), 1 (l'utilisateur n'a pas les droits)
	 */	
	function SavePage($tag, $body, $comment_on = "", $bypass_acls = false)
	{
		// get current user
		$user = $this->GetUserName();

		// check bypass of rights or write privilege
		$rights = $bypass_acls || ($comment_on ? $this->HasAccess("comment", $comment_on) : $this->HasAccess("write", $tag));
			
		if ($rights)
		{
			// is page new?
			if (!$oldPage = $this->LoadPage($tag))
			{
				// create default write acl. store empty write ACL for comments.
				$this->SaveAcl($tag, "write", ($comment_on ? $user : $this->GetConfigValue("default_write_acl")));

				// create default read acl
				$this->SaveAcl($tag, "read", $this->GetConfigValue("default_read_acl"));

				// create default comment acl.
				$this->SaveAcl($tag, "comment", ($comment_on ? "" : $this->GetConfigValue("default_comment_acl")));

				// current user is owner; if user is logged in! otherwise, no owner.
				if ($this->GetUser()) $owner = $user;
				else $owner = '';
			}
			else
			{
				// aha! page isn't new. keep owner!
				$owner = $oldPage["owner"];

				// ...and comment_on, eventualy?
				if ($comment_on == '') $comment_on = $oldPage['comment_on'];
			}


			// set all other revisions to old
			$this->Query("update ".$this->config["table_prefix"]."pages set latest = 'N' where tag = '".mysql_real_escape_string($tag)."'");

			// add new revision
			$this->Query("insert into ".$this->config["table_prefix"]."pages set ".
				"tag = '".mysql_real_escape_string($tag)."', ".
				($comment_on ? "comment_on = '".mysql_real_escape_string($comment_on)."', " : "").
				"time = now(), ".
				"owner = '".mysql_real_escape_string($owner)."', ".
				"user = '".mysql_real_escape_string($user)."', ".
				"latest = 'Y', ".
				"body = '".mysql_real_escape_string(chop($body))."'");

			unset($this->pageCache[$tag]);
			return 0;
		}
		else return 1;
	}

	
	/**
	 * AppendContentToPage
	 * Ajoute du contenu a la fin d'une page
	 *
	 * @param string $content Contenu a ajouter a la page
	 * @param string $page Nom de la page
	 * @param boolean $bypass_acls Bouleen pour savoir s'il faut bypasser les ACLs
	 * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
	 */
	function AppendContentToPage($content, $page, $bypass_acls = false)
	{
		// Si un contenu est specifie
		if (isset($content))
		{
			// -- Determine quelle est la page :
			//    -- passee en parametre (que se passe-t'il si elle n'existe pas ?)
			//    -- ou la page en cours par defaut
			$page = isset($page) ? $page : $this->GetPageTag();

			// -- Chargement de la page
			$result = $this->LoadPage($page);
			$body = $result['body'];
			// -- Ajout du contenu a la fin de la page
			$body .= $content;

			// -- Sauvegarde de la page
			// TODO : que se passe-t-il si la page est pleine ou si l'utilisateur n'a pas les droits ?
			$this->SavePage($page, $body, "", $bypass_acls);
			
			// now we render it internally so we can write the updated link table.
			$this->ClearLinkTable();
			$this->StartLinkTracking();
			$temp = $this->SetInclusions();
			$this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
			$this->Format($body);
			$this->SetInclusions($temp);
			if($user = $this->GetUser())
			{
				$this->TrackLinkTo($user['name']);
			}
			if($owner = $this->GetPageOwner())
			{
				$this->TrackLinkTo($owner);
			}
			$this->StopLinkTracking();
			$this->WriteLinkTable();
			$this->ClearLinkTable();/**/

			// Retourne 0 seulement si tout c'est bien passe
			return 0;
		}
		else return 1;
	}
	
	/**
	 * LogAdministrativeAction($user, $content, $page = "")
	 * 
	 * @param string $user Utilisateur
	 * @param string $content Contenu de l'enregistrement
	 * @param string $page Page de log
	 * 
	 * @return int Code d'erreur : 0 (succes), 1 (pas de contenu specifie)
	 */
	function LogAdministrativeAction($user, $content, $page = "")
	{
		$order   = array("\r\n", "\n", "\r");
		$replace = '\\n';
		$content = str_replace($order, $replace, $content);
		$contentToAppend = "\n" . date("Y-m-d H:i:s") . " . . . . " . $user . " . . . . " . $content . "\n";
		$page = $page ? $page : "LogDesActionsAdministratives" . date("Ymd");
		return $this->AppendContentToPage($contentToAppend, $page, true);
	}

	
	/**
	 * Make the purge of page versions that are older than the last version older than 3 "pages_purge_time"
	 * This method permits to allways keep a version that is older than that period.
	 */
	function PurgePages() {
		if ($days = $this->GetConfigValue("pages_purge_time")) { // is purge active ?
			// let's search which pages versions we have to remove
			// this is necessary beacause even MySQL does not handel multi-tables deletes before version 4.0
			$wnPages = $this->GetConfigValue('table_prefix') . 'pages';
			$sql = 'SELECT DISTINCT a.id FROM ' . $wnPages . ' a,' . $wnPages . ' b WHERE a.latest = \'N\' AND a.time < date_sub(now(), INTERVAL \'' . addslashes($days) . '\' DAY) AND a.tag = b.tag AND a.time < b.time AND b.time < date_sub(now(), INTERVAL \'' . addslashes($days) . '\' DAY)';
			$ids = $this->LoadAll($sql);

			if (count($ids)) { // there are some versions to remove from DB
				// let's build one big request, that's better...
				$sql = 'DELETE FROM ' . $wnPages . ' WHERE id IN (';
				foreach($ids as $key => $line){
					$sql .= ($key ? ', ':'') . $line['id']; // NB.: id is an int, no need of quotes
				}
				$sql .= ')';

				// ... and send it !
				$this->Query($sql);
			}
		}
	}



	// COOKIES
	function SetSessionCookie($name, $value)
	{
		SetCookie($name, $value, 0, $this->CookiePath);
		$_COOKIE[$name] = $value;
	}
	function SetPersistentCookie($name, $value, $remember = 0)
	{
		SetCookie($name, $value, time() + ($remember ? 90*24*60*60 : 60 * 60), $this->CookiePath);
		$_COOKIE[$name] = $value;
	}
	function DeleteCookie($name)
	{
		SetCookie($name, "", 1, $this->CookiePath);
		$_COOKIE[$name] = "";
	}
	function GetCookie($name) { return $_COOKIE[$name]; }



	// HTTP/REQUEST/LINK RELATED
	function SetMessage($message) { $_SESSION["message"] = $message; }
	function GetMessage()
	{
		if (isset($_SESSION["message"])) $message = $_SESSION["message"];
		else $message = "";
		$_SESSION["message"] = "";
		return $message;
	}
	function Redirect($url)
	{
		header("Location: $url");
		exit;
	}
	// returns just PageName[/method].
	function MiniHref($method = "", $tag = "")
	{
		if (!$tag = trim($tag)) $tag = $this->tag;
		return $tag.($method ? "/".$method : "");
	}
	// returns the full url to a page/method.
	function Href($method = "", $tag = "", $params = "", $htmlspchars = true)
	{
		$href = $this->config["base_url"].$this->MiniHref($method, $tag);
		if ($params)
		{
			$href .= ($this->config["rewrite_mode"] ? "?" : ($htmlspchars ? "&amp;" : '&')).$params;
		}
		return $href;
	}
	function Link($tag, $method = "", $text = "", $track = 1)
	{
		$displayText = $text ? $text : $tag;
		// is this an interwiki link?
		if (preg_match('/^' . WN_INTERWIKI_CAPTURE . '$/', $tag, $matches))
		{
			if ($tagInterWiki = $this->GetInterWikiUrl($matches[1], $matches[2])) {
				return '<a href="'.htmlspecialchars($tagInterWiki, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'">'
					.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).' (interwiki)</a>';
			}
			else return '<a href="'.htmlspecialchars($tag, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'">'
				.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).' (interwiki inconnu)</a>';
		}
		// is this a full link? ie, does it contain non alpha-numeric characters?
		// Note : [:alnum:] is equivalent [0-9A-Za-z]
		//		  [^[:alnum:]] means : some caracters other than [0-9A-Za-z]
		// For example : "www.adress.com", "mailto:adress@domain.com", "http://www.adress.com"
		else if (preg_match("/[^[:alnum:]]/", $tag))
		{
			// check for various modifications to perform on $tag
			if (preg_match("/^[\w.-]+\@[\w.-]+$/", $tag))
			{ // email addresses
				$tag = 'mailto:'.$tag;
			}
			// Note : in Perl regexp, (?: ... ) is a non-catching cluster
			else if (preg_match('/^[[:alnum:]][[:alnum:].-]*(?:\/|$)/', $tag))
			{ // protocol-less URLs
				$tag = 'http://'.$tag;
			}
			// Finally, block script schemes (see RFC 3986 about
			// schemes) and allow relative link & protocol-full URLs
			else if (preg_match('/^[a-z0-9.+-]*script[a-z0-9.+-]*:/i', $tag)
				|| !(preg_match('/^\.?\.?\//', $tag)
					|| preg_match('/^[a-z0-9.+-]+:\/\//i', $tag)))
			{
				// If does't fit, we can't qualify $tag as an URL.
				// There is a high risk that $tag is just XSS (bad
				// javascript: code) or anything nasty. So we must not
				// produce any link at all.
				return htmlspecialchars($tag.($text ? ' '.$text : ''), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET);
			}
			// Important: Here, we know that $tag is not something bad
			// and that we must produce a link with it

			// An inline image? (text!=tag and url ends by png,gif,jpeg)
			if ($text and preg_match("/\.(gif|jpeg|png|jpg)$/i",$tag))
			{
				return '<img src="'.htmlspecialchars($tag, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET)
					.'" alt="'.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'"/>';
			}
			else
			{
				// Even if we know $tag is harmless, we MUST encode it
				// in HTML with htmlspecialchars() before echoing it.
				// This is not about being paranoiac. This is about
				// being compliant to the HTML standard.
				return '<a href="'.htmlspecialchars($tag, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'">'
					.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'</a>';
			}
		}
		else
		{
			// it's a Wiki link!
			if (!empty($track)) $this->TrackLinkTo($tag);
			if ($this->LoadPage($tag))
				return '<a href="'.htmlspecialchars($this->href($method, $tag), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'">'
					.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'</a>';
			else
				return '<span class="missingpage">'.htmlspecialchars($displayText, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET)
				.'</span><a href="'.htmlspecialchars($this->href("edit", $tag), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET).'">?</a>';
		}
	}
	function ComposeLinkToPage($tag, $method = "", $text = "", $track = 1) {
		if (!$text) $text = $tag;
		$text = htmlspecialchars($text, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET);
		if ($track)
			$this->TrackLinkTo($tag);
		return '<a href="'.$this->href($method, $tag).'">'.$text.'</a>';
	}
	function IsWikiName($text) {
		return preg_match('/^' . WN_CAMEL_CASE . '$/', $text);
	}

	// LinkTracking management
	/**
	 * Tracks the link to a given page (only if the LinkTracking is activated)
	 * @param string $tag The tag (name) of the page to track a link to.
	 */
	function TrackLinkTo($tag) {
		if ($this->LinkTracking()) $this->linktable[] = $tag;
	}
	/**
	 * @return array The current link tracking table
	 */
	function GetLinkTable() { return $this->linktable; }
	/**
	 * Clears the link tracking table
	 */
	function ClearLinkTable() { $this->linktable = array(); }
	/**
	 * Starts the LinkTracking
	 * @return bool The previous state of the link tracking
	 */
	function StartLinkTracking() {
		return $this->LinkTracking(true);
	}
	/**
	 * Stops the LinkTracking
	 * @return bool The previous state of the link tracking
	 */
	function StopLinkTracking() {
		return $this->LinkTracking(false);
	}
	/**
	 * Sets and/or retrieve the state of the LinkTracking
	 * @param bool $newStatus The new status of the LinkTracking
	 * (defaults to <tt>null</tt> which lets it unchanged)
	 * @return bool The previous state of the link tracking
	 */
	function LinkTracking($newStatus = null)
	{
		$old = $this->isTrackingLinks;
		if ($newStatus !== null) $this->isTrackingLinks = $newStatus;
		return $old;
	}
	function WriteLinkTable() {
		// delete old link table
		$this->Query("delete from ".$this->config["table_prefix"]."links where from_tag = '".mysql_real_escape_string($this->GetPageTag())."'");
		if ($linktable = $this->GetLinkTable())
		{
			$from_tag = mysql_real_escape_string($this->GetPageTag());
			foreach ($linktable as $to_tag)
			{
				$lower_to_tag = strtolower($to_tag);
				if (!isset($written[$lower_to_tag]))
				{
					$this->Query("insert into ".$this->config["table_prefix"]."links set from_tag = '".$from_tag."', to_tag = '".mysql_real_escape_string($to_tag)."'");
					$written[$lower_to_tag] = 1;
				}
			}
		}
	}

	function Header() {
		$action = $this->GetConfigValue("header_action");
		if (($actionObj = &$this->GetActionObject($action)) && is_object($actionObj))
		{
			return $actionObj->GenerateHeader();
		}
		return $this->Action($action, 1);
	}

	function Footer() {
		$action = $this->GetConfigValue("footer_action");
		if (($actionObj = &$this->GetActionObject($action)) && is_object($actionObj))
		{
			return $actionObj->GenerateFooter();
		}
		return $this->Action($action, 1);
	}

	// FORMS
	function FormOpen($method = "", $tag = "", $formMethod = "post", $class="") {
		$result  = "<form action=\"".$this->href($method, $tag)."\" method=\"".$formMethod."\"";
		$result .= ((!empty($class)) ? " class=\"".$class."\"" : "");
		$result .= ">\n";
		if (!$this->config["rewrite_mode"]) $result .= "<input type=\"hidden\" name=\"wiki\" value=\"".$this->MiniHref($method, $tag)."\" />\n";
		return $result;
	}
	function FormClose() {
		return "</form>\n";
	}



	// INTERWIKI STUFF
	function ReadInterWikiConfig() {
		if ($lines = file("interwiki.conf"))
		{
			foreach ($lines as $line)
			{
				if ($line = trim($line))
				{
					list($wikiName, $wikiUrl) = explode(" ", trim($line));
					$this->AddInterWiki($wikiName, $wikiUrl);
				}
			}
		}
	}
	function AddInterWiki($name, $url) {
		$this->interWiki[strtolower($name)] = $url;
	}
	function GetInterWikiUrl($name, $tag)
	{
		if (isset($this->interWiki[strtolower($name)])) return $this->interWiki[strtolower($name)].$tag;
		else return FALSE;
	}



	// REFERRERS
	function LogReferrer($tag = "", $referrer = "") {
		// fill values
		if (!$tag = trim($tag)) $tag = $this->GetPageTag();
		if (!$referrer = trim($referrer) AND isset($_SERVER["HTTP_REFERER"])) $referrer = $_SERVER["HTTP_REFERER"];

		// check if it's coming from another site
		if ($referrer && !preg_match("/^".preg_quote($this->GetConfigValue("base_url"), "/")."/", $referrer))
		{
			// avoid XSS (with urls like "javascript:alert()" and co)
			// by forcing http/https prefix
			// NB.: this does NOT exempt to htmlspecialchars() the collected URIs !
			if (!preg_match('`^https?://`', $referrer)) return;

			$this->Query("insert into ".$this->config["table_prefix"]."referrers set ".
				"page_tag = '".mysql_real_escape_string($tag)."', ".
				"referrer = '".mysql_real_escape_string($referrer)."', ".
				"time = now()");
		}
	}
	function LoadReferrers($tag = "") {
		return $this->LoadAll("select referrer, count(referrer) as num from ".$this->config["table_prefix"]."referrers ".($tag = trim($tag) ? "where page_tag = '".mysql_real_escape_string($tag)."'" : "")." group by referrer order by num desc");
	}
	function PurgeReferrers() {
		if ($days = $this->GetConfigValue("referrers_purge_time")) {
			$this->Query("delete from ".$this->config["table_prefix"]."referrers where time < date_sub(now(), interval '".mysql_real_escape_string($days)."' day)");
		}
	}



	// PLUGINS
	/**
	 * Exacutes an "action" module and returns the generated output
	 * @param string $action The name of the action and its eventual parameters,
	 * as it appears in the page between "{{" and "}}"
	 * @param boolean $forceLinkTracking By default, the link tracking will be disabled
	 * during the call of an action. Set this value to <code>true</code> to allow it.
	 * @param array $vars An array of additionnal parameters to give to the action, in the form
	 * array( 'param' => 'value').
	 * This allows you to call Action() internally, setting $action to the name of the action
	 * you want to call and it's parameters in an array, wich is more efficient than
	 * the pattern-matching algorithm used to extract the parameters from $action.
	 * @return The output generated by the action.
	 */
	function Action($action, $forceLinkTracking = 0, $vars = array())
	{
		$cmd = trim($action);
		// extract $action and $vars_temp ("raw" attributes)
		if (!preg_match("/^([a-zA-Z-0-9]+)\/?(.*)$/", $cmd, $matches))
		{
			return '<div class="alert alert-danger">'._t('INVALID_ACTION').' &quot;' . htmlspecialchars($cmd, ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET) . '&quot;</div>'."\n";
		}
		list(, $action, $vars_temp) = $matches;
		$vars[$vars_temp] = $vars_temp; // usefull for {{action/vars_temp}}

		// now that we have the action's name, we can check if the user satisfies the ACLs
		if (!$this->CheckModuleACL($action, 'action'))
		{
			return '<div class="alert alert-danger">'._t('ERROR_NO_ACCESS').' ' . $action . '.</div>'."\n";
		}

		// match all attributes (key and value)
		// prepare an array for extract() to work with (in $this->IncludeBuffered())
		if (preg_match_all("/([a-zA-Z0-9]*)=\"(.*)\"/U", $vars_temp, $matches))
		{
			for ($a = 0; $a < count($matches[1]); $a++)
			{
				$vars[$matches[1][$a]] = $matches[2][$a];
			}
		}

		if (!$forceLinkTracking) $this->StopLinkTracking();
		if ($actionObj = &$this->GetActionObject($action))
		{
			if (is_object($actionObj))
			{
				$result = $actionObj->PerformAction($vars, $cmd);
			}
			else // $actionObj is an error message
			{
				$result = $actionObj;
			}
		}
		else // $actionObj == null (not found, no error message)
		{
			$this->parameter = &$vars;
			$result = $this->IncludeBuffered(strtolower($action).".php", '<div class="alert alert-danger">'._t('UNKNOWN_ACTION')." &quot;$action&quot;</div>\n", $vars, $this->config["action_path"]);
			unset($this->parameter);
		}
		$this->StartLinkTracking(); // shouldn't we restore the previous status ?
		return $result;
	}

	/**
	 * Finds the object corresponding to an action, if it exists.
	 * @param string $name The name of an action (should be alphanumeric)
	 * @return mixed
	 * 	- null if the corresponding file was not found or the corresponding class didn't exist after inclusion
	 *  - an error string if the corresponding file was found but an error append while loading it
	 *  - the object corresponding to this action if no problem happend
	 * To check the result, you should use is_object() on it.
	 * You should always assign the result of this method by referrence
	 * to avoid copying the object, which is the default beheviour in PHP4.
	 * @example
	 * $var = &$wiki->GetActionObject('actionname');
	 * if (is_object($var))
	 * {
	 * 		// normal behaviour
	 * }
	 * elseif ($var) // $var is not an object but an error string
	 * {
	 * 		// threat error
	 * }
	 * else // action was not found
	 * {
	 * 		// rescue from inexising action or sth
	 * } 
	 */
	function &GetActionObject($name)
	{
		$name = strtolower($name);
		$actionObj = null; // the generated object
		if (!preg_match('/^[a-z0-9]+$/', $name)) // paranoiac
		{
			return $actionObj;
		}

		// already tried to load this object ? (may be null)
		if (isset($this->actionObjects[$name]))
		{
			return $this->actionObjects[$name];
		}

		// object not loaded, try to load it
		$filename = $name . '.class.php';
		// load parent class for all action objects (only once)
		require_once 'includes/action.class.php';
		// include the action file, this should return an empty string
		$result = $this->IncludeBuffered($filename, null, null, $this->GetConfigValue('action_path'));
		if ($result) // the result was not an empty string, certainly an error message
		{
			$actionObj = $result;
		}
		elseif ($result !== false) // the result was empty but the file was found
		{
			$class = 'Action' . ucfirst($name);
			if (class_exists($class))
			{
				$actionObj = new $class($this);
				if (!is_a($actionObj, 'WikiniAction'))
				{
					die(_t('INVALID_ACTION')." '$name': "._t('INCORRECT_CLASS'));
				}
			}
		}
		$this->actionObjects[$name] = &$actionObj;
		return $actionObj;
	}

	/**
	 * Retrieves the list of existing actions
	 * @return array An unordered array of all the available actions.
	 */
	function GetActionsList()
	{
		$action_path = $this->GetConfigValue('action_path');
		$list = array();
		if ($dh = opendir($action_path))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches))
				{
					$list[] = $matches[1];
				}
			}
		}
		return $list;
	}

	/**
	 * Retrieves the list of existing handlers
	 * @return array An unordered array of all the available handlers.
	 */
	function GetHandlersList()
	{
		$handler_path = $this->GetConfigValue('handler_path') . '/page/';
		$list = array();
		if ($dh = opendir($handler_path))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (preg_match('/^([a-zA-Z-0-9]+)(.class)?.php$/', $file, $matches))
				{
					$list[] = $matches[1];
				}
			}
		}
		return $list;
	}

	function Method($method) {
		if (!$handler = $this->page["handler"]) $handler = "page";
		$methodLocation = $handler."/".$method.".php";
		return $this->IncludeBuffered($methodLocation, "<i>"._t('UNKNOWN_METHOD')." \"$methodLocation\"</i>", "", $this->config["handler_path"]);
	}
	function Format($text, $formatter = "wakka") {
		return $this->IncludeBuffered("formatters/".$formatter.".php", "<i>"._t('FORMATTER_NOT_FOUND')." \"$formatter\"</i>", compact("text")); 
	}



	// USERS
	function LoadUser($name, $password = 0) { return $this->LoadSingle("select * from ".$this->config["table_prefix"]."users where name = '".mysql_real_escape_string($name)."' ".($password === 0 ? "" : "and password = '".mysql_real_escape_string($password)."'")." limit 1"); }
	function LoadUsers() { return $this->LoadAll("select * from ".$this->config["table_prefix"]."users order by name"); }
	function GetUserName() { if ($user = $this->GetUser()) $name = $user["name"]; else $name = $_SERVER["REMOTE_ADDR"]; return $name; }
	function GetUser() { return (isset($_SESSION["user"]) ? $_SESSION["user"] : '');}
	function SetUser($user, $remember=0) { $_SESSION["user"] = $user; $this->SetPersistentCookie("name", $user["name"], $remember); $this->SetPersistentCookie("password", $user["password"], $remember); $this->SetPersistentCookie("remember", $remember, $remember); }
	function LogoutUser() { $_SESSION["user"] = ""; $this->DeleteCookie("name"); $this->DeleteCookie("password"); }
	function UserWantsComments() { if (!$user = $this->GetUser()) return false; return ($user["show_comments"] == "Y"); }
	function GetParameter($parameter, $default = '') { return (isset($this->parameter[$parameter]) ? $this->parameter[$parameter] : $default); }


	// COMMENTS
	/**
	 * Charge les commentaires relatifs a une page.
	 * 
	 * @param string $tag Nom de la page. Ex : "PagePrincipale"
	 * @return array Tableau contenant tous les commentaires et leurs
	 * proprietes correspondantes.
	 */
	function LoadComments($tag)
	{
		return $this->LoadAll(
			"select * " .
			"from ".$this->config["table_prefix"]."pages " .
			"where comment_on = '".mysql_real_escape_string($tag)."' " .
			"and latest = 'Y' " .
			"order by substring(tag, 8) + 0");
	}
	/**
	 * Charge les derniers commentaires de toutes les pages.
	 * 
	 * @param int $limit Nombre de commentaires charges.
	 *                   0 par d?faut (ie tous les commentaires).
	 * @return array Tableau contenant chaque commentaire et ses
	 *               proprietes associees.
	 * @todo Ajouter le parametre $start pour permettre une pagination
	 *       des commentaires : ->LoadRecentComments(10, 10)
	 */
	function LoadRecentComments($limit = 0)
	{
		// The part of the query which limit the number of comments  
		if(is_numeric($limit) && $limit > 0) $lim = " limit ".$limit;
		else $lim = "";

		// Query
		return $this->LoadAll(
			"select * " .
			"from " . $this->config["table_prefix"] . "pages " .
			"where comment_on != '' " .
			"and latest = 'Y' " .
			"order by time desc " .
			$lim);
	}
	function LoadRecentlyCommented($limit = 50)
	{
		$pages = array();

		// NOTE: this is really stupid. Maybe my SQL-Fu is too weak, but apparently there is no easier way to simply select
		//       all comment pages sorted by their first revision's (!) time. ugh!

		// load ids of the first revisions of latest comments. err, huh?
		if ($ids = $this->LoadAll("select min(id) as id from ".$this->config["table_prefix"]."pages where comment_on != '' group by tag order by id desc"))
		{
			// load complete comments
			$num=0;
			foreach ($ids as $id)
			{
				$comment = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where id = '".$id["id"]."' limit 1");
				if (!isset($comments[$comment["comment_on"]]) && $num < $limit)
				{
					$comments[$comment["comment_on"]] = $comment;
					$num++;
				}
			}

			// now load pages
			if ($comments)
			{
				// now using these ids, load the actual pages
				foreach ($comments as $comment)
				{
					$page = $this->LoadPage($comment["comment_on"]);
					$page["comment_user"] = $comment["user"];
					$page["comment_time"] = $comment["time"];
					$page["comment_tag"] = $comment["tag"];
					$pages[] = $page;
				}
			}
		}
		// load tags of pages 
		//return $this->LoadAll("select comment_on as tag, max(time) as time, tag as comment_tag, user from ".$this->config["table_prefix"]."pages where comment_on != '' group by comment_on order by time desc");
		return $pages;
	}



	// ACCESS CONTROL
	// returns true if logged in user is owner of current page, or page specified in $tag
	function UserIsOwner($tag = "") {
		// check if user is logged in
		if (!$this->GetUser()) return false;

		// set default tag
		if (!$tag = trim($tag)) $tag = $this->GetPageTag();

		// check if user is owner
		if ($this->GetPageOwner($tag) == $this->GetUserName()) return true;
	}

	/**
	 * @param string $group The name of a group
	 * @return string the ACL associated with the group $gname
	 * @see UserIsInGroup to check if a user belongs to some group
	 */
	function GetGroupACL($group)
	{
		if (array_key_exists($group, $this->_groupsCache))
		{
			return $this->_groupsCache[$group];
		}
		return $this->_groupsCache[$group] = $this->GetTripleValue($group, WIKINI_VOC_ACLS, GROUP_PREFIX);
	}

	/**
	 * Checks if a new group acl is not defined recursively
	 * (this method expects that groups that are already defined are not themselves defined recursively...)
	 * @param string $gname The name of the group
	 * @param string $acl The new acl for that group
	 * @return boolean True iff the new acl defines the group recursively
	 */
	function MakesGroupRecursive($gname, $acl, $origin = null, $checked = array())
	{
		$gname = strtolower($gname);
		if ($origin === null)
		{
			$origin = $gname;
		}
		elseif ($gname === $origin)
		{
			return true;
		}
		foreach (explode("\n", $acl) as $line)
		{
			if (!$line) continue;
			if ($line[0] == '!')
			{
				$line = substr($line, 1);
			}
			if (!$line) continue;
			if ($line[0] == '@')
			{
				$line = substr($line, 1);
				if (!in_array($line, $checked))
				{
					if ($this->MakesGroupRecursive($line, $this->GetGroupACL($line), $origin, $checked))
					{
						return true;
					}
				}
			}
		}
		$checked[] = $gname;
		return false;
	}

	/**
	 * Sets a new ACL to a given group
	 * @param string $gname The name of a group
	 * @param string $acl The new ACL to associate with the group $gname
	 * @return int 0 if successful, a triple error code or a specific error code:
	 * 	1000 if the new value would define the group recursively
	 * 	1001 if $gname is not named with alphanumeric chars
	 * @see GetGroupACL
	 */
	function SetGroupACL($gname, $acl)
	{
		if (preg_match('/[^A-Za-z0-9]/', $gname))
		{
			return 1001;
		}
		$old = $this->GetGroupACL($gname);
		if ($this->MakesGroupRecursive($gname, $acl))
		{
			return 1000;
		}
		$this->_groupsCache[$gname] = $acl;
		if ($old === null)
		{
			return $this->InsertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
		}
		elseif ($old === $acl)
		{
			return 0; // nothing has changed
		}
		else
		{
			return $this->UpdateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
		}
	}
	/**
	 * @return array The list of all group names
	 */
	function GetGroupsList()
	{
		$res = $this->GetMatchingTriples(GROUP_PREFIX . '%', WIKINI_VOC_ACLS_URI);
		$prefix_len = strlen(GROUP_PREFIX);
		$list = array();
		foreach ($res as $line)
		{
			$list[] = substr($line['resource'], $prefix_len);
		}
		return $list;
	}

	/**
	 * @param string $group The name of a group
	 * @return boolean true iff the user is in the given $group 
	 */
	function UserIsInGroup($group, $user = null, $admincheck = true)
	{
		return $this->CheckACL($this->GetGroupACL($group), $user, $admincheck);
	}

	/**
	 * Checks if a given user is andministrator
	 * @param string $user The name of the user (defaults to the current user if not given)
	 * @return boolean true iff the user is an administrator
	 */
	function UserIsAdmin($user = null)
	{
		return $this->UserIsInGroup(ADMIN_GROUP, $user, false);
	}

	function GetPageOwner($tag = "", $time = "") { if (!$tag = trim($tag)) $tag = $this->GetPageTag(); if ($page = $this->LoadPage($tag, $time)) return $page["owner"]; }
	function SetPageOwner($tag, $user) {
		// check if user exists
		if (!$this->LoadUser($user)) return;

		// updated latest revision with new owner
		$this->Query("update ".$this->config["table_prefix"]."pages set owner = '".mysql_real_escape_string($user)."' where tag = '".mysql_real_escape_string($tag)."' and latest = 'Y' limit 1");
	}
	function LoadAcl($tag, $privilege, $useDefaults = 1) {
		if ((!$acl = $this->LoadSingle("select * from ".$this->config["table_prefix"]."acls where page_tag = '".mysql_real_escape_string($tag)."' and privilege = '".mysql_real_escape_string($privilege)."' limit 1")) && $useDefaults)
		{
			$acl = array("page_tag" => $tag, "privilege" => $privilege, "list" => $this->GetConfigValue("default_".$privilege."_acl"));
		}
		return $acl;
	}
	function SaveAcl($tag, $privilege, $list) {
		if ($this->LoadAcl($tag, $privilege, 0)) $this->Query("update ".$this->config["table_prefix"]."acls set list = '".mysql_real_escape_string(trim(str_replace("\r", "", $list)))."' where page_tag = '".mysql_real_escape_string($tag)."' and privilege = '".mysql_real_escape_string($privilege)."' limit 1");
		else $this->Query("insert into ".$this->config["table_prefix"]."acls set list = '".mysql_real_escape_string(trim(str_replace("\r", "", $list)))."', page_tag = '".mysql_real_escape_string($tag)."', privilege = '".mysql_real_escape_string($privilege)."'");
	}
	// returns true if $user (defaults to current user) has access to $privilege on $page_tag (defaults to current page)
	function HasAccess($privilege, $tag = "", $user = "") {
		// set defaults
		if (!$tag = trim($tag)) $tag = $this->GetPageTag();
		if (!$user)
		{
			// if current user is owner, return true. owner can do anything!
			if ($this->UserIsOwner($tag)) return true;
			$user = $this->GetUserName();
		}

		// TODO: we might want to check if a given $user (other than the current user)
		// has access to a given page. If the $user is the owner of that page,
		// this method might give a wrong result (because we can't check that)

		// load acl
		$acl = $this->LoadAcl($tag, $privilege);

		// fine fine... now go through acl
		return $this->CheckACL($acl["list"], $user);
	}

	/**
	 * Checks if some $user satisfies the given $acl
	 * @param string $acl The acl to check, in the same format than for pages ACL's
	 * @param string $user The name of the user that must satisfy the ACL. By default
	 * the current remote user.
	 * @return bool True if the $user satisfies the $acl, false otherwise
	 */
	function CheckACL($acl, $user = null, $admincheck = true)
	{
		if (!$user)
		{
			$user = $this->GetUserName();
		}

		if ($admincheck && $this->UserIsAdmin($user))
		{
			return true;
		}


		foreach (explode("\n", $acl) as $line)
		{
			$line = trim($line);

			// check for inversion character "!"
			if (preg_match("/^[!](.*)$/", $line, $matches))
			{
				$negate = 1;
				$line = $matches[1];
			}
			else
			{
				$negate = 0;
			}

			// if there's still anything left... lines with just a "!" don't count!
			if ($line)
			{
				switch ($line[0])
				{
				case "#": // comments
					break;
				case "*": // everyone
					return !$negate;
				case "+": // registered users
					if (!$this->LoadUser($user)) 
					{
						return $negate;
					}
					else
					{
						return !$negate;
					}
				case '@': // groups
					$gname = substr($line, 1);
					// paranoiac: avoid line = '@'
					if ($gname && $this->UserIsInGroup($gname, $user, false /* we have allready checked if user was an admin */))
					{
						return !$negate;
					}
					break;
				default: // simple user entry
					if ($line == $user)
					{
						return !$negate;
					}
				}
			}
		}

		// tough luck.
		return false;
	}

	/**
	 * Loads the module ACL for a certain module
	 * @param string $module The name of the module
	 * @param string $module_type The type of module: 'action' or 'handler'
	 * @return mixed The ACL for the given module or <tt>null</tt> if no such
	 * ACL was found (which should probably be interpreted as '*'). 
	 */
	function GetModuleACL($module, $module_type)
	{
		$module = strtolower($module);
		switch ($module_type)
		{
		case 'action':
			if (array_key_exists($module, $this->_actionsAclsCache))
			{
				$acl = $this->_actionsAclsCache[$module];
				break;
			}
			$this->_actionsAclsCache[$module] = $acl = $this->GetTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_ACTIONS_PREFIX);
			if ($acl === null)
			{
				$action = &$this->GetActionObject($module);
				if (is_object($action))
				{
					return $this->_actionsAclsCache[$module] = $action->GetDefaultACL();
				}
			}
			break;
		case 'handler':
			$acl = $this->GetTripleValue($module, WIKINI_VOC_ACLS, WIKINI_VOC_HANDLERS_PREFIX);
			break;
		default:
			return null; // TODO error msg ?
		}
		return $acl === null ? '*' : $acl;
	}

	/**
	 * Sets the $acl for a given $module
	 * @param string $module The name of the module
	 * @param string $module_type The type of module ('action' or 'handler')
	 * @param string $acl The new ACL for that module
	 * @return 0 on success, > 0 on error (see InsertTriple and UpdateTriple)
	 */
	function SetModuleACL($module, $module_type, $acl)
	{
		$module = strtolower($module);
		$voc_prefix = $module_type == 'action' ? WIKINI_VOC_ACTIONS_PREFIX : WIKINI_VOC_HANDLERS_PREFIX;
		$old = $this->GetTripleValue($module, WIKINI_VOC_ACLS, $voc_prefix);
		if ($module_type == 'action')
		{
			$this->_actionsAclsCache[$module] = $acl;
		}
		if ($old === null)
		{
			return $this->InsertTriple($module, WIKINI_VOC_ACLS, $acl, $voc_prefix);
		}
		elseif ($old === $acl)
		{
			return 0; // nothing has changed
		}
		else
		{
			return $this->UpdateTriple($module, WIKINI_VOC_ACLS, $old, $acl, $voc_prefix);
		}
	}

	/**
	 * Checks if a $user satisfies the ACL to access a certain $module
	 * @param string $module The name of the module to access
	 * @param string $module_type The type of the module ('action' or 'handler')
	 * @param string $user The name of the user. By default
	 * the current remote user.
	 * @return bool True if the $user has access to the given $module, false otherwise.
	 */
	function CheckModuleACL($module, $module_type, $user = null)
	{
		$acl = $this->GetModuleACL($module, $module_type);
		if ($acl === null) return true; // undefined ACL means everybody has access
		return $this->CheckACL($acl, $user);
	}


	// MAINTENANCE
	function Maintenance() {
		// purge referrers
		$this->PurgeReferrers();
		// purge old page revisions
		$this->PurgePages();
	}



	// THE BIG EVIL NASTY ONE!
	function Run($tag, $method = "") {
		if(!($this->GetMicroTime()%9)) $this->Maintenance(); 

		$this->ReadInterWikiConfig();

		// do our stuff!
		if (!$this->method = trim($method)) $this->method = "show";
		if (!$this->tag = trim($tag)) $this->Redirect($this->href("", $this->config["root_page"]));
		if ((!$this->GetUser() && isset($_COOKIE["name"])) && ($user = $this->LoadUser($_COOKIE["name"], $_COOKIE["password"]))) $this->SetUser($user, $_COOKIE["remember"]);
		$this->SetPage($this->LoadPage($tag, (isset($_REQUEST["time"]) ? $_REQUEST["time"] :'')));
		$this->LogReferrer();

		// correction pour un support plus facile de nouveaux handlers
		if ($this->CheckModuleACL($this->method, 'handler'))
		{
			echo $this->Method($this->method);
		}
		else
		{
			echo _t('HANDLER_NO_ACCESS');
		}

		// action redirect: aucune redirection n'a eu lieu, effacer la liste des redirections precedentes
		if(!empty($_SESSION['redirects'])) session_unregister('redirects');
	}
}



// stupid version check
if (!isset($_REQUEST)) die(_t('NO_REQUEST_FOUND'));

// workaround for the amazingly annoying magic quotes.
function magicQuotesSuck(&$a)
{
	if (is_array($a))
	{
		foreach ($a as $k => $v)
		{
			if (is_array($v))
				magicQuotesSuck($a[$k]);
			else
				$a[$k] = stripslashes($v);
		}
	}
}

if (get_magic_quotes_runtime())
{
    // Deactivate
    set_magic_quotes_runtime(false);
}

if (get_magic_quotes_gpc())
{
	magicQuotesSuck($_POST);
	magicQuotesSuck($_GET);
	magicQuotesSuck($_COOKIE);
}


// default configuration values
$wakkaConfig= array();
$_rewrite_mode = detectRewriteMode();
$wakkaDefaultConfig = array(
	'wakka_version'		=> '',
	'wikini_version'	=> '',
	'yeswiki_version'	=> '',
	'yeswiki_release'	=> '',
	'debug'				=> 'no',
	"mysql_host"		=> "localhost",
	"mysql_database"	=> '',
	"mysql_user"		=> '',
	"mysql_password"	=> '',
	"table_prefix"		=> "yeswiki_",
	"base_url"			=> computeBaseURL($_rewrite_mode),
	"rewrite_mode"		=> $_rewrite_mode,
	'meta_keywords'		=> '',
	'meta_description'	=> '',
	"action_path"		=> "actions",
	"handler_path"		=> "handlers",
	"header_action"		=> "header",
	"footer_action"		=> "footer",
	"navigation_links"		=> "DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur",
	"referrers_purge_time"	=> 24,
	"pages_purge_time"	=> 90,
	"default_write_acl"	=> "*",
	"default_read_acl"	=> "*",
	"default_comment_acl"	=> "@admins",
	"preview_before_save"	=> 0,
	'allow_raw_html'	=> false);
unset($_rewrite_mode);

// load config
if (!$configfile = GetEnv("WAKKA_CONFIG")) $configfile = "wakka.config.php";
if (file_exists($configfile)) {
	include($configfile);
} 
else {
	// we must init language file without loading the page's settings.. to translate some default config settings
	$wakkaDefaultConfig["root_page"] =_t('HOMEPAGE_WIKINAME');
	$wakkaDefaultConfig["wakka_name"] = _t('MY_YESWIKI_SITE');
}
$wakkaConfigLocation = $configfile;
$wakkaConfig = array_merge($wakkaDefaultConfig, $wakkaConfig);


// check for locking
if (file_exists("locked")) {
	// read password from lockfile
	$lines = file("locked");
	$lockpw = trim($lines[0]);

	// is authentification given?
	if (isset($_SERVER["PHP_AUTH_USER"])) {
		if (!(($_SERVER["PHP_AUTH_USER"] == "admin") && ($_SERVER["PHP_AUTH_PW"] == $lockpw))) {
			$ask = 1;
		}
	} else {
		$ask = 1;
	}

	if ($ask) {
		header("WWW-Authenticate: Basic realm=\"".$wakkaConfig["wakka_name"]." Install/Upgrade Interface\"");
		header("HTTP/1.0 401 Unauthorized");
		echo _t("SITE_BEING_UPDATED") ;
		exit;
	}
}


// compare versions, start installer if necessary
if ($wakkaConfig["wakka_version"] && (!$wakkaConfig["wikini_version"])) { $wakkaConfig["wikini_version"]=$wakkaConfig["wakka_version"]; }
if (($wakkaConfig["wakka_version"] != WAKKA_VERSION) || ($wakkaConfig["wikini_version"] != WIKINI_VERSION)) {
	// start installer
	if (!isset($_REQUEST["installAction"]) OR !$installAction = trim($_REQUEST["installAction"])) $installAction = "default";
	include("setup/header.php");
	if (file_exists("setup/".$installAction.".php")) include("setup/".$installAction.".php"); else echo "<em>"._t("INVALID_ACTION")."</em>" ;
	include("setup/footer.php");
	exit;
}

// configuration du cookie de session
// determine le chemin pour les cookies
$a = parse_url($wakkaConfig['base_url']);
$CookiePath = dirname($a['path']);
// Fixe la gestion des cookie sous les OS utilisant le \ comme s?parteur de chemin
$CookiePath = str_replace("\\","/",$CookiePath);
// ajoute un '/' terminal sauf si on est ? la racine web
if ($CookiePath != '/') $CookiePath .= '/';
$a = session_get_cookie_params();
session_set_cookie_params($a['lifetime'],$CookiePath);
unset($a);
unset($CookiePath);

// start session
session_start();

// fetch wakka location
if (empty($_REQUEST['wiki']))
{
	// redirect to the root page
	header('Location: ' . $wakkaConfig['base_url'] . $wakkaConfig['root_page']);
	exit;
}
$wiki = $_REQUEST['wiki'];

// remove leading slash
$wiki = preg_replace("/^\//", "", $wiki);

// split into page/method, checking wiki name & method name (XSS proof)
if (preg_match('`^' . WN_TAG_HANDLER_CAPTURE . '$`', $wiki, $matches))
{
	list(, $page, $method) = $matches;
}
elseif (preg_match('`^' . WN_PAGE_TAG . '$`', $wiki))
{
	$page = $wiki;
}
else
{
	echo "<p>"._t('INCORRECT_PAGENAME')."</p>";
	exit;
}

// create wiki object
$wiki = new Wiki($wakkaConfig);

// update lang
loadpreferredI18n($page);
// check for database access
if (!$wiki->dblink)
{
	echo	"<p>"._t('DB_CONNECT_FAIL')."</p>";
	// Log error (useful to find the buggy server in a load balancing platform)
	trigger_error(_t('LOG_DB_CONNECT_FAIL'));
	exit;
}


// go!
if (!isset($method)) $method='';


// Security (quick hack)  : Check method syntax
if (!(preg_match('#^[A-Za-z0-9_]*$#',$method))) {
	$method='';
}
include('tools/prepend.php');
$wiki->Run($page, $method);

?>
