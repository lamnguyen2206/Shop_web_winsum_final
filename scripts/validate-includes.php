<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$broken = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    if (!preg_match_all("/(?:require_once|require|include_once|include)\s+(?:__DIR__\s*\.\s*'\/([^']+)'|__DIR__\s*\.\s*\"\/([^\"]+)\"|'([^']+\.php)'|\"([^\"]+\.php)\")/", $content, $matches, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($matches as $match) {
        $rel = $match[1] ?: $match[2] ?: $match[3] ?: $match[4];
        if ($rel === '' || str_starts_with($rel, 'http') || str_contains($rel, 'PEAR')) {
            continue;
        }
        if (!str_contains($rel, '.php')) {
            continue;
        }

        $baseDir = dirname($path);
        if (str_starts_with($rel, '/') || preg_match('/^[A-Za-z]:\\\\/', $rel)) {
            $target = $rel;
        } else {
            $target = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        }

        if (!is_file($target)) {
            $broken[] = [
                'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
                'include' => $rel,
                'expected' => str_replace($root . DIRECTORY_SEPARATOR, '', $target),
            ];
        }
    }
}

if ($broken === []) {
    echo "OK: All checked include paths exist.\n";
    exit(0);
}

echo "Broken include paths (" . count($broken) . "):\n";
foreach ($broken as $item) {
    echo "- {$item['file']}\n";
    echo "  missing: {$item['include']}\n";
}
exit(1);
