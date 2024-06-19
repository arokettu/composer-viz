<?php

namespace Arokettu\Composer\Viz;

use Arokettu\Composer\Viz\Engine\GraphBuilder;
use Composer\Command\BaseCommand;
use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class VizCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('viz');
        $this->setDescription('Generates a GraphViz representation of the dependency graph.');

        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file');
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output file format');

        $this->addOption('no-dev', null, InputOption::VALUE_NONE, 'Ignore development dependencies');
        $this->addOption('no-php', null, InputOption::VALUE_NONE, 'Ignore PHP dependencies');
        $this->addOption('no-ext', null, InputOption::VALUE_NONE, 'Ignore PHP extension dependencies');
        $this->addOption('no-platform', null, InputOption::VALUE_NONE, '--no-php and --no-ext');

        $this->addOption('no-pkg-versions', null, InputOption::VALUE_NONE, 'Do not render version labels on vertices');
        $this->addOption('no-dep-versions', null, InputOption::VALUE_NONE, 'Do not render version labels on arrows');
        $this->addOption('no-versions', null, InputOption::VALUE_NONE, '--no-pkg-versions and --no-dep-versions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $noDev  = $input->getOption('no-dev');

        $noPlatform  = $input->getOption('no-platform');
        $noExt = $noPlatform || $input->getOption('no-ext');
        $noPHP = $noPlatform || $input->getOption('no-php');

        $format  = $input->getOption('format');
        $outFile = $input->getOption('output');

        $noVersions = $input->getOption('no-versions');
        $noVertexVersions = $noVersions || $input->getOption('no-pkg-versions');
        $noEdgeVersions   = $noVersions || $input->getOption('no-dep-versions');

        $graph = (new GraphBuilder(
            $this->getComposer(),
            $noDev,
            $noExt,
            $noPHP,
            $noVertexVersions,
            $noEdgeVersions
        ))->build();

        $viz = new GraphViz();
        $viz->setFormat($this->detectFormat($outFile, $format));

        if ($outFile) {
            $file = $viz->createImageFile($graph);
            rename($file, $outFile);
        } else {
            $viz->display($graph);
        }

        return 0;
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
