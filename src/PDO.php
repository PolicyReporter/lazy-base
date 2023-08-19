<?php

namespace Policyreporter\LazyBase;

use Policyreporter\LazyBase\Lazy;
use Exception;

/**
 * This is an inner class for DB only, do not use directly
 *
 * If, for whatever reason, it is desired to build a PDO connection without
 * registering a handle with DB, this class should still be constructed as it
 * sets some default options that we want to assume are being used, specifically
 * ATTR_ERRMODE which will cause PDO to throw Exceptions instead of returning
 * false on error, and ATTR_STATEMENT_CLASS which allows us to hook in our
 * Lazy\PDOStatement class in as the PDOStatement over-ride, exposing methods
 * such as toValue and one
 */
class PDO extends PDO\CompositionWrapper
{
    private $wasInTransaction = false;
    private $debugThreshold;
    private $debugBar;

    private static $batchSize = 5000;

    protected function wrapStatement(\PDOStatement $statement): Lazy\AbstractIterator
    {
        return new Lazy\PDOStatement($statement);
    }

    /**
     * Wrap a \PDO and set some defaults for our usage
     *
     * @param \PDO $handle The database handle to use
     * @param int|bool $debugThreshold The number in miliseconds or false for no limit
     */
    public function __construct(
        \PDO $handle,
        bool $isProduction = true,
        int|bool $debugThreshold = false,
        string $explainString = '',
        ?\DebugBar\DebugBar $debugBar = null,
        bool $enableDebugBarEmulatedQuery = false,
        bool $enableDebugBarExplain = false,
    ) {
        parent::__construct($handle);
        $this->isProduction = $isProduction;
        $this->debugThreshold = $debugThreshold;
        $this->explainString = $explainString;
        $this->debugBar = $debugBar;
        $this->enableDebugBarEmulatedQuery = $enableDebugBarEmulatedQuery;
        $this->enableDebugBarExplain = $enableDebugBarExplain;
        // These options are required for this class to function properly
        // and would require careful consideration before modification, as such
        // we'll over-ride the values set on the pre-existing handle
        $overrideOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        foreach ($overrideOptions as $opt => $value) {
            $this->setAttribute($opt, $value);
        }
    }

    /**
     * Wrap these two function's return values with our custom iterator
     *
     * @return mixed The result of the function as called on the database handle
     * params as per the parent function
     * @return Lazy\PDOStatement The wrapped result handle
     */
    public function prepare($statement, $options = null)
    {
        return $this->wrapStatement($this->redirectToHandle(__FUNCTION__, ...func_get_args()));
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        return $this->wrapStatement($this->redirectToHandle(__FUNCTION__, ...func_get_args()));
    }

    /**
     * Execute a query on the database
     *
     * This function will run some packaging steps to ensure the data is properly
     * setup to be submitted to PDO then execute the statement, returning the
     * resulting PDOStatement
     *
     * <b>NB ON PARAMETER TYPING</b>
     * Please note that the actual type of these parameters will determine
     * their binding rules
     * <code>
     * run('SELECT 1 FROM members LIMIT :l', ['l' => '1'])
     * </code>
     * will result in a query error since 'l' is not a php typed int.  For safety
     * we refuse to coerce in this function, but for almost all cases, values can
     * be submitted to as strings letting the dbms coerce their types on its end
     * <code>
     * run('SELECT :1 + 1', ['l' => '1'])
     * </code>
     * will properly yield a value of two
     *
     * @param string    $query      The query to execute
     * @param mixed[]   $parameters An array of parameters required by that query
     * @return Lazy\PDOStatement      The result described by the query and parameters
     */
    public function run($query, $parameters = null)
    {
        return call_user_func([$this, 'internalRun'], $query, $parameters);
    }

