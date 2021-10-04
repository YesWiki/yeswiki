<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DbService
{
    protected $params;

    protected $link;
    protected $queryLog;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->queryLog = [];

        $this->initSqlConnection();
    }

    protected function initSqlConnection()
    {
        $this->link = @mysqli_connect(
            $this->params->get('mysql_host'),
            $this->params->get('mysql_user'),
            $this->params->get('mysql_password'),
            $this->params->get('mysql_database'),
            $this->params->has('mysql_port') ? $this->params->get('mysql_port') : ini_get("mysqli.default_port")
        );
        if ($this->link) {
            if ($this->params->has('db_charset') and $this->params->get('db_charset') === 'utf8mb4') {
                // necessaire pour les versions de mysql qui ont un autre encodage par defaut
                mysqli_set_charset($this->link, 'utf8mb4');

                // dans certains cas (ovh), set_charset ne passe pas, il faut faire une requete sql
                $charset = mysqli_character_set_name($this->link);
                if ($charset != 'utf8mb4') {
                    mysqli_query($this->link, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                }
            }
        } else {
            exit(_t('DB_CONNECT_FAIL'));
        }
    }

    public function getLink()
    {
        return $this->link;
    }

    public function getQueryLog()
    {
        return $this->queryLog;
    }

    public function prefixTable($tableName)
    {
        return ' ' . $this->params->get('table_prefix') . $tableName . ' ';
    }

    public function escape($string)
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
        if ($this->params->get('debug')) {
            $start = $this->getMicroTime();
        }

        if (!$result = mysqli_query($this->link, $query)) {
            ob_end_clean();
            die('Query failed: ' . $query . ' (' . mysqli_error($this->link) . ')');
        }

        if ($this->params->get('debug')) {
            $time = $this->getMicroTime() - $start;
            $this->queryLog[] = array(
                'query' => $query,
                'time' => $time
            );
        }

        return $result;
    }

    protected function getMicroTime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /*
     * Returns the first result of the query
     * If query fails returns null
     */
    public function loadSingle($query): ?array
    {
        if ($data = $this->LoadAll($query)) {
            return $data[0];
        }
        return null;
    }

    /*
     * Fills and returns a table with the results of the query
     * Frees the SQL results set afterwards
     */
    public function loadAll($query): array
    {
        $data = array();
        if ($r = $this->query($query)) {
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }
            mysqli_free_result($r);
        }
        return $data;
    }

    public function count($query): int
    {
        return mysqli_num_rows($this->query($query));
    }
}
