<?php

namespace Pantheon\Terminus\Commands\Self;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Helpers\LocalMachineHelper;

/**
 * Class ClearCacheCommand.
 *
 * @package Pantheon\Terminus\Commands\Self
 */
class ClearCacheCommand extends TerminusCommand
{
    /**
     * Clears the local Terminus command cache.
     *
     * @command self:clear-cache
     * @aliases self:cc
     *
     * @usage Clears the local Terminus session cache and all locally saved machine tokens.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function clearCache()
    {
        $local_machine = $this->getContainer()->get(LocalMachineHelper::class);
        $fs = $local_machine->getFilesystem();
        $finder = $local_machine->getFinder();

        $finder->files()->in($this->getConfig()->get('command_cache_dir'));
        foreach ($finder as $file) {
            $fs->remove($file);
        }
        $this->log()->notice('The local Terminus cache has been cleared.');
    }
}