    /**
     * Perform a normal 'run' operation, but with debugging
     *
     * This function will first dump an EXPLAIN {ANALYZE} about the query being executed, this doesn't usually double
     * query execution time, but it adds significant overhead, the output of the EXPLAIN {ANALYZE} is sent to the
     * error_log
     *
     * Other ANALYZE flags can be added or used from config.ini.php
     *
     * All parameters match @see run() (except force debug, which this implies)
     * @param string    $query      The query to execute
     * @param mixed[]   $parameters An array of parameters required by that query
     * @return Lazy\PDOStatement      The result described by the query and parameters
     */
    public function debug($query, $parameters = null)
    {
        if ($this->isProduction) {
            try {
                throw new \Exception('Calling PDO::debug on production');
            } catch (\Exception $e) {
                \error($e);
            }
            return call_user_func([$this, 'internalRun'], $query, $parameters, false);
        } else {
            return call_user_func([$this, 'internalRun'], $query, $parameters, true);
        }
    }

    /**
     * Internal self::run() entry point
     *
     * @see self::run( )
     * @param bool $forceDebug Force the debugging of this query, even if no debugThreshold is set
     */
    private function internalRun($query, $parameters = null, $forceDebug = false)
    {
        $prepareArguments = [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY];
        $extraneousParameters = [];
        if ($parameters !== null) {
            if (!is_array($parameters)) {
                throw new \InvalidArgumentException('A scalar was passed as the argument to PDO::run');
            }
            [$query, $parameters, $extraneousParameters] = self::explodeParams($query, $parameters);
        }
        // Now that we've expanded any potential arrays with proper query replacements, we can
        // prepare the query for execution
        $stmt = new Lazy\PDOStatement($this->prepare($query, $prepareArguments));
        if ($parameters !== null) {
            $stmt->bindParams($parameters);
        }
        $this->wasInTransaction = $this->inTransaction();
        if ($this->debugThreshold) { // 0 (all queries) || false (no queries)
            $startTime = microtime(true);
        } else {
            $startTime = 0;
        }
        try {
            // Run the statement
            $stmt->execute();
        } catch (\PDOException $e) {
            $error = ['Error Info' => $stmt->errorInfo()];
            if (count($extraneousParameters)) {
                $error['In addition following parameters had no matching replacement tokens'] = $extraneousParameters;
                \jerror($error);
            }
            throw $e;
        }
        // If false, skip all debugging, always
        if (
                $forceDebug
            ||  $this->debugThreshold === 0
            || ($this->debugThreshold !== false && (microtime(true) - $startTime) > ($this->debugThreshold / 1000))
        ) {
            if (!$this->isProduction && $this->debugBar !== null && $this->enableDebugBarEmulatedQuery) {
                $this->debugBar['db_with_replacement']->addMessage(
                    $this->assembleEmulatedQuery($query, $parameters)
                );
            }
            $explainStmt = $this->prepare(
                "EXPLAIN {$this->explainString} {$query}",
                $prepareArguments
            );
            if ($parameters !== null) {
                $explainStmt->bindParams($parameters);
            }
            try {
                $explainStmt->execute();
                $analyzeOuput = implode(PHP_EOL, $explainStmt->column());
                if ($sequenceScans = \mb_substr_count($analyzeOuput, 'Seq Scan')) {
                    $analyzeOuput .= PHP_EOL . "Including {$sequenceScans} un-indexed Sequential Scans";
                }
                $matches = [];
                if (
                    preg_match_all(
                        '/Index\s*(?:Only)?\s*Scan\s+using\s+([[:alnum:]_]+)\s+on\s+([[:alnum:]_]+)/',
                        $analyzeOuput,
                        $matches
                    )
                ) {
                    $matchesStrings = ['Indexes used:'];
                    for ($i = 0; $i < count($matches[0]); $i++) {
                        $matchesStrings[] = "{$matches[2][$i]} ( \"{$matches[1][$i]}\" )";
                    }
                    $analyzeOuput .= PHP_EOL . implode(PHP_EOL . "\t", $matchesStrings);
                }

                if (!$this->isProduction && $this->debugBar !== null && $this->enableDebugBarExplain) {
                    $this->debugBar['db_explain']->addMessage($analyzeOuput);
                }
                // Pretty arbitrarily chosen format that is easy to read, this formatting should
                // not be programmatically relied upon
                \error(PHP_EOL . $analyzeOuput);
            } catch (\PDOException $e) {
                // silence this exception since it occurred when attempting to generate debug stuff,
                // if it's a non-service availability related exception it will have triggered the
                // exception handler earlier and we wouldn't be here
            }
        }
        return $stmt;
    }

