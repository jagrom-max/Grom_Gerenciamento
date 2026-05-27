<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$args = array_slice($argv, 1);

$extensions = [
    'php' => true,
    'blade.php' => true,
    'js' => true,
    'css' => true,
    'md' => true,
    'txt' => true,
    'json' => true,
    'yml' => true,
    'yaml' => true,
    'xml' => true,
    'ps1' => true,
    'cmd' => true,
    'sh' => true,
];

$skipParts = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . '_toolchain' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR,
];

function isCandidateFile(string $path, array $extensions, array $skipParts): bool
{
    foreach ($skipParts as $part) {
        if (str_contains($path, $part)) {
            return false;
        }
    }

    $name = basename($path);
    if (str_ends_with($name, '.blade.php')) {
        return true;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);

    return isset($extensions[$ext]);
}

function trackedFiles(string $root): array
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' ls-files';
    $output = shell_exec($cmd);
    if ($output === null) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $file): string => $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file),
        preg_split('/\R/', trim($output)) ?: []
    )));
}

$files = $args !== []
    ? array_map(static fn (string $file): string => realpath($file) ?: $file, $args)
    : trackedFiles($root);

$patterns = [
    '/\x{00C3}[\x{0080}-\x{00BF}\x{0192}\x{201A}\x{201E}\x{2026}\x{2020}\x{2021}\x{02C6}\x{2030}\x{0160}\x{2039}\x{0152}\x{017D}\x{2018}\x{2019}\x{201C}\x{201D}\x{2022}\x{2013}\x{2014}\x{02DC}\x{2122}\x{0161}\x{203A}\x{0153}\x{017E}\x{0178}]/u',
    '/\x{00C2}[\x{0080}-\x{00BF}]/u',
    '/\x{00E2}[\x{0080}-\x{00BF}\x{20AC}\x{2020}\x{2021}\x{20A0}-\x{20CF}]/u',
    '/\x{FFFD}/u',
];

$errors = [];

foreach ($files as $file) {
    $path = is_file($file) ? realpath($file) : false;
    if ($path === false || ! isCandidateFile($path, $extensions, $skipParts)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || ! mb_check_encoding($content, 'UTF-8')) {
        continue;
    }

    $lines = preg_split('/\R/', $content) ?: [];
    foreach ($lines as $index => $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                $errors[] = sprintf('%s:%d', $relative, $index + 1);
                break 2;
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Possivel texto com encoding corrompido encontrado:\n");
    fwrite(STDERR, implode("\n", $errors) . "\n");
    fwrite(STDERR, "Corrija o mojibake antes de commitar.\n");
    exit(1);
}

echo "OK: nenhum padrao de mojibake encontrado.\n";
