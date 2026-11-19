<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;

if (!function_exists('config')) {
    /**
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = Config::all();

        if ($key === null) {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Construit une URL interne a partir d'un nom de route.
     *
     * @param array<string, scalar|null> $params
     */
    function url(string $route = 'dashboard', array $params = []): string
    {
        $query = array_merge(['r' => $route], $params);

        return base_url() . '/index.php?' . http_build_query($query);
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        $configured = rtrim((string) config('url', ''), '/');

        // En mode web, l'URL de base depend du point d'entree reellement servi :
        // docroot = public/ (http://host/) ou racine du projet (http://host/public/).
        // La deduire de la requete evite des assets en 404 quand APP_URL ne
        // correspond pas au lancement du serveur.
        if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME'], $_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $base = $script === '/' || $script === '.' ? '' : rtrim($script, '/');

            return $scheme . '://' . $host . $base;
        }

        if ($configured !== '') {
            return $configured;
        }

        return 'http://localhost';
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function redirect(string $route = 'dashboard', array $params = []): never
    {
        header('Location: ' . url($route, $params));
        exit;
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('flashes')) {
    /** @return array<int, array{type: string, message: string}> */
    function flashes(): array
    {
        return Session::pullFlashes();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('argent')) {
    /**
     * Formate un montant en FCFA avec separateurs de milliers.
     */
    function argent(mixed $montant, bool $avecDevise = true): string
    {
        $valeur = number_format((float) $montant, 0, ',', ' ');
        $devise = (string) config('devise', 'XAF');

        return $avecDevise ? $valeur . ' ' . $devise : $valeur;
    }
}

if (!function_exists('date_fr')) {
    function date_fr(?string $date, bool $avecHeure = false): string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000')) {
            return '—';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return e($date);
        }

        return date($avecHeure ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
    }
}

if (!function_exists('datetime_local_value')) {
    function datetime_local_value(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return date('Y-m-d\TH:i');
        }

        $timestamp = strtotime($datetime);

        return $timestamp === false ? date('Y-m-d\TH:i') : date('Y-m-d\TH:i', $timestamp);
    }
}

if (!function_exists('statut_badge')) {
    function statut_badge(string $statut): string
    {
        $map = [
            'solde'      => ['success', 'Soldé'],
            'en_retard'  => ['danger', 'En retard'],
            'partiel'    => ['warning', 'Partiellement réglé'],
            'a_jour'     => ['info', 'À jour'],
            'a_venir'    => ['secondary', 'À venir'],
            'non_solde'  => ['warning', 'Non soldé'],
            'active'     => ['success', 'Active'],
            'terminee'   => ['secondary', 'Terminée'],
            'annulee'    => ['dark', 'Annulée'],
            'actif'      => ['success', 'Actif'],
            'suspendu'   => ['warning', 'Suspendu'],
            'termine'    => ['secondary', 'Terminé'],
            'archive'    => ['dark', 'Archivé'],
            'inactif'    => ['danger', 'Inactif'],
            'ouverte'    => ['success', 'Ouverte'],
            'cloturee'   => ['secondary', 'Clôturée'],
            'valide'     => ['success', 'Validé'],
            'annule'     => ['danger', 'Annulé'],
            'administrateur' => ['primary', 'Administrateur'],
            'secretaire' => ['info', 'Secrétaire / Comptable'],
            'especes'    => ['secondary', 'Espèces'],
            'orange_money' => ['warning', 'Orange Money'],
            'mtn_mobile_money' => ['warning', 'MTN Mobile Money'],
            'virement'   => ['info', 'Virement'],
            'autre'      => ['secondary', 'Autre'],
            'libre'      => ['info', 'Paiement libre'],
            'tranches'   => ['primary', 'Tranches'],
        ];

        [$classe, $label] = $map[$statut] ?? ['secondary', ucfirst(str_replace('_', ' ', $statut))];

        return '<span class="badge text-bg-' . e($classe) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('mode_paiement_label')) {
    function mode_paiement_label(string $mode): string
    {
        return match ($mode) {
            'especes' => 'Espèces',
            'orange_money' => 'Orange Money',
            'mtn_mobile_money' => 'MTN Mobile Money',
            'virement' => 'Virement',
            default => 'Autre',
        };
    }
}

if (!function_exists('initiales')) {
    function initiales(string $nom, string $prenom): string
    {
        return mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
    }
}

if (!function_exists('taille_lisible')) {
    function taille_lisible(int $octets): string
    {
        if ($octets < 1024) {
            return $octets . ' o';
        }
        if ($octets < 1048576) {
            return number_format($octets / 1024, 1, ',', ' ') . ' Ko';
        }
        return number_format($octets / 1048576, 2, ',', ' ') . ' Mo';
    }
}
