<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$basePath = __DIR__.'/../specs/001-project-foundation/contracts/openapi.yaml';
$phase4Path = __DIR__.'/../specs/005-acs-access-control/contracts/openapi.yaml';

$base = Yaml::parseFile($basePath);
$phase4 = Yaml::parseFile($phase4Path);

foreach ($phase4['paths'] ?? [] as $path => $operations) {
    $base['paths'][$path] = $operations;
}

foreach ($phase4['tags'] ?? [] as $tag) {
    $names = array_column($base['tags'] ?? [], 'name');
    if (! in_array($tag['name'], $names, true)) {
        $base['tags'][] = $tag;
    }
}

$skipSecuritySchemes = ['TenantSession'];
$skipParameters = ['IdempotencyKey'];
$skipSchemas = ['Problem'];

foreach ($phase4['components']['securitySchemes'] ?? [] as $name => $definition) {
    if (in_array($name, $skipSecuritySchemes, true)) {
        continue;
    }
    $base['components']['securitySchemes'][$name] = $definition;
}

foreach ($phase4['components']['parameters'] ?? [] as $name => $definition) {
    if (in_array($name, $skipParameters, true)) {
        continue;
    }
    $base['components']['parameters'][$name] = $definition;
}

if (! isset($base['components']['responses']['Problem'])) {
    $base['components']['responses']['Problem'] = $phase4['components']['responses']['Problem'];
}

foreach ($phase4['components']['schemas'] ?? [] as $name => $definition) {
    if (in_array($name, $skipSchemas, true)) {
        continue;
    }
    $base['components']['schemas'][$name] = $definition;
}

$description = (string) ($base['info']['description'] ?? '');
if (! str_contains($description, 'Phase 4')) {
    $base['info']['description'] = rtrim($description)."\n\nPhase 4 ACS and access-control operations are merged from the feature contract.";
}

file_put_contents(
    $basePath,
    Yaml::dump($base, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
);

copy($basePath, __DIR__.'/../docs/api/openapi.yaml');

echo "Merged Phase 4 OpenAPI operations into foundation contract.\n";
