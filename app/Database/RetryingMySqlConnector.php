<?php

namespace App\Database;

use Illuminate\Database\Connectors\MySqlConnector;
use PDOException;

/**
 * MySQL connector that waits and tries again when Hostinger refuses a new connection.
 *
 * Hostinger allows 20 new MySQL connections a second for the whole account: web
 * requests, the scheduler and every tenant database together. Over that, connecting
 * fails with "SQLSTATE[HY000] [2002] Operation not permitted", and the request
 * failed with "Service temporarily unavailable". The limit is per second, so waiting
 * a few hundred milliseconds and connecting again almost always works. The waits are
 * random so requests refused in the same second do not all come back together.
 *
 * The counters cover the current request (PHP starts fresh for each one) and are
 * written to the request log by LogRequest.
 */
class RetryingMySqlConnector extends MySqlConnector
{
    /** Wait before each retry, in milliseconds [min, max]: at most about 3 seconds in all. */
    private const RETRY_WAITS_MS = [[150, 350], [300, 600], [500, 900], [700, 1200]];

    /**
     * New connections opened, persistent ones (DB_PERSISTENT: usually an open socket
     * reused, so not counted as new), retries made, and connections given up on.
     */
    public static int $opened = 0;
    public static int $persistent = 0;
    public static int $retries = 0;
    public static int $refused = 0;

    public function createConnection($dsn, array $config, array $options)
    {
        $attempt = 0;

        while (true) {
            try {
                $pdo = parent::createConnection($dsn, $config, $options);

                if (! empty($options[\PDO::ATTR_PERSISTENT])) {
                    self::$persistent++;
                } else {
                    self::$opened++;
                }

                return $pdo;
            } catch (PDOException $e) {
                if (! self::refusedByHost($e)) {
                    throw $e;
                }

                $wait = self::RETRY_WAITS_MS[$attempt++] ?? null;

                if ($wait === null) {
                    self::$refused++;

                    throw $e;
                }

                self::$retries++;
                usleep(random_int($wait[0], $wait[1]) * 1000);
            }
        }
    }

    /**
     * The host's connection limit, not a wrong password or a stopped server.
     */
    public static function refusedByHost(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Operation not permitted')
            || str_contains($message, 'Resource temporarily unavailable');
    }
}
