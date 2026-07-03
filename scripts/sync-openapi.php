<?php

declare(strict_types=1);

$source = __DIR__.'/../specs/001-project-foundation/contracts/openapi.yaml';
$destination = __DIR__.'/../docs/api/openapi.yaml';
$baseline = __DIR__.'/../docs/api/openapi-baseline.yaml';

if (! file_exists($source)) {
    fwrite(STDERR, "Source OpenAPI document not found.\n");
    exit(1);
}

if (in_array('--check', $argv, true)) {
    if (! file_exists($destination) || hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
        fwrite(STDERR, "OpenAPI copy is out of sync.\n");
        exit(1);
    }

    exit(0);
}

if (! is_dir(dirname($destination))) {
    mkdir(dirname($destination), 0777, true);
}

copy($source, $destination);

if (in_array('--freeze', $argv, true)) {
    copy($source, $baseline);
}
