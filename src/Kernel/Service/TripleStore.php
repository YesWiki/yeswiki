<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Kernel\Database\SqlFragment;

class TripleStore
{
    protected $dbService;
    protected $hibernationService;

    protected $cacheByResource;
    protected array $matchingCache = [];

    public const TYPE_URI = 'http://outils-reseaux.org/_vocabulary/type';
    public const SOURCE_URL_URI = 'http://outils-reseaux.org/_vocabulary/sourceUrl';

    public function __construct(DbService $dbService, HibernationService $hibernationService)
    {
        $this->dbService = $dbService;
        $this->hibernationService = $hibernationService;
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
     * @return list<array<string, mixed>> The list of all the triples that match the asked criteria
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
        $params = [];
        // The operators are whitelisted above and stay in the text -- an operator cannot be
        // bound. The values bind, and `%` in one is deliberately left alone: a caller asking
        // for LIKE wants pattern semantics, which is why nothing here defuses wildcards the
        // way SearchIndexQuery does.
        if ($resource !== null) {
            $where[] = ' resource ' . $res_op . ' ?';
            $params[] = $resource;
        }
        if ($property !== null) {
            $where[] = ' property ' . $prop_op . ' ?';
            $params[] = $property;
        }
        if ($value !== null) {
            $where[] = ' value ' . $val_op . ' ?';
            $params[] = $value;
        }
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Add a local in instance cache
        // TODO This cache should be shared with "$this->cacheByResource[$res][$prop]"
        //      - but $res in this cache can be with a $re_prefix
        //      - but $prop in this cache can be with $prop_prefix
        //      - $re_prefix and $prop_prefix are not parameters of the getMatching function
        //
        // Keyed on the statement AND its values. Before bindings the values were inside the
        // statement, so the statement alone identified the query; now every lookup for a given
        // shape produces the SAME text, and keying on it alone would serve the first
        // resource's triples to every subsequent resource asking the same shape of question.
        $key = $sql . '|' . serialize($params);
        if (!array_key_exists($key, $this->matchingCache)) {
            $this->matchingCache[$key] = $this->dbService->loadAll($sql, $params);
        }

        return $this->matchingCache[$key];
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
        $this->loadResource($res);
        if (isset($this->cacheByResource[$res][$prop])) {
            return $this->cacheByResource[$res][$prop];
        }

        return [];
    }

    /**
     * Whether this resource carries any triple at all, whatever the property.
     *
     * Answered from the same per-resource cache `getAll()` fills, which is the point:
     * `GroupManager::groupExists()` asked it through `getMatching()` instead, whose cache is
     * a different one keyed by SQL text. The two never shared, so checking a group existed
     * and then reading its members ran the identical
     * `SELECT * FROM triples WHERE resource = 'ThisWikiGroup:admins'` twice -- on every
     * `isInGroup()`, which is every ACL check on every page.
     */
    public function hasAnyProperty(?string $resource, string $re_prefix = THISWIKI_PREFIX): bool
    {
        $res = empty($resource) ? '' : $re_prefix . $resource;
        $this->loadResource($res);

        return $this->cacheByResource[$res] !== [];
    }

    /**
     * Read every triple of one resource into the cache, once. One query per resource, not
     * one per property: a resource has a handful of triples and the caller almost always
     * goes on to ask about another of them.
     */
    private function loadResource(string $res): void
    {
        if (isset($this->cacheByResource[$res])) {
            return;
        }

        $this->cacheByResource[$res] = [];
        $sql = 'SELECT * FROM ' . $this->dbService->prefixTable('triples') . ' WHERE ';
        $params = [];
        if (empty($res)) { // get everything if no resource given
            $sql .= '1';
        } else {
            $sql .= 'resource = ?';
            $params[] = $res;
        }
        foreach ($this->dbService->loadAll($sql, $params) as $triple) {
            if (!isset($this->cacheByResource[$res][$triple['property']])) {
                $this->cacheByResource[$res][$triple['property']] = [];
            }
            $this->cacheByResource[$res][$triple['property']][] = ['id' => $triple['id'], 'value' => $triple['value'], 'resource' => $triple['resource']];
        }
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
        $sql = 'SELECT id FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource = ? AND property = ? AND value = ?';
        $triple = $this->dbService->loadSingle($sql, [$re_prefix . $resource, $prop_prefix . $property, $value]);

        return !is_null($triple) ?
            intval($triple['id'])
            : null;
    }

