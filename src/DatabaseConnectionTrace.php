<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

class DatabaseConnectionTrace
{
    public const CONNECTION_TYPE__READ_ONLY = "ReadOnly";
    public const CONNECTION_TYPE__READ_WRITE = "ReadWrite";

    protected float $startTime;
    protected string | array $logger;
    protected ?float $endTime = null;

    public function __construct(
        protected \PDO $pdo,
        // Callable
        callable $logger,
        protected string $handleName,
        ?float $microTime = null,
    )
    {
        $this->logger = $logger;
        $this->startTime = $microTime ? $microTime : microtime(true);
    }

    public function endTrace(): void
    {
        $this->endTime = microtime(true);
        $this->log();
    }

    public function log(): void
    {
        if ($this->logger === null) {
            return;
        }

        $logs = [];

        //log connection time
        $logs["timeElapsedMilliSeconds"] =  (int) (($this->endTime - $this->startTime) * 1000);

        //log connection type
        if ($this->handleName) {
            $logs["connectionType"] = $this->handleName;
        }

        //log route
        $logs["route"] = $_SERVER["REQUEST_URI"] ?? $_SERVER["REDIRECT_SCRIPT_URL"] ?? "";

        ($this->logger)("DB Connection Trace", $logs, false);
    }
}
