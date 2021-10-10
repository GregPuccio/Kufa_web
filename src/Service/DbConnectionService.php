<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/18/17
 * Time: 6:33 AM
 */

namespace App\Service;


use \DB\SQL;

class DbConnectionService extends \Prefab
{

    protected static $db_inst = null;

    /**
     * Return the db object per the configuration
     *
     * @return SQL|null
     */
    public function getDb()
    {
        if (!self::$db_inst) {
            try {
                $host = getenv('DB_HOST');
                $port = getenv('DB_PORT');
                $name = getenv('DB_NAME');
                $user = getenv('DB_USER');
                $pass = getenv('DB_PASSWORD');


                self::$db_inst =
                    new SQL(
                        "mysql:host={$host};port={$port};dbname={$name}",
                        $user,
                        $pass,
                        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION)
                    );
            } catch (\Exception $e) {
                // LoggerService::crit("Error connecting to database. " . $e->getMessage());
                die("Error connecting to database");
                //exit;
            }

        }
        return self::$db_inst;
    }

    /**
     * Return and ActiveRecord for the specified table
     *
     * @param $table
     * @param array|null|string $fields
     *
     * @return SQL\Mapper
     */
    public function getAR($table, $fields = null)
    {
        return new SQL\Mapper($this->getDb(), $table, $fields);
    }

    /**
     * Get ActiveRecord by Primary Key value
     * Scheme checked for single primary key field. Throws if there is not exactly one field
     *
     * @param string $table
     * @param string|int $pk
     * @param array|null|string $fields
     *
     * @return SQL\Mapper
     * @throws \Exception
     */
    public function getARbyPK($table, $pk, $fields = null)
    {
        $obj = $this->getAR($table, $fields);
        // If the active record has one field with pkey=true we can automate this
        $pkeyFields = array_filter(
            $obj->schema(),
            function ($f) {
                return $f['pkey'];
            }
        );
        if (count($pkeyFields) != 1) {
            throw new \Exception('Invalid table used in \Service\SbConnectionService\getPK');
        }
        $pkey = array_keys($pkeyFields)[0];
        $obj->load(["{$pkey}=?", $pk]);
        return $obj;
    }

    /**
     * Execute an arbitrary SQL query
     *
     * @param string $c               sql query
     * @param array|null|string $args Arguments for replaceable params
     * @param int $ttl
     * @param bool $log
     *
     * @return array|FALSE|int
     */
    public function exec($c, $args = null, $ttl = 0, $log = true)
    {
        return $this->getDb()->exec($c, $args, $ttl, $log);
    }

    /**
     * Return the first record (as an array) of sn arbitrary SQL query
     *
     * @param string $c               SQL Query
     * @param array|null|string $args Arguments for replaceable params
     * @param int $ttl
     * @param bool $log
     *
     * @return array|FALSE
     */
    public function getOne($c, $args = null, $ttl = 0, $log = true)
    {
        $r = $this->exec($c, $args, $ttl, $log);
        if (is_array($r) && count($r)) {
            return $r[0];
        } else {
            return false;
        }
    }

    /**
     * Return a normalized record as array from an active record, or false if the record is dry
     *
     * @param SQL\Mapper $obj
     *
     * @return array|FALSE
     */
    public function normalizeRecord($obj)
    {
        return !$obj || $obj->dry() ? false : $obj->cast();
    }

    /**
     * Return the first column from the first record returned from an arbitrary SQL query.
     * Return false if there is no record.
     *
     * @param string $c               SQL Query
     * @param array|null|string $args Arguments for replaceable params
     * @param int $ttl
     * @param bool $log
     *
     * @return int|string|FALSE
     */
    public function getScalar($c, $args = null, $ttl = 0, $log = true)
    {
        $r = $this->getOne($c, $args, $ttl, $log);
        return $r ? array_values($r)[0] : false;
    }

    public static function buildLimit($start = 0, $limit = 0)
    {
        if ($start != 0 || $limit != 0) {
            $start = (int)$start;
            $limit = (int)$limit;
            return " LIMIT $start,$limit";
        } else {
            return '';
        }
    }

    /**
     * @param SQL\Mapper[] $a
     *
     * @return array
     */
    public static function recastActiveRecordArray(array $a)
    {
        return array_map(
            function (SQL\Mapper $ar) {
                return $ar->cast();
            },
            $a
        );
    }

    /**
     * Delegate function - begin a DB transaction
     *
     * @return bool
     */
    public function begin()
    {
        return $this->getDb()->begin();
    }

    /**
     * Delegate function - commit a DB transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->getDb()->commit();
    }

    /**
     * Delegate function - rollback a DB transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->getDb()->rollback();
    }


    private function processFilter(array $filter, $fields = null)
    {

        $params = [];
        $useFields = count($fields);

        if (is_array($filter)) {
            $index = 0;
            $filt_array = [];
            foreach ($filter as $k => $v) {
                if (!is_array($v)) {
                    $v = [$v, 'like'];
                } else {
                    if (count($v) < 2 || !in_array($v[1], ['like', '>', '<', '>=', '<=', '=', 'raw', 'in'])) {
                        $v[1] = 'like';
                    }
                }
                $filter[$k] = $v;
                if (!empty($v[0]) && (!$useFields || $v[1] == 'raw' || in_array($k, $fields))) {
                    if ($v[1] == 'raw') {
                        $filt_array[] = $v[0];
                    //} elseif ($v[1] == 'in') {
                    //    $ip = IcanModel::createInParams($v[0], "k{$index}_");
                    //    $params = array_merge($params, $ip['params']);
                    //    $filt_array[] = "$k IN ({$ip['in_clause']})";
                    } else {
                        $pname = ":filt{$index}";
                        $params[$pname] = strtolower($v[1]) != 'like' ? $v[0] : "%{$v[0]}%";

                        $filt_array[] = "{$k} {$v[1]} {$pname}";
                    }
                }
                $index++;
            }
            $filt = implode(' AND ', $filt_array);
        } else {
            $filt = $filter;
        }
        return [$filt, $params];
    }

    /**
     * @param string $table    Table/View name
     * @param int $pageSize    Number of records per page
     * @param int $page        0-based page
     * @param array $filter    Assoc array of field=>like value
     * @param string $sort_by  Field to sort by
     * @param string $sort_dir Direction of sort (ASC/DESC)
     *
     * @param int $ttl         Cache time
     *
     * @return array[]
     */
    public function paginate($table, $pageSize, $page, array $filter = [], $sort_by = '', $sort_dir = 'ASC', $ttl = 0)
    {
        $options = [];

        $obj = DbConnectionService::instance()->getAR($table);
        $fields = $obj->fields(false);
        // Validate the sort parameter. It must match a field. If not, the result set will not be sorted.
        if (!empty($sort_by) && in_array($sort_by, $fields)) {
            if (strtoupper($sort_dir) != 'DESC') {
                $sort_dir = 'ASC';
            }
            $options['order'] = "$sort_by $sort_dir";
        }
        $f = $this->processFilter($filter, $fields);
        $p = $obj->paginate($page, $pageSize, $f, $options, $ttl);
        $p['records'] = self::recastActiveRecordArray($p['subset']);
        $p['search'] = count($filter) >= 1 && array_values($filter)[0][1] == 'like' ? array_values($filter)[0][0] : '';
        $p['filter'] = $filter;
        return $p;
    }

    public function getLock($lockName, $timeout = 5)
    {
        return $this->getScalar('SELECT GET_LOCK(:lock, :time)', [':lock' => $lockName, ':time' => $timeout]);
    }

    public function releaseLock($lockName)
    {
        return $this->getScalar('SELECT RELEASE_LOCK(:lock)', [':lock'=>$lockName]);
    }

    /**
     * Stringify function modified from F3
     *
     * @param array|string $fields
     * @param array|string $joins
     * @param null|array|string $filter
     * @param array|null $options
     *
     * @return array
     */
    function stringify($fields, $joins, $filter = null, array $options = null)
    {
        if (!$options) {
            $options = [];
        }
        $options += [
            'group' => null,
            'order' => null,
            'limit' => 0,
            'offset' => 0
        ];
        $db = $this->getDb();
        if (is_array($joins)) {
            $joins = implode(' ', $joins);
        }
        if (is_array($fields)) {
            $fields = implode(', ', $fields);
        }
        $sql = 'SELECT ' . $fields . ' FROM ' . $joins;
        $args = [];
        if (is_array($filter)) {
            // Convert single argument to an array with a single argument
            $args = isset($filter[1]) && is_array($filter[1]) ?
                $filter[1] :
                array_slice($filter, 1, null, true);
            $args = is_array($args) ? $args : [1 => $args];
            list($filter) = $filter;
        }
        if ($filter) {
            $sql .= ' WHERE ' . $filter;
        }
        if ($options['group']) {
            $sql .= ' GROUP BY ' . implode(
                    ',',
                    array_map(
                        function ($str) use ($db) {
                            return preg_replace_callback(
                                '/\b(\w+[._\-\w]*)\h*(HAVING.+|$)/i',
                                function ($parts) use ($db) {
                                    return $db->quotekey($parts[1]) .
                                        (isset($parts[2]) ? (' ' . $parts[2]) : '');
                                },
                                $str
                            );
                        },
                        explode(',', $options['group'])
                    )
                );
        }
        if ($options['order']) {
            $sql .= ' ORDER BY ' . implode(
                    ',',
                    array_map(
                        function ($str) use ($db) {
                            return preg_match(
                                '/^\h*(\w+[._\-\w]*)(?:\h+((?:ASC|DESC)[\w\h]*))?\h*$/i',
                                $str,
                                $parts
                            ) ?
                                ($db->quotekey($parts[1]) .
                                    (isset($parts[2]) ? (' ' . $parts[2]) : '')) : $str;
                        },
                        explode(',', $options['order'])
                    )
                );
        }
        // Supports mysql only
        if ($options['limit']) {
            $sql .= ' LIMIT ' . (int)$options['limit'];
        }
        if ($options['offset']) {
            $sql .= ' OFFSET ' . (int)$options['offset'];
        }

        return [$sql, $args];
    }


    /**
     * This is similar to paginate but allows us to use queries defined on the fly such as joins.
     *
     * @param int $pageSize
     * @param int $pos
     * @param array|string $fields
     * @param array|string $joins
     * @param array $filter = []
     * @param string $order = null
     * @param int $ttl = 0
     *
     * @return array
     */
    public function paginateSql($pageSize, $pos, $fields, $joins, array $filter = [], $order = null, $ttl = 0)
    {
        // Build queries...
        $f = $this->processFilter($filter);

        $count_sql = $this->stringify('count(*)', $joins, $f);
        $total = $this->getScalar($count_sql[0], $count_sql[1]);
        $count = ceil($total / $pageSize);
        $pos = max(0, min($pos, $count - 1));


        $options = [
            'order' => $order,
            'limit' => $pageSize,
            'offset' => $pos * $pageSize
        ];

        $sql = $this->stringify($fields, $joins, $f, $options);

        //LoggerService::debug(print_r($sql, true));
        //LoggerService::debug($this->db_inst->log());
        return [
            'records' => $this->exec($sql[0], $sql[1], $ttl),
            'total' => $total,
            'limit' => $pageSize,
            'count' => $count,
            'pos' => $pos < $count ? $pos : 0,
            'search' => count($filter) >= 1 && array_values($filter)[0][1] == 'like' ? array_values($filter)[0][0] : '',
            'filter' => $filter
        ];

    }

    /**
     * @param string $table_name
     * @param string|null $key_field Name of field used to select a subset of record
     * @param $primary_key_field
     * @param $order_field
     * @param int $rec_id            The record whose position is to change
     * @param string $dir            up or down
     *
     * @throws \Exception
     */
    public function reorderItems($table_name, $key_field, $primary_key_field, $order_field, $rec_id, $dir)
    {
        try {
            $db = $this;
            $db->begin();

            // Get the account
            $obj = $db->getARbyPK($table_name, $rec_id);
            if ($obj->dry()) {
                throw new \Exception(
                    'Could not find record.', "Record {$rec_id} in {$table_name} not found."
                );
            }

            if ($key_field) {
                $where = "WHERE {$key_field}=:cid";
                $params = [':cid' => $obj->{$key_field}];
            } else {
                $where = '';
                $params = null;
            }
            // Get the list of all items for this category
            $rs = $db->exec(
                "SELECT {$primary_key_field}, {$order_field} FROM {$table_name} {$where} ORDER BY {$order_field} ASC",
                $params
            );
            // Get index of entry we want
            $index = array_search($rec_id, array_column($rs, $primary_key_field));
            if ($index === false) {
                // Nothing to do?
                throw new \Exception(
                    "Invalid record {$rec_id} in {$table_name}"
                );
            }
            $count = count($rs);
            // Easiest is just to reorder the array and assign values to the display order for all rows
            // reassign order values
            for ($i = 0; $i < $count; $i++) {
                $rs[$i][$order_field] = $i;
            }
            switch ($dir) {
                case 'up':
                    if ($index > 0) {
                        $t = $rs[$index - 1][$order_field];
                        $rs[$index - 1][$order_field] = $rs[$index][$order_field];
                        $rs[$index][$order_field] = $t;
                    }
                    break;
                case 'down':
                    if ($index < $count - 1) {
                        $t = $rs[$index + 1][$order_field];
                        $rs[$index + 1][$order_field] = $rs[$index][$order_field];
                        $rs[$index][$order_field] = $t;
                    }
                    break;
            }
            // Write the changes back
            foreach ($rs as $r) {
                $db->exec(
                    "UPDATE {$table_name} SET {$order_field}=:do WHERE {$primary_key_field}=:caid",
                    [':do' => $r[$order_field], ':caid' => $r[$primary_key_field]]
                );
            }
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollback();
            //LoggerService::err($e);
            throw new \Exception('An error occurred');
        } catch (\Exception $e) {
            $db->rollback();
            //LoggerService::err($e);
            throw $e;
        }
    }

}
