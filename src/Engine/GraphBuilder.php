<?php

namespace Arokettu\ComposerViz\Engine;

use Arokettu\ComposerViz\Helpers\StringHelper;
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
    const VERTEX_TYPE_DEFAULT   = 'vertex_default';
    const VERTEX_TYPE_ROOT      = 'vertex_root';
    const VERTEX_TYPE_DEV       = 'vertex_dev';
    const VERTEX_TYPE_PLATFORM  = 'vertex_platform';
    const VERTEX_TYPE_PROVIDED  = 'vertex_provided';

    private static $vertexColors = [
        self::VERTEX_TYPE_DEFAULT     => '#ffffff',
        self::VERTEX_TYPE_ROOT        => '#eeffee',
        self::VERTEX_TYPE_DEV         => '#eeeeee',
        self::VERTEX_TYPE_PLATFORM    => '#eeeeff',
        self::VERTEX_TYPE_PROVIDED    => '#ffeeee',
    ];

    const EDGE_TYPE_DEFAULT     = 'edge_default';
    const EDGE_TYPE_DEV         = 'edge_dev';
    const EDGE_TYPE_PROVIDED    = 'edge_provided';

    private static $edgeColors = [
        self::EDGE_TYPE_DEFAULT     => '#000000',
        self::EDGE_TYPE_DEV         => '#999999',
        self::EDGE_TYPE_PROVIDED    => '#cc9999',
    ];

    const NODE_ROOT = 'root_node';
    const NODE_DEP  = 'dep_node';
    const NODE_DEV  = 'dev_node';

    const PACKAGE_REGULAR   = 'regular_package';
    const PACKAGE_PHP       = 'php_package';
    const PACKAGE_EXTENSION = 'ext_package';
    const PACKAGE_COMPOSER  = 'composer_package';

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

    public function __construct(Composer $composer, $noDev, $noExt, $noPHP, $noVertexVersions, $noEdgeVersions)
    {
        $this->noDev = $noDev;
        $this->noExt = $noExt;
        $this->noPHP = $noPHP;
        $this->noVertexVersions = $noVertexVersions;
        $this->noEdgeVersions = $noEdgeVersions;

        $this->composer = $composer;
        $this->arrayLoader = new ArrayLoader();
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
            $this->applyVertexStyle($providedVertex, self::VERTEX_TYPE_PROVIDED);
            $this->buildEdge($providedVertex, $packageVertex, $version, self::EDGE_TYPE_PROVIDED);
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

    private function getVertex($name, $nodeType)
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

            $this->applyVertexStyle($vertex, $vertexType);

            $this->vertices[$name] = $vertex;

            // make php-64bit and php-ipv6 'provide' PHP
            if ($packageType === self::PACKAGE_PHP && $name !== 'php') {
                $this->phpVertices[$name] = $vertex;
            }
        }

        return $this->vertices[$name];
    }

    private function buildEdge(Vertex $from, Vertex $to, $version, $edgeType)
    {
        $edge = $from->createEdgeTo($to);

        if (!$this->noEdgeVersions) {
            $edge->setAttribute('graphviz.label', $version);
        }

        $this->applyEdgeStyle($edge, $edgeType);
    }

    private function processLockFile($dataComposerLock, $dev)
    {
        $this->processPackageList($dataComposerLock['packages'], self::NODE_DEP);

        if ($dev) {
            $this->processPackageList($dataComposerLock['packages-dev'], self::NODE_DEV);
        }
    }

    private function processPackageList(array $packages, $nodeType)
    {
        foreach ($packages as $package) {
            $this->processPackageData($this->arrayLoader->load($package), $nodeType, false);
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
        if (StringHelper::strContains($name, '/') || $name === '__root__') {
            return self::PACKAGE_REGULAR;
        }

        if (StringHelper::strStartsWith($name, 'ext-') || StringHelper::strStartsWith($name, 'lib-')) {
            return self::PACKAGE_EXTENSION;
        }

        if (StringHelper::strStartsWith($name, 'php')) {
            return self::PACKAGE_PHP;
        }

        if (StringHelper::strStartsWith($name, 'composer-')) {
            return self::PACKAGE_COMPOSER;
        }

        throw new \RuntimeException("Unable to determine package type of {$name}");
    }

    private function applyVertexStyle(Vertex $vertex, $vertexType)
    {
        $vertex->setAttribute('graphviz.shape', 'box');
        $vertex->setAttribute('graphviz.style', 'rounded, filled');
        $vertex->setAttribute('graphviz.fillcolor', self::$vertexColors[$vertexType]);
    }

    private function applyEdgeStyle(Edge $edge, $edgeType)
    {
        $edge->setAttribute('graphviz.color', self::$edgeColors[$edgeType]);
        $edge->setAttribute('graphviz.fontcolor', self::$edgeColors[$edgeType]);
    }
}
