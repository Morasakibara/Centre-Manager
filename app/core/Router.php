<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array{0: string, 1: class-string, 2: string, 3: bool, 4: bool}> */
    private array $routes;

    public function __construct()
    {
        /** @var array<string, array{0: string, 1: class-string, 2: string, 3: bool, 4: bool}> $routes */
        $routes = require dirname(__DIR__, 2) . '/config/routes.php';
        $this->routes = $routes;
    }

    public function dispatch(): void
    {
        $route = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : 'dashboard';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!isset($this->routes[$route])) {
            $this->notFound();
            return;
        }

        [$allowedMethod, $controllerClass, $action, $authRequired, $adminRequired] = $this->routes[$route];

        if ($method !== $allowedMethod) {
            http_response_code(405);
            header('Allow: ' . $allowedMethod);
            echo 'Methode non autorisee.';
            return;
        }

        if ($allowedMethod === 'POST') {
            Csrf::check();
        }

        if ($authRequired && !Auth::check()) {
            flash('warning', 'Veuillez vous connecter pour continuer.');
            redirect('login');
        }

        if ($adminRequired && !Auth::isAdmin()) {
            http_response_code(403);
            (new \App\Controllers\ErreurController())->interdit();
            return;
        }

        if (!$authRequired && Auth::check() && $route === 'login') {
            redirect('dashboard');
        }

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException('Controleur introuvable : ' . $controllerClass);
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $action)) {
            throw new \RuntimeException('Action introuvable : ' . $controllerClass . '::' . $action);
        }

        $controller->{$action}();
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Page introuvable</title>'
            . '<link rel="stylesheet" href="' . e(asset('vendor/bootstrap/bootstrap.min.css')) . '"></head>'
            . '<body class="bg-light"><div class="container py-5 text-center">'
            . '<h1 class="display-6">404</h1><p class="text-muted">Cette page n\'existe pas.</p>'
            . '<a class="btn btn-primary" href="' . e(url('dashboard')) . '">Retour au tableau de bord</a>'
            . '</div></body></html>';
    }
}