    /**
     * A more proper quoter that handles `NULL` correctly
     *
     * For ease of use this function returns a function, but this function is configured to
     * only properly escape things being sent over this handle, and will mis-escape things
     * if there is a mismatch of expected character encoding, do not reuse quoters for other
     * handles or reuse a quoter after changing the character encoding for this handle
     *
     * @return callable A function to quote things
     */
    public function quoter()
    {
        $handle = $this->handle;
        return function ($v) use ($handle) {
            if ($v === null) {
                return 'NULL';
            } else {
                return $this->handle->quote($v);
            }
        };
    }

    /**
     * Force a confirmation of the current transaction
     *
     * Prior to calling a `commit` this function can be used to confirm
     * that the current database state is valid in terms of all deferred
     * constraints.  Essentially, call this directly prior to commit will
     * guarantee the commit succeeds if this succeeds
     *
     * @throws \PDOException if the commit cannot be properly fulfilled.
     */
    public function forceTransactionCheck()
    {
        $this->run('SET CONSTRAINTS ALL IMMEDIATE');
        $this->run('SET CONSTRAINTS ALL DEFERRED');
    }

    /**
     * Assemble a static string that represents this query
     *
     * This function should NOT be used in production for querying, as it
     * circumvents parameter binding, however it is useful for snapshotting
     * the approximate query to be sent to the server, if this query contains
     * a SQL injection weakness, it is due to the limitations of this function
     * and that injection point should not be assumed to exist in a properly
     * executed call (but probably check and make sure)
     *
     * @param mixed[] $parameters @see run( )
     * @param string $query @see run( )
     *
     * @return string The assembled emulated query
     */
    public function assembleEmulatedQuery($query, array $parameters = null)
    {
        if ($this->isProduction) {
            try {
                throw new \Exception('Calling PDO::assembleEmulatedQuery on production');
            } catch (\Exception $e) {
                \error($e);
            }
        }
        if ($parameters !== null) {
            [$query, $parameters] = self::explodeParams($query, $parameters);
            $parameters = array_combine(
                array_map(function ($v) {
                    return "/(?<!:){$v}(?![a-zA-Z0-9_])/";
                }, array_keys($parameters)),
                array_map($this->quoter(), $parameters)
            );
            $query = preg_replace(array_keys($parameters), $parameters, $query);
        }
        return $query . ';';
    }

    /**
     * Explode any array parameters, adjust the query string accordingly
     *
     * There is no specific need for this function to be private, other than the
     * fact that there's nothing anyone should need this for
     *
     * @param string $query The query string
     * @param mixed[] $parameters The parameters for the query
     * @return [string,mixed[],string[]]
     *      The new Query
     *      The new parameter list
     *      Any parameters that we didn't find replacements for
     */
    private static function explodeParams(string $query, array $parameters)
    {
        $newParameters = $replacementList = $extraneousParameters = [];
        foreach ($parameters as $name => $value) {
            // Grab both the colon prepended, and unprepended values as we'll need them both
            [$bindingToken, $name] = self::varNameAndToken($name);
            // Construct our replacement key, we also use this to verify the existence of the token
            // [a-zA-Z0-9_] matches legal characters for use in bind tokens i.e. :first-param is an
            // invalid bindToken
            $searchKey = "/(?<!:){$bindingToken}(?![a-zA-Z0-9_])/";
            if (!preg_match($searchKey, $query)) {
                // We didn't find this parameter, we're done with it,
                // but keep a record for debugging
                $extraneousParameters[] = $bindingToken;
            } elseif (!is_array($value)) {
                // This is not a param that needs replacing, retain it,
                $newParameters[$bindingToken] = $value;
            } else {
                // Then mix the indicies of the array into the name to
                // create a bunch of unique keys for our individual elements
                $newKeys = array_map(function ($v) use ($name) {
                    return ":0{$name}__{$v}";
                }, array_keys($value));
                // Add a new 'to-be-replaced' mapping for the query substituting
                // our new list of comma imploded names for the old name
                $replacementList[$searchKey] = implode(", ", $newKeys);
                // Finally combine the values with their new keys and add
                // them to the list of parameters
                $newParameters = array_merge($newParameters, array_combine($newKeys, $value));
            }
        }
        // Replace the tokens with any exploded tokens we've found
        $query = preg_replace(array_keys($replacementList), $replacementList, $query);
        return [$query, $newParameters, $extraneousParameters];
    }

