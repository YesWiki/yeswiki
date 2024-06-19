<?php

namespace YesWiki\Core\Service;

use Throwable;
use YesWiki\Security\Controller\SecurityController;

class TripleStore
{
    protected $dbService;
    protected $securityController;

    protected $cacheByResource;

    public const TYPE_URI = 'http://outils-reseaux.org/_vocabulary/type';
    public const SOURCE_URL_URI = 'http://outils-reseaux.org/_vocabulary/sourceUrl';

    public function __construct(DbService $dbService, SecurityController $securityController)
    {
        $this->dbService = $dbService;
        $this->securityController = $securityController;
        $this->cacheByResource = [];
    }

    /**
     * Retrieves a single value for a given couple (resource, property).
     *
     * @param string $resource
     *                            The resource of the triples
     * @param string $property
     *                            The property of the triple to retrieve
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     *
     * @return string the value corresponding to ($resource, $property) or null if
     *                there is no such couple in the triples table
     */
    public function getOne($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX): ?string
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
     *                         The resource of the triples or null
     * @param string $property
     *                         The property of the triple to retrieve or null
     * @param string $value
     *                         The value of the triple to retrieve or null
     * @param string $res_op
     *                         The operator of comparison between the effective resource and $resource (default: 'LIKE')
     * @param string $prop_op
     *                         The operator of comparison between the effective property and $property (default: '=')
     * @param string $val_op
     *                         The operator of comparison between the effective value and $valueq (default: '=')
     *
     * @return array The list of all the triples that match the asked criteria
     */
    public function getMatching($resource = null, $property = null, $value = null, $res_op = 'LIKE', $prop_op = '=', $val_op = '='): array
    {
        static $operators = [
            '=',
            'LIKE',
        ]; // we might want to add other operators later
        $res_op = strtoupper($res_op);
        if (!in_array($res_op, $operators)) {
            $res_op = '=';
        }
        $prop_op = strtoupper($prop_op);
        if (!in_array($prop_op, $operators)) {
            $prop_op = '=';
        }
        $val_op = strtoupper($val_op);
        if (!in_array($val_op, $operators)) {
            $val_op = '=';
        }

        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('triples');
        $where = [];
        if ($resource !== null) {
            $where[] = ' resource ' . $res_op . ' "' . $this->dbService->escape($resource) . '"';
        }
        if ($property !== null) {
            $where[] = ' property ' . $prop_op . ' "' . $this->dbService->escape($property) . '"';
        }
        if ($value !== null) {
            $where[] = ' value ' . $val_op . ' "' . $this->dbService->escape($value) . '"';
        }
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return $this->dbService->loadAll($sql);
    }

    /**
     * Retrieves all the values for a given couple (resource, property).
     *
     * @param string $resource
     *                            The resource of the triples
     * @param string $property
     *                            The property of the triple to retrieve
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to THISWIKI_PREFIX)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to WIKINI_VOC_PREFIX)
     *
     * @return array An array of the retrieved values, in the form
     *               array(
     *               0 => array(id = 7 , 'value' => $value1),
     *               1 => array(id = 34, 'value' => $value2),
     *               ...
     *               )
     */
    public function getAll($resource, $property, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX): array
    {
        $res = empty($resource) ? '' : $re_prefix . $resource;
        $prop = $prop_prefix . $property;
        if (isset($this->cacheByResource[$res])) {
            // All resource's properties was previously loaded.
            if (isset($this->cacheByResource[$res][$prop])) {
                return $this->cacheByResource[$res][$prop];
            }
            // LoadAll($sql) return an empty array when no result, do the same.
            return [];
        }
        $this->cacheByResource[$res] = [];
        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('triples') . ' WHERE ';
        if (empty($res)) { // get everything if no resource given
            $sql .= '1';
        } else {
            $sql .= 'resource = "' . $this->dbService->escape($res) . '"';
        }
        foreach ($this->dbService->loadAll($sql) as $triple) {
            if (!isset($this->cacheByResource[$res][$triple['property']])) {
                $this->cacheByResource[$res][$triple['property']] = [];
            }
            $this->cacheByResource[$res][$triple['property']][] = ['id' => $triple['id'], 'value' => $triple['value'], 'resource' => $triple['resource']];
        }
        if (isset($this->cacheByResource[$res][$prop])) {
            return $this->cacheByResource[$res][$prop];
        }

        return [];
    }

