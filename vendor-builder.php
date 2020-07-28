<?php

if (getcwd() !== __DIR__) {
    die("Please navigate to " . __DIR__);
}

exec('rm -rf vendor');

$lock = json_decode(file_get_contents('../../../composer.lock'), true);
$currentCompopserJson = json_decode(file_get_contents('deps.json'), true);

$newComposerJson = ['require' => []];

foreach ($currentCompopserJson['require'] as $package => $version) {
    if (strpos($package, 'shopware/') !== false ) {
        continue;
    }

    $newComposerJson['require'][$package] = $version;
}

foreach ($lock['packages'] as $package) {
    $newComposerJson['replace'][$package['name']] = $package['version'];
}

mkdir('vendor');

file_put_contents('vendor/composer.json', json_encode($newComposerJson, JSON_PRETTY_PRINT));

exec('composer install -d vendor');

file_put_contents('vendor/autoload.php', '<?php' . PHP_EOL . 'require __DIR__ . \'/vendor/autoload.php\';');
