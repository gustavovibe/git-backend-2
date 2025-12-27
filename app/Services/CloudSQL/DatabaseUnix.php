<?php

namespace App\Services\CloudSQL;

use PDO;
use PDOException;
use RuntimeException;
use TypeError;

class DatabaseUnix
{
    public static function initUnixDatabaseConnection(): PDO
    {
        try {
            $username = env('DB_USERNAME');        // Laravel .env keys
            $password = env('DB_PASSWORD');
            $dbName   = env('DB_DATABASE');
            $instanceUnixSocket = env('DB_SOCKET'); // e.g. /cloudsql/project:region:instance

            $dsn = sprintf(
                'mysql:dbname=%s;unix_socket=%s',
                $dbName,
                $instanceUnixSocket
            );

            $conn = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (TypeError $e) {
            throw new RuntimeException(
                'Invalid or missing configuration. ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Could not connect to the Cloud SQL Database: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return $conn;
    }
}
