<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

/**
 * Sauvegarde de la base via mysqldump. La restauration est documentee
 * dans docs/SAUVEGARDE.md et reste une operation manuelle controlee.
 */
final class BackupService
{
    public static function dossier(): string
    {
        $dossier = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        return $dossier;
    }

    /**
     * @return array{success: bool, message: string, fichier?: string}
     */
    public static function creer(): array
    {
        $mysqldump = self::trouverMysqldump();
        if ($mysqldump === null) {
            return [
                'success' => false,
                'message' => 'mysqldump est introuvable. Renseignez MYSQLDUMP_PATH dans le fichier .env, ou utilisez phpMyAdmin / la procedure documentee.',
            ];
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $fichier = self::dossier() . '/centre-manager-' . date('Ymd-His') . '.sql';
        $erreur = self::dossier() . '/.derniere-erreur.log';

        $commande = sprintf(
            '%s --host=%s --port=%s --user=%s %s --single-transaction --routines --default-character-set=utf8mb4 %s > %s 2> %s',
            escapeshellarg($mysqldump),
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            $config['password'] !== '' ? '--password=' . escapeshellarg((string) $config['password']) : '',
            escapeshellarg((string) $config['database']),
            escapeshellarg($fichier),
            escapeshellarg($erreur)
        );

        exec($commande, $sortie, $code);

        if ($code !== 0 || !is_file($fichier) || filesize($fichier) < 1024) {
            $message = is_file($erreur) ? trim((string) file_get_contents($erreur)) : 'Erreur inconnue.';
            @unlink($fichier);
            Database::log('Echec sauvegarde : ' . $message);
            return ['success' => false, 'message' => 'La sauvegarde a echoue : ' . mb_substr($message, 0, 300)];
        }

        @unlink($erreur);
        AuditService::log('sauvegarde_base', 'systeme', null, ['fichier' => basename($fichier)]);
        self::nettoyer();

        return [
            'success' => true,
            'message' => 'Sauvegarde créée : ' . basename($fichier),
            'fichier' => $fichier,
        ];
    }

    /** @return array<int, array{nom: string, taille: int, date: int}> */
    public static function liste(): array
    {
        $resultat = [];
        foreach (glob(self::dossier() . '/*.sql') ?: [] as $fichier) {
            $resultat[] = [
                'nom' => basename($fichier),
                'taille' => (int) filesize($fichier),
                'date' => (int) filemtime($fichier),
            ];
        }

        usort($resultat, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $resultat;
    }

    private static function nettoyer(): void
    {
        $retention = (int) (Config::all()['backup']['retention'] ?? 14);
        if ($retention <= 0) {
            return;
        }

        $limite = time() - ($retention * 86400);
        foreach (glob(self::dossier() . '/*.sql') ?: [] as $fichier) {
            if (filemtime($fichier) !== false && filemtime($fichier) < $limite) {
                @unlink($fichier);
            }
        }
    }

    private static function trouverMysqldump(): ?string
    {
        $configure = (string) (Config::all()['backup']['mysqldump'] ?? '');
        if ($configure !== '' && is_file($configure)) {
            return $configure;
        }

        $candidats = [];
        if (PHP_OS_FAMILY === 'Windows') {
            $candidats[] = 'C:/MAMP/bin/mysql/bin/mysqldump.exe';
            $candidats[] = 'C:/xampp/mysql/bin/mysqldump.exe';
            $candidats[] = 'C:/laragon/bin/mysql/mysql-8.0/bin/mysqldump.exe';
            foreach (glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe') ?: [] as $trouve) {
                $candidats[] = $trouve;
            }
        } else {
            foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump'] as $candidat) {
                $candidats[] = $candidat;
            }
        }

        foreach ($candidats as $candidat) {
            if (is_file($candidat)) {
                return $candidat;
            }
        }

        $which = @shell_exec('command -v mysqldump 2>/dev/null');
        if (is_string($which) && trim($which) !== '') {
            return trim($which);
        }

        return null;
    }

    /**
     * Restauration controlee (utilisee uniquement en CLI par un administrateur).
     */
    public static function restaurer(string $fichier): void
    {
        if (!is_file($fichier)) {
            throw new RuntimeException('Fichier de sauvegarde introuvable.');
        }

        $mysql = str_replace('mysqldump', 'mysql', (string) self::trouverMysqldump());
        if (!is_file($mysql)) {
            throw new RuntimeException('Le client mysql est introuvable.');
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $commande = sprintf(
            '%s --host=%s --port=%s --user=%s %s --default-character-set=utf8mb4 %s < %s',
            escapeshellarg($mysql),
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            $config['password'] !== '' ? '--password=' . escapeshellarg((string) $config['password']) : '',
            escapeshellarg((string) $config['database']),
            escapeshellarg($fichier)
        );

        exec($commande, $sortie, $code);
        if ($code !== 0) {
            throw new RuntimeException('La restauration a echoue (code ' . $code . ').');
        }

        Database::log('Restauration effectuee depuis ' . basename($fichier));
    }
}
