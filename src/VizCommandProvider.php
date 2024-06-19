<?php

namespace Arokettu\Composer\Viz;

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
