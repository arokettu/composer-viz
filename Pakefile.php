<?php

pake_task('build');

function run_build()
{
    pake_remove_dir('build');
    pake_mkdirs('build/temp');

    pake_mirror(pakeFinder::type('any'), 'bin', 'build/temp/bin');
    pake_mirror(pakeFinder::type('any'), 'src', 'build/temp/src');

    pake_copy('composer.json', 'build/temp/composer.json');
    pake_copy('composer.lock', 'build/temp/composer.lock');

    pake_sh('cd build/temp && composer install --optimize-autoloader --no-dev --ignore-platform-reqs');

    $version = trim(pake_sh('git describe --tags HEAD'));

    $bootstrap = file_get_contents('build/temp/src/bootstrap.php');
    $bootstrap = str_replace('%VERSION%', $version, $bootstrap);
    file_put_contents('build/temp/src/bootstrap.php', $bootstrap);

    $compiler = new \Secondtruth\Compiler\Compiler('build/temp');

    $compiler->addIndexFile('bin/composer-viz');
    $compiler->addDirectory('src');
    $compiler->addDirectory('vendor', ['!*.php', '*/tests/*', '*/Test/*', '*/Tests/*']);

    $compiler->compile('build/composer-viz.phar');
}
