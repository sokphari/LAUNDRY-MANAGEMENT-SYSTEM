<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Services\DashboardService;

final class DashboardController
{
    public static function index(): void
    {
        $currentYear = (int) date('Y');
        $requestedYear = isset($_GET['year']) && is_string($_GET['year']) && ctype_digit($_GET['year'])
            ? (int) $_GET['year']
            : $currentYear;

        View::render('dashboard/index', DashboardService::sampleData($requestedYear) + [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'csrfToken' => Csrf::token(),
        ]);
    }
}
