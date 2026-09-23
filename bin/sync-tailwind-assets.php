<?php

declare(strict_types=1);

$manifestPath = __DIR__.'/../public/assets/manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($manifest)) {
    throw new RuntimeException('The asset manifest must contain an object.');
}

foreach (['styles/public.css' => 'var/tailwind/public.built.css', 'styles/authenticated.css' => 'var/tailwind/authenticated.built.css'] as $logical => $built) {
    $assetPath = $manifest[$logical] ?? null;
    if (!is_string($assetPath) || !is_file(__DIR__.'/../'.$built)) {
        throw new RuntimeException(sprintf('Unable to sync built asset "%s".', $logical));
    }

    copy(__DIR__.'/../'.$built, __DIR__.'/../public'.$assetPath);
}
