<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

final class AuditService
{
    /**
     * @param array<string, mixed> $details
     */
    public static function log(
        string $action,
        string $entite,
        ?int $entiteId,
        array $details = [],
        ?int $utilisateurId = null
    ): void {
        $userId = $utilisateurId ?? Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $statement = Database::pdo()->prepare(
            'INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details, adresse_ip, date_action)
             VALUES (:utilisateur, :action, :entite, :entite_id, :details, :ip, NOW())'
        );

        $statement->execute([
            'utilisateur' => $userId,
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
        ]);
    }

    /**
     * @param array<string, mixed> $filtres
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function paginate(array $filtres = [], int $page = 1, int $parPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filtres['action'])) {
            $where[] = 'j.action = :action';
            $params['action'] = $filtres['action'];
        }
        if (!empty($filtres['entite'])) {
            $where[] = 'j.entite = :entite';
            $params['entite'] = $filtres['entite'];
        }
        if (!empty($filtres['utilisateur'])) {
            $where[] = 'j.utilisateur_id = :utilisateur';
            $params['utilisateur'] = (int) $filtres['utilisateur'];
        }
        if (!empty($filtres['debut'])) {
            $where[] = 'DATE(j.date_action) >= :debut';
            $params['debut'] = $filtres['debut'];
        }
        if (!empty($filtres['fin'])) {
            $where[] = 'DATE(j.date_action) <= :fin';
            $params['fin'] = $filtres['fin'];
        }
        if (!empty($filtres['q'])) {
            $where[] = '(j.action LIKE :q OR j.detail LIKE :q OR j.entite LIKE :q)';
            $params['q'] = '%' . $filtres['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM journal_activites j WHERE ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $parPage;

        $statement = $pdo->prepare(
            'SELECT j.*, u.nom_complet AS utilisateur_nom, u.role
             FROM journal_activites j
             LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
             WHERE ' . $whereSql . '
             ORDER BY j.date_action DESC, j.id DESC
             LIMIT :limite OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limite', $parPage, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return ['rows' => $statement->fetchAll(), 'total' => $total];
    }
}
