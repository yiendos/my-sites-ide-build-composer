<?php

namespace Yiendos\MySitesIde\Build\Composer\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs Composer for a site in a throwaway composer container. Every
 * `docker compose` call runs from the IDE root, as the my-sites-ide CLI is
 * run there.
 */
trait InteractsWithComposer
{
    /**
     * Whether Repos/<site>/Sites has a composer.json to work from
     *
     * @param string $site
     * @return bool
     */
    protected function siteHasComposerJson(string $site): bool
    {
        return is_file((getenv('IDE_ROOT') ?: getcwd()) . "/Repos/{$site}/Sites/composer.json");
    }

    /**
     * Runs `composer <arguments>` in Repos/<site>/Sites (mounted at
     * /opt/repos/<site>/Sites), echoing the command first like the core
     * commands do
     *
     * @param OutputInterface $output
     * @param string $site
     * @param array<int, string> $arguments
     * @return int the exit code
     */
    protected function composer(OutputInterface $output, string $site, array $arguments): int
    {
        $command = 'docker compose run --rm composer --working-dir=' . escapeshellarg("{$site}/Sites")
            . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        $output->writeLn($command);
        passthru($command, $code);

        return $code;
    }
}
