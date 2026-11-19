<?php

declare(strict_types=1);

use App\Core\Env;

$root = dirname(__DIR__);

Env::load($root . '/.env');

return [
    'name'      => Env::get('APP_NAME', 'Centre Manager'),
    'env'       => Env::get('APP_ENV', 'local'),
    'debug'     => Env::bool('APP_DEBUG', false),
    'url'       => rtrim((string) Env::get('APP_URL', 'http://localhost/centre-manager/public'), '/'),
    'timezone'  => Env::get('APP_TIMEZONE', 'Africa/Douala'),
    'devise'    => Env::get('APP_DEVISE', 'XAF'),
    'session'   => [
        'lifetime' => Env::int('SESSION_LIFETIME', 60),
        'secure'   => Env::bool('SESSION_SECURE', false),
    ],
    'login'     => [
        'max_tentatives' => Env::int('LOGIN_MAX_TENTATIVES', 5),
        'blocage_minutes' => Env::int('LOGIN_BLOCAGE_MINUTES', 15),
    ],
    'centre'    => [
        'nom'       => Env::get('CENTRE_NOM', 'PharaonAcademia'),
        'adresse'   => Env::get('CENTRE_ADRESSE', ''),
        'telephone' => Env::get('CENTRE_TELEPHONE', ''),
        'email'     => Env::get('CENTRE_EMAIL', ''),
    ],
    'matricule' => [
        'prefixe' => Env::get('MATRICULE_PREFIXE', 'CF'),
    ],
    'backup'    => [
        'retention' => Env::int('BACKUP_RETENTION', 14),
        'mysqldump' => Env::get('MYSQLDUMP_PATH', ''),
    ],
    'root'      => $root,
    'storage'   => $root . '/storage',
];
