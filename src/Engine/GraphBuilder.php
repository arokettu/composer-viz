<?php

namespace SandFox\ComposerViz\Engine;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

class GraphBuilder
{
    /** @var Composer */
    private $composer;
    /** @var Graph */
    private $graph;
    /** @var Vertex[] */
    private $vertices = [];

    private $noDev;

    private $noExt;
    private $noPHP;

    private $noVertexVersions;
    private $noEdgeVersions;

    public function __construct(Composer $composer, $noDev, $noExt, $noPHP, $noVertexVersions, $noEdgeVersions)
    {
        $this->noDev = $noDev;
        $this->noExt = $noExt;
        $this->noPHP = $noPHP;
        $this->noVertexVersions = $noVertexVersions;
        $this->noEdgeVersions = $noEdgeVersions;

        $this->composer = $composer;

        $this->graph = new Graph();
    }

    /**
     * @return Graph
     */
    public function build()
    {
        $dataComposerJson = $this->composer->getPackage();
        $dataComposerLock = $this->composer->getLocker()->getLockData();

        $this->processPackageData($dataComposerJson, !$this->noDev, false);
        $this->processLockFile($dataComposerLock, !$this->noDev);

        return $this->graph;
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
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

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
}
