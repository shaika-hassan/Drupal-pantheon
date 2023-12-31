<?php

namespace Pantheon\Terminus\Commands\Solr;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Commands\WorkflowProcessingTrait;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

/**
 * Class DisableCommand
 * @package Pantheon\Terminus\Commands\Solr
 */
class DisableCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;
    use WorkflowProcessingTrait;

    /**
     * Disables Solr add-on for a site.
     *
     * @authorize
     *
     * @command solr:disable
     *
     * @param string $site_id Site name
     *
     * @usage <site> Disables Solr add-on for <site>.
     */
    public function disable($site_id)
    {
        $site = $this->getSiteById($site_id);
        $workflow = $site->getSolr()->disable();
        $this->processWorkflow($workflow);
        $this->log()->notice($workflow->getMessage());
    }
}
