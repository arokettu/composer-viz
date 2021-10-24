<?php

namespace Arokettu\ComposerViz;

use Composer\Plugin\Capability\CommandProvider;

/**
 * @internal
 */
final class VizCommandProvider implements CommandProvider
{
    public function getCommands()
    {
        return [
            new VizCommand(),
        ];
    }
}
