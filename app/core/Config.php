<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$data === null) {
            /** @var array<string, mixed> $config */
            $config = require dirname(__DIR__, 2) . '/config/app.php';

            // Les parametres persistes en base (saisis dans l'application)
            // prennent le pas sur les valeurs par defaut du fichier .env.
            try {
                $rows = \App\Core\Database::pdo()->query('SELECT cle, valeur FROM parametres')->fetchAll();
                foreach ($rows as $row) {
                    $cle = (string) ($row['cle'] ?? '');
                    $valeur = (string) ($row['valeur'] ?? '');
                    match ($cle) {
                        'centre_nom' => $config['centre']['nom'] = $valeur,
                        'centre_adresse' => $config['centre']['adresse'] = $valeur,
                        'centre_telephone' => $config['centre']['telephone'] = $valeur,
                        'centre_email' => $config['centre']['email'] = $valeur,
                        'matricule_prefixe' => $config['matricule']['prefixe'] = $valeur,
                        'devise' => $config['devise'] = $valeur,
                        default => null,
                    };
                }
            } catch (\Throwable $exception) {
                // Base non disponible : on conserve les valeurs du .env.
            }

            self::$data = $config;
        }

        return self::$data;
    }
}
