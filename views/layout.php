<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($title ?? 'Cricket Subs') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --brand:#0a7; }
    body{margin:0;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f7f7f7;color:#222}
    header{background:var(--brand);color:#fff;padding:1rem 1.2rem;font-weight:600}
    main{max-width:56rem;margin:2rem auto;background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:1.2rem}
    a{color:var(--brand);text-decoration:none}
    .muted{color:#666}
  </style>
</head>
<body>
  <header>Cricket Subs</header>
  <main>
    <?= $content ?>
  </main>
</body>
</html>
