<?php

declare(strict_types=1);

namespace Arokettu\Composer\Viz\Engine;

use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Fhaculty\Graph\Edge\Directed as Edge;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * @internal
 */
final class GraphBuilder
{
    private const VERTEX_TYPE_DEFAULT   = 'vertex_default';
    private const VERTEX_TYPE_ROOT      = 'vertex_root';
    private const VERTEX_TYPE_DEV       = 'vertex_dev';
    private const VERTEX_TYPE_PLATFORM  = 'vertex_platform';
    private const VERTEX_TYPE_PROVIDED  = 'vertex_provided';

    private const VERTEX_COLORS = [
        self::VERTEX_TYPE_DEFAULT     => '#ffffff',
        self::VERTEX_TYPE_ROOT        => '#eeffee',
        self::VERTEX_TYPE_DEV         => '#eeeeee',
        self::VERTEX_TYPE_PLATFORM    => '#eeeeff',
        self::VERTEX_TYPE_PROVIDED    => '#ffeeee',
    ];

    private const EDGE_TYPE_DEFAULT     = 'edge_default';
    private const EDGE_TYPE_DEV         = 'edge_dev';
    private const EDGE_TYPE_PROVIDED    = 'edge_provided';

    private const EDGE_COLORS = [
        self::EDGE_TYPE_DEFAULT     => '#000000',
        self::EDGE_TYPE_DEV         => '#777777',
        self::EDGE_TYPE_PROVIDED    => '#cc7777',
    ];

    private const NODE_ROOT = 'root_node';
    private const NODE_DEP  = 'dep_node';
    private const NODE_DEV  = 'dev_node';

    private const NODE_BORDER_COLORS = [
        self::NODE_ROOT => '#000000',
        self::NODE_DEP  => '#000000',
        self::NODE_DEV  => '#777777',
    ];

    private const PACKAGE_REGULAR   = 'regular_package';
    private const PACKAGE_PHP       = 'php_package';
    private const PACKAGE_EXTENSION = 'ext_package';
    private const PACKAGE_COMPOSER  = 'composer_package';

    /** @var Composer */
    private $composer;
    /** @var ArrayLoader */
    private $arrayLoader;
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

    public function __construct(
        Composer $composer,
        bool $noDev,
        bool $noExt,
        bool $noPHP,
        bool $noVertexVersions,
        bool $noEdgeVersions
    ) {
        $this->noDev = $noDev;
        $this->noExt = $noExt;
        $this->noPHP = $noPHP;
        $this->noVertexVersions = $noVertexVersions;
        $this->noEdgeVersions = $noEdgeVersions;

        $this->composer = $composer;
        $this->arrayLoader = new ArrayLoader();
    }

    public function build(): Graph
    {
        if ($this->graph) {
            return $this->graph;
        }

        $this->graph = new Graph();
        $this->graph->setAttribute('graphviz.graph.concentrate', 'true');

        $dataComposerJson = $this->composer->getPackage();
        $dataComposerLock = $this->composer->getLocker()->getLockData();

        $this->processPackageData($dataComposerJson, self::NODE_ROOT, !$this->noDev);
        $this->processLockFile($dataComposerLock, !$this->noDev);

        foreach ($this->phpVertices as $vertex) {
            $php = $this->getVertex('php', self::NODE_DEP);
            $this->buildEdge($vertex, $php, '', self::EDGE_TYPE_PROVIDED);
        }

        foreach ($this->provides as list($package, $provided, $version)) {
            if (!isset($this->vertices[$provided])) {
                continue;
            }

            $packageVertex = $this->getVertex($package, self::NODE_DEP);
            $providedVertex = $this->getVertex($provided, self::NODE_DEP);
            $this->applyVertexStyle($providedVertex, self::VERTEX_TYPE_PROVIDED, self::NODE_DEP);
            $this->buildEdge($providedVertex, $packageVertex, $version, self::EDGE_TYPE_PROVIDED);
        }

        return $this->graph;
    }

    private function processPackageData(PackageInterface $package, string $nodeType, bool $includeDev): void
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
                $nodeType === self::NODE_DEV ? self::EDGE_TYPE_DEV : self::EDGE_TYPE_DEFAULT
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
                $this->buildEdge($rootVertex, $packageVertex, $constraint, self::EDGE_TYPE_DEV);
            }
        }
    }

    private function getVertex(string $name, string $nodeType): Vertex
    {
        if (!isset($this->vertices[$name])) {
            $vertex = $this->graph->createVertex($name);
            $packageType = $this->packageType($name);

            if ($nodeType === self::NODE_ROOT) {
                $vertexType = self::VERTEX_TYPE_ROOT;
            } else {
                switch ($packageType) {
                    case self::PACKAGE_EXTENSION:
                    case self::PACKAGE_COMPOSER:
                    case self::PACKAGE_PHP:
                        $vertexType = self::VERTEX_TYPE_PLATFORM;
                        break;
                    case self::PACKAGE_REGULAR:
                        $vertexType = $nodeType === self::NODE_DEV ? self::VERTEX_TYPE_DEV : self::VERTEX_TYPE_DEFAULT;
                        break;
                    default:
                        throw new \LogicException('Unknown package type');
                }
            }

            $this->applyVertexStyle($vertex, $vertexType, $nodeType);

            $this->vertices[$name] = $vertex;

            // make php-64bit and php-ipv6 'provide' PHP
            if ($packageType === self::PACKAGE_PHP && $name !== 'php') {
                $this->phpVertices[$name] = $vertex;
            }
        }

        return $this->vertices[$name];
    }

    private function buildEdge(Vertex $from, Vertex $to, string $version, string $edgeType): void
    {
        $edge = $from->createEdgeTo($to);

        if (!$this->noEdgeVersions) {
            $edge->setAttribute('graphviz.label', $version);
        }

        $this->applyEdgeStyle($edge, $edgeType);
    }

    private function processLockFile(array $dataComposerLock, bool $dev): void
    {
        $this->processPackageList($dataComposerLock['packages'], self::NODE_DEP);

        if ($dev) {
            $this->processPackageList($dataComposerLock['packages-dev'], self::NODE_DEV);
        }
    }

    private function processPackageList(array $packages, string $nodeType): void
    {
        foreach ($packages as $package) {
            $this->processPackageData($this->arrayLoader->load($package), $nodeType, false);
        }
    }

    private function ignorePackage(string $name): bool
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

    private function packageType(string $name): string
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

    private function applyVertexStyle(Vertex $vertex, string $vertexType, string $nodeType): void
    {
        $vertex->setAttribute('graphviz.shape', 'box');
        $vertex->setAttribute('graphviz.style', 'rounded, filled');
        $vertex->setAttribute('graphviz.fillcolor', self::VERTEX_COLORS[$vertexType]);
        $vertex->setAttribute('graphviz.color', self::NODE_BORDER_COLORS[$nodeType]);
        $vertex->setAttribute('graphviz.fontcolor', self::NODE_BORDER_COLORS[$nodeType]);
    }

    private function applyEdgeStyle(Edge $edge, string $edgeType): void
    {
        $edge->setAttribute('graphviz.color', self::EDGE_COLORS[$edgeType]);
        $edge->setAttribute('graphviz.fontcolor', self::EDGE_COLORS[$edgeType]);
    }
}
