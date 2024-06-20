<?php

declare(strict_types=1);

namespace Arokettu\Composer\Viz\Tests;

use Arokettu\Composer\Viz\Engine\GraphBuilder;
use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Graphp\GraphViz\GraphViz;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SamplesTest extends TestCase
{
    private function samples(): array
    {
        return array_map(function ($p) {
            return [$p];
        }, glob(__DIR__ . '/data/samples/*'));
    }

    private function createComposer(string $path): Composer
    {
        return Factory::create(new NullIO(), $path . '/composer.json', true, true);
    }

    /**
     * @dataProvider samples
     */
    #[DataProvider('samples')]
    public function testSamples(string $path): void
    {
        $composer = $this->createComposer($path);

        $gb = new GraphBuilder($composer, false, false, false, false, false);
        $viz = new GraphViz();
        $viz->setFormat('dot');

        self::assertEquals(
            file_get_contents($path . '/composer.dot'),
            $viz->createScript($gb->build())
        );
    }
}
