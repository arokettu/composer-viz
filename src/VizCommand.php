<?php

namespace SandFoxMe\ComposerViz;

use Composer\Command\BaseCommand;
use Composer\Package\PackageInterface;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VizCommand extends BaseCommand
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
        $this->setName('viz');
        $this->setDescription('Generate a GraphViz representation of the dependency graph');

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
        $noDev  = $input->getOption('no-dev');

        $noPlatform  = $input->getOption('no-platform');
        $this->noExt = $noPlatform || $input->getOption('no-ext');
        $this->noPHP = $noPlatform || $input->getOption('no-php');

        $format     = $input->getOption('format');
        $outFile    = $input->getOption('output');

        $noVersions = $input->getOption('no-versions');
        $this->noVertexVersions = $noVersions || $input->getOption('no-pkg-versions');
        $this->noEdgeVersions   = $noVersions || $input->getOption('no-dep-versions');

        $dataComposerJson = $this->getComposer()->getPackage();
        $dataComposerLock = $this->getComposer()->getLocker()->getLockData();

        $this->graph = new Graph();

        $this->processPackageData($dataComposerJson, !$noDev, false);
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

    /**
     * @param PackageInterface $package
     * @param bool $includeDev  include development dependencies
     * @param bool $asDev       treat as development
     */
    private function processPackageData(PackageInterface $package, $includeDev, $asDev)
    {
        $rootPackage = $package->getName();

        $rootVertex = $this->getVertex($rootPackage);

        if (!$this->noVertexVersions) {
            $rootVertex->setAttribute('graphviz.label', "{$rootPackage}: {$package->getPrettyVersion()}");
        }

        foreach ($package->getRequires() as $link) {
            $target = $link->getTarget();

            if ($this->ignorePackage($target)) {
                continue;
            }

            $constraint = $link->getPrettyConstraint();

            $packageVertex = $this->getVertex($target);
            $this->buildEdge($rootVertex, $packageVertex, $constraint, $asDev);
        }

        if ($includeDev) {
            foreach ($package->getDevRequires() as $link) {
                $target = $link->getTarget();

                if ($this->ignorePackage($target)) {
                    continue;
                }

                $constraint = $link->getPrettyConstraint();

                $packageVertex = $this->getVertex($target);
                $this->buildEdge($rootVertex, $packageVertex, $constraint, true);
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
        $localRepo = $this->getComposer()->getRepositoryManager()->getLocalRepository();

        foreach ($dataComposerLock['packages'] as $package) {
            $this->processPackageData($localRepo->findPackage($package['name'], '*'), false, false);
        }

        if ($dev) {
            foreach ($dataComposerLock['packages-dev'] as $package) {
                $this->processPackageData($localRepo->findPackage($package['name'], '*'), false, true);
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
