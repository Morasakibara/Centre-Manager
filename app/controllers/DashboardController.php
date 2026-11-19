<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\TableauBordService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $this->render('dashboard/index', array_merge(
            ['titre' => 'Tableau de bord'],
            TableauBordService::donnees()
        ));
    }
}
