<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$basePath = __DIR__.'/../specs/001-project-foundation/contracts/openapi.yaml';
$phase2Path = __DIR__.'/../specs/003-wallet-passes-scanning/contracts/openapi.yaml';

$base = Yaml::parseFile($basePath);
$phase2 = Yaml::parseFile($phase2Path);

foreach ($phase2['paths'] ?? [] as $path => $operations) {
    $base['paths'][$path] = $operations;
}

foreach ($phase2['tags'] ?? [] as $tag) {
    $names = array_column($base['tags'] ?? [], 'name');
    if (! in_array($tag['name'], $names, true)) {
        $base['tags'][] = $tag;
    }
}

foreach (['schemas', 'parameters', 'securitySchemes'] as $section) {
    foreach ($phase2['components'][$section] ?? [] as $name => $definition) {
        $base['components'][$section][$name] = $definition;
    }
}

$description = (string) ($base['info']['description'] ?? '');
if (! str_contains($description, 'Phase 2')) {
    $base['info']['description'] = str_replace(
        'Phase 1',
        'Phase 1 and Phase 2',
        $description,
    );
}

file_put_contents(
    $basePath,
    Yaml::dump($base, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
);

copy($basePath, __DIR__.'/../docs/api/openapi.yaml');

echo "Merged Phase 2 OpenAPI operations into foundation contract.\n";
