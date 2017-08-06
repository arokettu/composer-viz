<?php

namespace SandFoxMe\ComposerViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VizCommand extends Command
{
    /**
     * @var Graph
     */
    private $graph;
    /**
     * @var Vertex[]
     */
    private $vertices = [];

    private $noExt;
    private $noPHP;

    private $noVertexVersions;
    private $noEdgeVersions;

    protected function configure()
    {
        $this->setName('composer-viz');
        $this->setDescription('Generate a GraphViz representation of the dependency graph');

        $this->addOption('path',        'd',    InputOption::VALUE_REQUIRED,    'Path to composer.json and composer.lock', getcwd());
        $this->addOption('output',      'o',    InputOption::VALUE_REQUIRED,    'Output file');
        $this->addOption('format',      'f',    InputOption::VALUE_REQUIRED,    'Output file format');

        $this->addOption('no-dev',      null,   InputOption::VALUE_NONE,        'Ignore development dependencies');
        $this->addOption('no-php',      null,   InputOption::VALUE_NONE,        'Ignore PHP dependencies');
        $this->addOption('no-ext',      null,   InputOption::VALUE_NONE,        'Ignore PHP extension dependencies');
        $this->addOption('no-platform', null,   InputOption::VALUE_NONE,        '--no-php and --no-ext');

        $this->addOption('no-pkg-versions', null,   InputOption::VALUE_NONE,    'Do not render version labels on vertices');
        $this->addOption('no-dep-versions', null,   InputOption::VALUE_NONE,    'Do not render version labels on arrows');
        $this->addOption('no-versions',     null,   InputOption::VALUE_NONE,    '--no-pkg-versions and --no-dep-versions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path   = $input->getOption('path');
        $noDev  = $input->getOption('no-dev');

        $noPlatform  = $input->getOption('no-platform');
        $this->noExt = $noPlatform || $input->getOption('no-ext');
        $this->noPHP = $noPlatform || $input->getOption('no-php');

        $format     = $input->getOption('format');
        $outFile    = $input->getOption('output');

        $noVersions = $input->getOption('no-versions');
        $this->noVertexVersions = $noVersions || $input->getOption('no-pkg-versions');
        $this->noEdgeVersions   = $noVersions || $input->getOption('no-dep-versions');

        $dataComposerJson = $this->loadJsonFromPath($path, 'composer.json');
        $dataComposerLock = $this->loadJsonFromPath($path, 'composer.lock');

        if (empty($dataComposerJson['name'])) {
            $dataComposerJson['name'] = 'Project';
        }

        $this->graph = new Graph();

        $this->processPackageData($dataComposerJson, !$noDev);
        $this->processLockFile($dataComposerLock, !$noDev);

        $viz = new GraphViz();

        $viz->setFormat($this->detectFormat($outFile, $format));

        if ($outFile) {
            $file = $viz->createImageFile($this->graph);
            rename($file, $outFile);
        } else {
            $viz->display($this->graph);
        }
    }

    private function loadJsonFromPath($path, $file)
    {
        $jsonFile = realpath(implode('/', [$path, $file]));

        if (!is_file($jsonFile) || !is_readable($jsonFile)) {
            throw new RuntimeException("No {$file} available in {$path}");
        }

        $jsonData = json_decode(file_get_contents($jsonFile), JSON_OBJECT_AS_ARRAY);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("{$file} is not a valid json file");
        }

        return $jsonData;
    }

    private function processPackageData($data, $includeDev)
    {
        $rootPackage = $data['name'];

        $rootVertex = $this->getVertex($rootPackage);

        if (!$this->noVertexVersions && !empty($data['version'])) {
            $rootVertex->setAttribute('graphviz.label', "{$rootPackage}: {$data['version']}");
        }

        if (!empty($data['require'])) {
            foreach ($data['require'] as $package => $version) {
                if ($this->ignorePackage($package)) {
                    continue;
                }

                $packageVertex = $this->getVertex($package);
                $this->buildEdge($rootVertex, $packageVertex, $version, false);
            }
        }

        if ($includeDev && !empty($data['require-dev'])) {
            foreach ($data['require-dev'] as $package => $version) {
                if ($this->ignorePackage($package)) {
                    continue;
                }

                $packageVertex = $this->getVertex($package);
                $this->buildEdge($rootVertex, $packageVertex, $version, true);
            }
        }
    }

    private function getVertex($name)
    {
        if (!isset($this->vertices[$name])) {
            $vertex = $this->graph->createVertex($name);
            $this->vertices[$name] = $vertex;
        }

        return $this->vertices[$name];
    }

    private function buildEdge(Vertex $from, Vertex $to, $version, $dev)
    {
        $edge = $from->createEdgeTo($to);

        if (!$this->noEdgeVersions) {
            $edge->setAttribute('graphviz.label', $version);
        }

        if ($dev) {
            $edge->setAttribute('graphviz.style', 'dashed');
        }
    }

    private function processLockFile($dataComposerLock, $dev)
    {
        foreach ($dataComposerLock['packages'] as $package) {
            $this->processPackageData($package, false);
        }

        if ($dev) {
            foreach ($dataComposerLock['packages-dev'] as $package) {
                $this->processPackageData($package, false);
            }
        }
    }

    private function ignorePackage($name)
    {
        // filter extensions (begins with ext-, no namespace slash)
        if ($this->noExt && strpos($name, 'ext-') === 0 && strpos($name, '/') === false) {
            return true;
        }

        // filter php platform (begins with php, no namespace slash)
        if ($this->noPHP && strpos($name, 'php') === 0 && strpos($name, '/') === false) {
            return true;
        }

        return false;
    }

    private function detectFormat($filename, $format)
    {
        if ($format) {
            return $format;
        }

        if ($filename && preg_match('/\.([^.]+)$/', $filename, $matches)) {
            return $matches[1];
        }

        return 'png';
    }
}
