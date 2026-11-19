<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ErreurController extends Controller
{
    public function interdit(): void
    {
        $this->render('errors/403', ['titre' => 'Acces refuse']);
    }
}
