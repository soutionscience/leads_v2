<?php

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
        date_default_timezone_set($config['timezone']);
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config()['database'];
    if ($config['driver'] === 'mysql') {
        $mysql = $config['mysql'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $mysql['host'],
            $mysql['database'],
            $mysql['charset']
        );
        $pdo = new PDO($dsn, $mysql['username'], $mysql['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(
                'PDO SQLite is not enabled in this PHP install. Use MySQL on cPanel, or enable pdo_sqlite for local SQLite mode.'
            );
        }
        $pdo = new PDO('sqlite:' . $config['sqlite_path'], null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    migrate($pdo, $config['driver']);
    return $pdo;
}

function migrate(PDO $pdo, string $driver): void
{
    $autoIncrement = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $textDefault = 'CURRENT_TIMESTAMP';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id {$autoIncrement},
            phone_normalized VARCHAR(32) NOT NULL UNIQUE,
            phone_display VARCHAR(64) NOT NULL,
            name VARCHAR(160) DEFAULT '',
            type VARCHAR(32) NOT NULL DEFAULT 'customer',
            ignored INTEGER NOT NULL DEFAULT 0,
            notes TEXT,
            external_source VARCHAR(64),
            external_contact_id VARCHAR(128),
            last_synced_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT {$textDefault},
            updated_at DATETIME NOT NULL DEFAULT {$textDefault}
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id {$autoIncrement},
            contact_id INT,
            phone_normalized VARCHAR(32) NOT NULL,
            phone_display VARCHAR(64) NOT NULL,
            customer_name VARCHAR(160) DEFAULT '',
            source VARCHAR(32) NOT NULL DEFAULT 'phone',
            product_name VARCHAR(180) DEFAULT '',
            product_price DECIMAL(12,2),
            quoted_amount DECIMAL(12,2),
            delivery_area VARCHAR(180) DEFAULT '',
            delivery_fee DECIMAL(12,2),
            resolution VARCHAR(32) NOT NULL DEFAULT 'quoting',
            notes TEXT,
            call_started_at DATETIME,
            call_ended_at DATETIME,
            call_duration_seconds INT NOT NULL DEFAULT 0,
            followup_1_done INTEGER NOT NULL DEFAULT 0,
            followup_2_done INTEGER NOT NULL DEFAULT 0,
            followup_3_done INTEGER NOT NULL DEFAULT 0,
            next_followup_at DATE,
            created_at DATETIME NOT NULL DEFAULT {$textDefault},
            updated_at DATETIME NOT NULL DEFAULT {$textDefault},
            FOREIGN KEY(contact_id) REFERENCES contacts(id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS call_events (
            id {$autoIncrement},
            lead_id INT,
            contact_id INT,
            phone_normalized VARCHAR(32) NOT NULL,
            phone_display VARCHAR(64) NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            ignored INTEGER NOT NULL DEFAULT 0,
            ignore_reason VARCHAR(64),
            occurred_at DATETIME NOT NULL DEFAULT {$textDefault},
            payload TEXT,
            FOREIGN KEY(lead_id) REFERENCES leads(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id)
        )
    ");
}
