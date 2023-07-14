<?php

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;

class DeployCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Run several commands after performing a code deployment.
     *
     * @command deploy
     * @option no-maintenance Do not set the maintenance_mode during the deploy process.
     *
     * @usage drush deploy -v -y
     *   Run updates with verbose logging and accept all prompts.
     *
     * @version 10.3
     *
     * @topics docs:deploy
     *
     * @throws \Exception
     */
    public function deploy($options = ['no-maintenance' => false]): void
    {
        $self = $this->siteAliasManager()->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $manager = $this->processManager();

        $this->logger()->notice("Database updates start.");

        // Remove the 'no-maintenance' option for other commands except the updb.
        unset($redispatchOptions['no-maintenance']);
        $updb_options = [
            'no-cache-clear' => true,
            'no-maintenance' => $options['no-maintenance'],
        ];
        $process = $manager->drush($self, 'updatedb', [], $updb_options + $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        $this->logger()->success("Config import start.");
        $process = $manager->drush($self, 'config:import', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        $this->logger()->success("Deploy hook start.");
        $process = $manager->drush($self, 'deploy:hook', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }

    /**
     * @param ProcessManager $manager
     * @param SiteAlias $self
     * @param array $redispatchOptions
     */
    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions): void
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger()->success("Cache rebuild start.");
        $process = $manager->drush($self, 'cache:rebuild', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
