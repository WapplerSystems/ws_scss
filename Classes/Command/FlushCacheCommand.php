<?php
declare(strict_types=1);

namespace WapplerSystems\WsScss\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Targeted invalidation for the ws_scss content-hash cache, which
 * NonFlushableFileBackend deliberately keeps out of reach of a generic
 * `cache:flush`. Run this whenever a .scss source actually changed -- e.g.
 * wired into a project's composer post-update-cmd/post-autoload-dump so it
 * happens automatically on every deploy. Also reachable manually via the
 * Backend's "Flush SCSS cache" clear-cache toolbar action
 * (FlushCacheController) for ad-hoc use.
 */
class FlushCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Flush the ws_scss compiled-SCSS content-hash cache (not covered by a generic cache:flush, see NonFlushableFileBackend).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        GeneralUtility::makeInstance(CacheManager::class)->getCache('ws_scss')->flushByTag('scss');
        $output->writeln('ws_scss cache flushed.');
        return Command::SUCCESS;
    }
}
