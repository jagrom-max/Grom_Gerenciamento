<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GROM Web PHP</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fa;
            color: #2c3e50;
            font-family: "Segoe UI", Tahoma, sans-serif;
            padding: 24px;
        }
        .card {
            width: min(760px, 100%);
            background: #fff;
            border: 1px solid #d8dee7;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 16px 36px rgba(44, 62, 80, .10);
        }
        h1 {
            margin: 0 0 12px;
            font-size: 30px;
        }
        p {
            margin: 0 0 12px;
            line-height: 1.6;
        }
        code {
            background: #eef3f8;
            border-radius: 8px;
            padding: 2px 6px;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>GROM Web PHP</h1>
        <p>Esta e a base inicial da reconstrucao web do GROM.</p>
        <p>O scaffold funcional completo do Laravel ainda depende da instalacao de <code>php</code> e <code>composer</code> nesta estacao.</p>
        <p>Enquanto isso, a arquitetura, o schema, os diagramas, a infraestrutura e os mockups ja estao versionados em <code>/grom_web_php</code>.</p>
    </main>
</body>
</html>

