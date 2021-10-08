<?php

namespace SandFox\ComposerViz\Engine;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

class GraphBuilder
{
    const COLOR_DEFAULT     = '#ffffff';
    const COLOR_ROOT        = '#eeffee';
    const COLOR_DEV         = '#eeeeee';
    const COLOR_PLATFORM    = '#eeeeff';
    const COLOR_PROVIDED    = '#ffeeee';

    const NODE_ROOT = 1001;
    const NODE_DEP = 1002;
    const NODE_DEV = 1003;

    const PACKAGE_REGULAR = 2001;
    const PACKAGE_PHP = 2002;
    const PACKAGE_EXTENSION = 2003;
    const PACKAGE_COMPOSER = 2004;

    const EDGE_REGULAR = 'solid';
    const EDGE_DEV = 'dashed';
    const EDGE_PROVIDED = 'dotted';

    /** @var Composer */
    private $composer;
    /** @var Graph */
    private $graph = null;
    /** @var Vertex[] */
    private $vertices = [];
    /** @var Vertex[] */
    private $phpVertices = [];
    /** @var string[][] */
    private $provides = [];

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
    }

    /**
     * @return Graph
     */
    public function build()
    {
        if ($this->graph) {
            return $this->graph;
        }

        $this->graph = new Graph();

        $dataComposerJson = $this->composer->getPackage();
        $dataComposerLock = $this->composer->getLocker()->getLockData();

        $this->processPackageData($dataComposerJson, self::NODE_ROOT, !$this->noDev);
        $this->processLockFile($dataComposerLock, !$this->noDev);

        foreach ($this->phpVertices as $vertex) {
            $php = $this->getVertex('php', self::NODE_DEP);
            $this->buildEdge($vertex, $php, '', self::EDGE_PROVIDED);
        }

        foreach ($this->provides as list($package, $provided, $version)) {
            if (!isset($this->vertices[$provided])) {
                continue;
            }

            $packageVertex = $this->getVertex($package, self::NODE_DEP);
            $providedVertex = $this->getVertex($provided, self::NODE_DEP);
            $providedVertex->setAttribute('graphviz.fillcolor', self::COLOR_PROVIDED);
            $this->buildEdge($providedVertex, $packageVertex, $version, self::EDGE_PROVIDED);
        }

        return $this->graph;
    }

    /**
     * @param PackageInterface $package
     * @param bool $nodeType       node type
     * @param bool $includeDev  include development dependencies
     */
    private function processPackageData(PackageInterface $package, $nodeType, $includeDev)
    {
        $rootPackage = $package->getName();

        $rootVertex = $this->getVertex($rootPackage, $nodeType);

        if (!$this->noVertexVersions) {
            $rootVertex->setAttribute('graphviz.label', "{$rootPackage}: {$package->getPrettyVersion()}");
        }

        foreach ($package->getRequires() as $link) {
            $target = $link->getTarget();

            if ($this->ignorePackage($target)) {
                continue;
            }

            $constraint = $link->getPrettyConstraint();

            $packageVertex = $this->getVertex(
                $target,
                $nodeType === self::NODE_DEV ? self::NODE_DEV : self::NODE_DEP
            );
            $this->buildEdge(
                $rootVertex,
                $packageVertex,
                $constraint,
                $nodeType === self::NODE_DEV ? self::EDGE_DEV : self::EDGE_REGULAR
            );
        }

        foreach ($package->getProvides() as $link) {
            $this->provides[] = [$rootPackage, $link->getTarget(), $link->getPrettyConstraint()];
        }

        foreach ($package->getReplaces() as $link) {
            $this->provides[] = [$rootPackage, $link->getTarget(), $link->getPrettyConstraint()];
        }

        if ($includeDev) {
            foreach ($package->getDevRequires() as $link) {
                $target = $link->getTarget();

                if ($this->ignorePackage($target)) {
                    continue;
                }

                $constraint = $link->getPrettyConstraint();

                $packageVertex = $this->getVertex($target, self::NODE_DEV);
                $this->buildEdge($rootVertex, $packageVertex, $constraint, self::EDGE_DEV);
            }
        }
    }

    private function getVertex($name, $nodeType)
    {
        if (!isset($this->vertices[$name])) {
            $vertex = $this->graph->createVertex($name);
            $packageType = $this->packageType($name);

            if ($nodeType === self::NODE_ROOT) {
                $color = self::COLOR_ROOT;
            } else {
                switch ($packageType) {
                    case self::PACKAGE_EXTENSION:
                    case self::PACKAGE_COMPOSER:
                    case self::PACKAGE_PHP:
                        $color = self::COLOR_PLATFORM;
                        break;
                    case self::PACKAGE_REGULAR:
                        $color = $nodeType === self::NODE_DEV ? self::COLOR_DEV : self::COLOR_DEFAULT;
                        break;
                    default:
                        throw new \LogicException('Unknown package type');
                }
            }

            $vertex->setAttribute('graphviz.shape', 'box');
            $vertex->setAttribute('graphviz.style', 'rounded, filled');
            $vertex->setAttribute('graphviz.fillcolor', $color);

            $this->vertices[$name] = $vertex;

            // make php-64bit and php-ipv6 'provide' PHP
            if ($packageType === self::PACKAGE_PHP && $name !== 'php') {
                $this->phpVertices[$name] = $vertex;
            }
        }

        return $this->vertices[$name];
    }

    private function buildEdge(Vertex $from, Vertex $to, $version, $type)
    {
        $edge = $from->createEdgeTo($to);

        if (!$this->noEdgeVersions) {
            $edge->setAttribute('graphviz.label', $version);
        }

        $edge->setAttribute('graphviz.style', $type);
    }

    private function processLockFile($dataComposerLock, $dev)
    {
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($dataComposerLock['packages'] as $package) {
            $this->processPackageData($localRepo->findPackage($package['name'], '*'), self::NODE_DEP, false);
        }

        if ($dev) {
            foreach ($dataComposerLock['packages-dev'] as $package) {
                $this->processPackageData($localRepo->findPackage($package['name'], '*'), self::NODE_DEV, false);
            }
        }
    }

    private function ignorePackage($name)
    {
        $type = $this->packageType($name);

        // filter extensions (begins with ext-, no namespace slash)
        if ($this->noExt && $type === self::PACKAGE_EXTENSION) {
            return true;
        }

        // filter php platform (begins with php, no namespace slash)
        if ($this->noPHP && $type === self::PACKAGE_PHP) {
            return true;
        }

        return false;
    }

    private function packageType($name)
    {
        if (str_contains($name, '/') || $name === '__root__') {
            return self::PACKAGE_REGULAR;
        }

        if (str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-')) {
            return self::PACKAGE_EXTENSION;
        }

        if (str_starts_with($name, 'php')) {
            return self::PACKAGE_PHP;
        }

        if (str_starts_with($name, 'composer-')) {
            return self::PACKAGE_COMPOSER;
        }

        throw new \RuntimeException("Unable to determine package type of {$name}");
    }
}