    /**
     * Checks whether a triple exists or not.
     *
     * @param string $resource
     *                            The resource of the triple to find
     * @param string $property
     *                            The property of the triple to find
     * @param string $value
     *                            The value of the triple to find
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     *
     * @return int|null The id of the found triple or null if there is no such triple
     */
    public function exist($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX): ?int
    {
        $sql = 'SELECT id FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource = "' . $this->dbService->escape($re_prefix . $resource) . '" ' . 'AND property = "' . $this->dbService->escape($prop_prefix . $property) . '" ' . 'AND value = "' . $this->dbService->escape($value) . '"';
        $triple = $this->dbService->loadSingle($sql);

        return !is_null($triple) ?
            intval($triple['id'])
            : null;
    }

    /**
     * Inserts a new triple ($resource, $property, $value) in the triples' table.
     *
     * @param string $resource
     *                            The resource of the triple to insert
     * @param string $property
     *                            The property of the triple to insert
     * @param string $value
     *                            The value of the triple to insert
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     *
     * @return int An error code: 0 (success), 1 (failure) or 3 (already exists)
     */
    public function create($resource, $property, $value, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

        if ($this->exist($res, $property, $value, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the cache
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }

        $sql = 'INSERT INTO ' . $this->dbService->prefixTable('triples') . ' (resource, property, value)' . 'VALUES ("' . $this->dbService->escape($res) . '", "' . $this->dbService->escape($prop_prefix . $property) . '", "' . $this->dbService->escape($value) . '")';

        return $this->dbService->query($sql) ? 0 : 1;
    }

    /**
     * Updates a triple ($resource, $property, $value) in the triples' table.
     *
     * @param string $resource
     *                            The resource of the triple to update
     * @param string $property
     *                            The property of the triple to update
     * @param string $oldvalue
     *                            The old value of the triple to update
     * @param string $newvalue
     *                            The new value of the triple to update
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     *
     * @return int An error code: 0 (succ?s), 1 (?chec),
     *             2 ($resource, $property, $oldvalue does not exist)
     *             or 3 ($resource, $property, $newvalue already exists)
     */
    public function update($resource, $property, $oldvalue, $newvalue, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX)
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

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

        $sql = 'UPDATE ' . $this->dbService->prefixTable('triples') . ' SET value = "' . $this->dbService->escape($newvalue) . '" ' . 'WHERE id = ' . $id;

        return $this->dbService->query($sql) ? 0 : 1;
    }

    /**
     * Deletes a triple ($resource, $property, $value) from the triples' table.
     *
     * @param string $resource
     *                            The resource of the triple to delete
     * @param string $property
     *                            The property of the triple to delete
     * @param string $value
     *                            The value of the triple to delete. If set to <tt>null</tt>,
     *                            deletes all the triples corresponding to ($resource, $property). (defaults to <tt>null</tt>)
     * @param string $re_prefix
     *                            The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string $prop_prefix
     *                            The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @param string $extraSQL
     *                            Extra SQL query (null by default)
     */
    public function delete($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX, $extraSQL = null): bool
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

        $sql = 'DELETE FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource = "' . $this->dbService->escape($res) . '" ' . 'AND property = "' . $this->dbService->escape($prop_prefix . $property) . '" ';
        if ($value !== null) {
            $valueQuery = 'AND value = "' . $this->dbService->escape($value) . '"';
            $sql .= $valueQuery;
        } else {
            $valueQuery = '';
        }
        if ($extraSQL !== null) {
            $extraSQLQuery = 'AND (' . $extraSQL . ')';
            $sql .= $extraSQLQuery;
        } else {
            $extraSQLQuery = '';
        }
        // invalidate the cache
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }

        try {
            if ($this->dbService->query($sql) === false) {
                return false;
            }
            $sql = <<<SQL
            SELECT `id` FROM {$this->dbService->prefixTable('triples')} 
              WHERE `resource` = "{$this->dbService->escape($re_prefix . $resource)}" 
                AND `property` = "{$this->dbService->escape($prop_prefix . $property)}" 
                $valueQuery
                $extraSQLQuery
                ;
            SQL;
            $triple = $this->dbService->loadSingle($sql);

            return is_null($triple);
        } catch (Throwable $th) {
            return false;
        }
    }
}
