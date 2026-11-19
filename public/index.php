<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::all();

date_default_timezone_set((string) $config['timezone']);
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'French');

// Erreurs : journalisees, jamais affichees en production.
$debug = (bool) $config['debug'];
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$logDir = $config['storage'] . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php-error.log');

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    Database::log(sprintf(
        '%s: %s dans %s:%d%s%s',
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL,
        $exception->getTraceAsString()
    ));

    http_response_code(500);
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title>'
        . '<link rel="stylesheet" href="' . htmlspecialchars(asset('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') . '"></head>'
        . '<body class="bg-light"><div class="container py-5 text-center">'
        . '<h1 class="h3">Une erreur est survenue</h1>'
        . '<p class="text-muted">L\'incident a ete enregistre. Contactez l\'administrateur si le probleme persiste.</p>'
        . '</div></body></html>';
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;");

Session::start();

if (
    isset($_GET['r'])
    && $_GET['r'] === 'logout'
    && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
) {
    // Compatibilite : la deconnexion reste une action POST protegee par CSRF.
    http_response_code(405);
    header('Allow: POST');
    echo 'Utilisez le bouton de deconnexion.';
    return;
}

(new Router())->dispatch();
