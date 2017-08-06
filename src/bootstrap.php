<?php

// init autoloader

$file = null;

foreach ([
         __DIR__ . '/../vendor/autoload.php',
         __DIR__ . '/../../../autoload.php',
    ] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

if (!class_exists('SandFoxMe\\ComposerViz\\VizCommand')) {
    echo 'Autoload initialization error. Please install dependencies with `composer install`' . PHP_EOL;
}

$app = new \Symfony\Component\Console\Application('composer-viz', 'alpha');
$app->add(new \SandFoxMe\ComposerViz\VizCommand);
$app->setDefaultCommand('composer-viz', true);
$app->run();
