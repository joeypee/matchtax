<?php
declare(strict_types=1);

require __DIR__ . '/../lib.php';

echo render('home.php', [
  'title' => 'Home',
]);
