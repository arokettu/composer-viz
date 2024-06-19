<?php

declare(strict_types=1);

namespace Arokettu\Composer\Viz;

use Composer\Plugin\Capability\CommandProvider;

/**
 * @internal
 */
final class VizCommandProvider implements CommandProvider
{
    public function getCommands(): array
    {
        return [
            new VizCommand(),
        ];
    }
}