    /**
     * Safely execute a rollBack, potentially chaining an existing exception
     *
     * @param \Exception $e The exception to be chained
     *
     * @return bool(true) Whether the rollBack was successful, according to PDO::rollBack
     *         which should always be true @see http://php.net/manual/en/pdo.rollback.php
     */
    public function rollBack(\Throwable $e = null)
    {
        $rolledBack = false;
        if ($this->inTransaction()) {
            $rolledBack = $this->handle->rollBack();
            if ($e !== null && $e instanceof \Throwable) {
                // Yay the rollBack worked, but we still have a pending exception to throw
                throw $e;
            }
            return $rolledBack;
        }
        if ($this->wasInTransaction === false) {
            throw new \PDOException("There was no active transaction", 0, $e);
        } elseif ($e !== null) {
            throw $e;
        }
        return $rolledBack;
    }


    /**
     * Commit, but clean up our _wasInTransaction flay
     *
     * @return bool Whether the commit was successful
     */
    public function commit()
    {
        $result = $this->handle->commit();
        $this->wasInTransaction = false;
        return $result;
    }

    /**
     * Takes a potentially ':' prefixed name and returns the two alternatives
     *
     * @param string|int $name The name in question
     *
     * @return string[] The replacement token followed by the variable name
     */
    private static function varNameAndToken($name): array
    {
        switch (gettype($name)) {
            case 'string':
                if ($name[0] === ':') {
                    return [$name, \mb_substr($name, 1)];
                }
                break;
            case 'integer':
                //fallthrough to default
            default:
                break;
        }
        return [":{$name}", $name];
    }

    /**
     * Generate an insert clause for a given data array
     * Keys must be natural (eg. ints with no gaps)
     *
     * @param mixed[][] $data The data to generate the clause for
     * @return string The insert clause corresponding to that data
     *
     * @throws Exception if keys are not ints or not natural
     */
    public static function insertClauseForData($data)
    {
        if (!is_array($data)) {
            throw new Exception("insertClauseForData expects data to be an array");
        }
        $keys = array_keys($data);
        //first index, array of values in the form (:{index})
        $initial = [$keys[0] && is_int($keys[0]) ? $keys[0] : 0, []];
        $reduced = array_reduce($keys, function ($carry, $value) {
            if (!is_int($value)) {
                throw new Exception("Key $value is not an int!");
            }
            if ($value != $carry[0]) {
                throw new Exception("Expected key to be $carry[0], got $value. Array keys must be in natural order");
            }
            $carry[0]++;
            $carry[1][] = "(:{$value})";
            return $carry;
        }, $initial);
        return implode(',', $reduced[1]);
    }

    /**
     * Generate a clause for a given data array, suitable for following the WHERE keyword.  E.g.,
     * whereClauseForData([["1", "Aetna"], ["2", "BCBS"]], ["id", "companyname"]) -> 'ROW(id, companyname)
     * IN (ROW(:0), ROW(:1))'
     *
     * @param array[][] $data The data to generate the clause for
     * @param string[] $fields An array of field names
     * @return string The where clause corresponding to that data
     */
    public static function whereClauseForData($data, $fields)
    {
        // Double-quote field names (possibly including table names)
        $numFields = sizeof($fields);
        for ($i = 0; $i < $numFields; $i++) {
            $fields[$i] = str_replace('.', '"."', "\"{$fields[$i]}\"");
        }
        $fields = implode(", ", $fields);

        if (!sizeof($data)) {
            throw new \Exception("No data was seen");
        }

        $counter = 0;
        $clauses = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                //in case $row is not an array, convert it into array and remove empty spaces.
                $row = array_filter([$row]);
            }