    /**
     * Deletes every triple for a given resource, whatever their property.
     *
     * @param string $resource
     *                          The resource of the triples to delete
     * @param string $re_prefix
     *                          The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     */
    public function deleteAll($resource, $re_prefix = THISWIKI_PREFIX): bool
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

        // This line used to carry a warning: it had wrapped the value in DOUBLE quotes, and
        // SQLite's PDO driver does not escape '"', so a resource containing one broke out of
        // the literal -- found via UserManager::delete() on an account name containing '"'
        // (ticket 06). Bound, the value is not in the statement at all and the quoting rules
        // of no driver apply to it. That is the entire argument for bindings in one line.
        $sql = 'DELETE FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource = ?';

        // invalidate the caches
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }
        $this->matchingCache = [];

        return $this->dbService->query($sql, [$res]) !== false;
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
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

        if ($this->exist($res, $property, $value, '', $prop_prefix)) {
            return 3;
        }

        // invalidate the caches
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }
        $this->matchingCache = [];

        $sql = 'INSERT INTO ' . $this->dbService->prefixTable('triples') . ' (resource, property, value) VALUES (?, ?, ?)';

        return $this->dbService->query($sql, [$res, $prop_prefix . $property, $value]) ? 0 : 1;
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
        if ($this->hibernationService->isWikiHibernated()) {
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

        // invalidate the caches
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }
        $this->matchingCache = [];

        $sql = 'UPDATE ' . $this->dbService->prefixTable('triples') . ' SET value = ? WHERE id = ?';

        return $this->dbService->query($sql, [$newvalue, $id]) ? 0 : 1;
    }

    /**
     * Deletes a triple ($resource, $property, $value) from the triples' table.
     *
     * @param string           $resource
     *                                      The resource of the triple to delete
     * @param string           $property
     *                                      The property of the triple to delete
     * @param string           $value
     *                                      The value of the triple to delete. If set to <tt>null</tt>,
     *                                      deletes all the triples corresponding to ($resource, $property). (defaults to <tt>null</tt>)
     * @param string           $re_prefix
     *                                      The prefix to add to $resource (defaults to <tt>THISWIKI_PREFIX</tt>)
     * @param string           $prop_prefix
     *                                      The prefix to add to $property (defaults to <tt>WIKINI_VOC_PREFIX</tt>)
     * @param SqlFragment|null $extraSQL
     *                                      An extra clause to AND onto the delete, with the values it binds
     *                                      (null by default)
     */
    public function delete($resource, $property, $value = null, $re_prefix = THISWIKI_PREFIX, $prop_prefix = WIKINI_VOC_PREFIX, ?SqlFragment $extraSQL = null): bool
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }
        $res = $re_prefix . $resource;

        $sql = 'DELETE FROM ' . $this->dbService->prefixTable('triples') . ' WHERE resource = ? AND property = ? ';
        // one list, reused by the verification SELECT below, which asks the same question
        $params = [$res, $prop_prefix . $property];
        if ($value !== null) {
            $valueQuery = 'AND value = ?';
            $sql .= $valueQuery;
            $params[] = $value;
        } else {
            $valueQuery = '';
        }
        // $extraSQL is a whole extra clause a caller supplies -- so it arrives as a SqlFragment
        // (ticket 31) and brings its own values with it, rather than having had to escape them
        // into the text on the way here. It goes in after the value clause, so its parameters go
        // after that clause's too.
        $extraSQLQuery = '';
        if ($extraSQL !== null && !$extraSQL->isEmpty()) {
            $extraSQLQuery = 'AND (' . $extraSQL->sql . ')';
            $sql .= $extraSQLQuery;
            $params = [...$params, ...$extraSQL->params];
        }
        // invalidate the caches
        if (isset($this->cacheByResource[$res])) {
            unset($this->cacheByResource[$res]);
        }
        $this->matchingCache = [];

        try {
            if ($this->dbService->query($sql, $params) === false) {
                return false;
            }
            $sql = <<<SQL
            SELECT id FROM {$this->dbService->prefixTable('triples')}
              WHERE resource = ?
                AND property = ?
                $valueQuery
                $extraSQLQuery
                ;
            SQL;
            $triple = $this->dbService->loadSingle($sql, $params);

            return is_null($triple);
        } catch (\Throwable $th) {
            return false;
        }
    }
}
