<?php

declare(strict_types=1);

use App\Core\View;

$router->get('/', static function (): void {
    View::render('home/index', ['title' => 'Project foundation']);
});
