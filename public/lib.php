<?php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function render(string $view, array $vars = []): string {
    extract($vars, EXTR_SKIP);

    ob_start();
    require __DIR__ . "/views/$view";
    $content = ob_get_clean();

    ob_start();
    require __DIR__ . "/views/layout.php";
    return ob_get_clean();
}
