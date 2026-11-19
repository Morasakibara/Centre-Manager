<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $config = config();
        $user = Auth::user();
        $flashes = flashes();
        $errors = Session::pullErrors();

        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('Vue introuvable : ' . $view);
        }

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        Session::clearOld();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = dirname(__DIR__, 2) . '/resources/views/' . $layout . '.php';
        require $layoutFile;
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            flash('warning', 'Veuillez vous connecter pour continuer.');
            redirect('login');
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin()) {
            http_response_code(403);
            $this->render('errors/403', ['titre' => 'Acces refuse']);
            exit;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return $_POST;
    }

    protected function validateCsrf(): void
    {
        Csrf::check();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    protected function backWithErrors(string $route, array $data, array $errors): never
    {
        Session::flashInput($data);
        Session::setErrors($errors);
        flash('danger', 'Certaines informations sont invalides. Merci de corriger les champs signales.');
        redirect($route);
    }
}
