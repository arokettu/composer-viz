<?php

namespace SandFoxMe\ComposerViz;

use Composer\Plugin\Capability\CommandProvider;

class VizCommandProvider implements CommandProvider
{
    public function getCommands()
    {
        return [
            new VizCommand(),
        ];
    }
}