            if (sizeof($row) != $numFields) {
                throw new \Exception("Saw incorrect number of fields");
            }
            $clauses[] = '    ROW(:' . $counter++ . ')';
        }
        $clauses = implode(",\n", $clauses);

        return "ROW($fields) IN (\n$clauses\n)";
    }

    public static function whereClause(array $atoms, string $baseIndentation = ''): string
    {
        $atoms = array_filter($atoms);
        if (empty($atoms)) {
            return '';
        } else {
            return $baseIndentation . \strtr(
                "WHERE\n(\n    " . implode("\n)\nAND\n(\n    ", $atoms) . "\n)",
                ["\n" => "\n{$baseIndentation}"]
            );
        }
    }

    public static function havingClause(array $atoms, string $baseIndentation = ''): string
    {
        $atoms = array_filter($atoms);
        if (empty($atoms)) {
            return '';
        } else {
            return $baseIndentation . \strtr(
                "HAVING\n(\n    " . implode("\n)\nAND\n(\n    ", $atoms) . "\n)",
                ["\n" => "\n{$baseIndentation}"]
            );
        }
    }

    /**
     * This function takes a list of named values and returns the SET query fragment to set those values
     *
     * This function doesn't currently output a list that can be table qualified (i.e. 'foo.bar = :bar')
     *
     * @param mixed[] A list of scalar values titled by the fields they correspond to
     * @return [string, mixed[]] A query fragment and an array of values configured to fix that query fragment
     */
    public static function setClause(array $atoms): array
    {
        if (!count($atoms)) {
            throw new \Exception("The atom list must not be empty.");
        }
        $values = [];
        $setStatements = [];
        foreach ($atoms as $name => $value) {
            $values[$name] = $value;
            $setStatements[] = "{$name} = :{$name}";
        }
        return ['SET ' . implode(', ', $setStatements), $values];
    }

    /**
     * Insert data into the DB in batches. You must be inside a transaction to use this function.
     *
     * @param string $query A query of the form "INSERT INTO table (col1, col2) VALUES "
     * @param array $data An array of the form [[col1data, col2data], [col1data, col2data], ...]
     *
     */
    public function runInsertInBatches(string $queryBeforeValues, array $data, string $queryAfterValues = ''): void
    {
        if (!count($data)) {
            // Nothing to do, we're done.
            return;
        }

        $numParams = count($data[0]);

        $inTransaction = $this->inTransaction();
        if (!$inTransaction) {
            $this->beginTransaction();
        }
        try {
            $batchSize = max(1, floor(self::getBatchSize() / $numParams));
            for ($i = 0; ([] !== ($batch = array_slice($data, $batchSize * $i, $batchSize))); $i++) {
                $this->run($queryBeforeValues . ' ' . self::insertClauseForData($batch) .
                                ' ' . $queryAfterValues, $batch);
            }

            if (!$inTransaction) {
                $this->commit();
            }
        } catch (\Exception $e) {
            if (!$inTransaction) {
                $this->rollBack($e);
            } else {
                throw $e;
            }
        }
    }

    public static function setBatchSize(int $batchSize): void
    {
        self::$batchSize = $batchSize;
    }

    protected static function getBatchSize(): int
    {
        return self::$batchSize;
    }

    public static function buildEscapedColumnNameString(array $fields): string
    {
        if (\mb_strpos(implode('', $fields), '"') !== false) {
            throw new \Exception('Database column names cannot include the " character.');
        }
        return '"' . implode('", "', $fields) . '"';
    }
}
