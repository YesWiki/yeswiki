<?php

namespace YesWiki\Core\Service;

class TripleStore
{
    protected $wiki;
    protected $cacheByResource;

    public function __construct($wiki)
    {
        $this->wiki = $wiki;
        $this->cacheByResource = array();
    }

    /**
     * Retrieves a single value for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return string The value corresponding to ($resource, $property) or null if
     *         there is no such couple in the triples table.
     */
    public function getOne($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $this->getAll($resource, $property, $re_prefix, $prop_prefix);
        if ($res) {
            return $res[0]['value'];
        }

        return null;
    }
    
    /**
     * Retrieves all the triples that match some criteria.
     * This allows to search triples by their approximate resource or property names.
     * The allowed operators are the sql "LIKE" and the sql "=".
     *
     * Does not use the cache $this->cacheByResource.
     *
     * @param string $resource
     *            The resource of the triples or null
     * @param string $property
     *            The property of the triple to retrieve or null
     * @param string $value
     *            The value of the triple to retrieve or null
     * @param string $res_op
     *            The operator of comparison between the effective resource and $resource (default: 'LIKE')
     * @param string $prop_op
     *            The operator of comparison between the effective property and $property (default: '=')
     * @return array The list of all the triples that match the asked criteria
     */
    public function getMatching($resource = null, $property = null, $value = null, $res_op = 'LIKE', $prop_op = '=')
    {
        static $operators = array(
            '=',
            'LIKE'
        ); // we might want to add other operators later
        $res_op = strtoupper($res_op);
        if (! in_array($res_op, $operators)) {
            $res_op = '=';
        }

        $sql = 'SELECT * FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'triples ';
        $where = [];
        if ($resource !== null) {
            $where[] = 'resource ' . $res_op . ' "' . mysqli_real_escape_string($this->wiki->dblink, $resource) . '"';
        }
        if ($property !== null) {
            $prop_op = strtoupper($prop_op);
            if (! in_array($prop_op, $operators)) {
                $prop_op = '=';
            }

            $where[] = ' property ' . $prop_op . ' "' . mysqli_real_escape_string($this->wiki->dblink, $property) . '"';
        }
        if ($value !== null) {
            $where[] = ' value = "' . mysqli_real_escape_string($this->wiki->dblink, $value) . '"';
        }
        if( count($where)>0 ) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return $this->wiki->LoadAll($sql);
    }

    /**
     * Retrieves all the values for a given couple (resource, property)
     *
     * @param string $resource
     *            The resource of the triples
     * @param string $property
     *            The property of the triple to retrieve
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to THISWIKI_PREFIX)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to WIKINI_VOC_PREFIX)
     * @return array An array of the retrieved values, in the form
     *         array(
     *         0 => array(id = 7 , 'value' => $value1),
     *         1 => array(id = 34, 'value' => $value2),
     *         ...
     *         )
     */
    public function getAll($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;
        $prop = $prop_prefix . $property ;
        if (isset($this->cacheByResource[$res])) {
            // All resource's properties was previously loaded.
            if (isset($this->cacheByResource[$res][$prop])) {
                return $this->cacheByResource[$res][$prop] ;
            }
            // LoadAll($sql) return an empty array when no result, do the same.
            return array();
        }
        $this->cacheByResource[$res] = array();
        $sql = 'SELECT * FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->wiki->dblink, $res) . '"' ;
        foreach ($this->wiki->LoadAll($sql) as $triple) {
            if (! isset($this->cacheByResource[$res][ $triple['property'] ])) {
                $this->cacheByResource[$res][ $triple['property'] ] = array();
            }
            $this->cacheByResource[$res][ $triple['property'] ][] = array( 'id'=>$triple['id'], 'value'=>$triple['value']) ;
        }
        if (isset($this->cacheByResource[$res][$prop])) {
            return $this->cacheByResource[$res][$prop] ;
        }
        return array() ;
    }

    /**
     * Checks whether a triple exists or not
     *
     * @param string $resource
     *            The resource of the triple to find
     * @param string $property
     *            The property of the triple to find
     * @param string $value
     *            The value of the triple to find
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @param
     *            int The id of the found triple or 0 if there is no such triple.
     */
    public function exist($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $sql = 'SELECT id FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->wiki->dblink, $re_prefix . $resource) . '" ' . 'AND property = "' . mysqli_real_escape_string($this->wiki->dblink, $prop_prefix . $property) . '" ' . 'AND value = "' . mysqli_real_escape_string($this->wiki->dblink, $value) . '"';
        $res = $this->wiki->LoadSingle($sql);
        if (! $res) {
            return 0;
        }

        return $res['id'];
    }

    /**
     * Inserts a new triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to insert
     * @param string $property
     *            The property of the triple to insert
     * @param string $value
     *            The value of the triple to insert
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (success), 1 (failure) or 3 (already exists)
     */
    public function create($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        if ($this->exist($res, $property, $value, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }

        $sql = 'INSERT INTO ' . $this->wiki->GetConfigValue('table_prefix') . 'triples (resource, property, value)' . 'VALUES ("' . mysqli_real_escape_string($this->wiki->dblink, $res) . '", "' . mysqli_real_escape_string($this->wiki->dblink, $prop_prefix . $property) . '", "' . mysqli_real_escape_string($this->wiki->dblink, $value) . '")';
        return $this->wiki->Query($sql) ? 0 : 1;
    }

    /**
     * Updates a triple ($resource, $property, $value) in the triples' table
     *
     * @param string $resource
     *            The resource of the triple to update
     * @param string $property
     *            The property of the triple to update
     * @param string $oldvalue
     *            The old value of the triple to update
     * @param string $newvalue
     *            The new value of the triple to update
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @return int An error code: 0 (succ?s), 1 (?chec),
     *         2 ($resource, $property, $oldvalue does not exist)
     *         or 3 ($resource, $property, $newvalue already exists)
     */
    public function update($resource, $property, $oldvalue, $newvalue, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        $id = $this->exist($res, $property, $oldvalue, '', $prop_prefix);
        if (!$id) {
            return 2;
        }

        if ($this->exist($res, $property, $newvalue, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }

        $sql = 'UPDATE ' . $this->wiki->GetConfigValue('table_prefix') . 'triples ' . 'SET value = "' . mysqli_real_escape_string($this->wiki->dblink, $newvalue) . '" ' . 'WHERE id = ' . $id;
        return $this->wiki->Query($sql) ? 0 : 1;
    }

    /**
     * Deletes a triple ($resource, $property, $value) from the triples' table
     *
     * @param string $resource
     *            The resource of the triple to delete
     * @param string $property
     *            The property of the triple to delete
     * @param string $value
     *            The value of the triple to delete. If set to <tt>null</tt>,
     *            deletes all the triples corresponding to ($resource, $property). (defaults to <tt>null</tt>)
     * @param string $re_prefix
     *            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     */
    public function delete($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        $res = $re_prefix . $resource ;

        $sql = 'DELETE FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'triples ' . 'WHERE resource = "' . mysqli_real_escape_string($this->wiki->dblink, $res) . '" ' . 'AND property = "' . mysqli_real_escape_string($this->wiki->dblink, $prop_prefix . $property) . '" ';
        if ($value !== null) {
            $sql .= 'AND value = "' . mysqli_real_escape_string($this->wiki->dblink, $value) . '"';
        }

        // invalidate the cache
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }

        $this->wiki->Query($sql);
    }
}
