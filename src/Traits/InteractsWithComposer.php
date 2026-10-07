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
     * The site's application code, relative to Repos/ (and to /opt/repos in
     * the container) - <site>/<IDE_APP_DIR>, which the IDE sets (deploy by
     * default, `.` for the repository root). Falls back to deploy on an IDE
     * that predates it.
     *
     * @param string $site
     * @return string
     */
    protected function siteApp(string $site): string
    {
        $app = trim((string) (getenv('IDE_APP_DIR') ?: 'deploy'), '/');

        return $app === '.' || $app === '' ? $site : "{$site}/{$app}";
    }

    /**
     * The site's application code on the host
     *
     * @param string $site
     * @return string
     */
    protected function sitePath(string $site): string
    {
        return (getenv('IDE_ROOT') ?: getcwd()) . '/Repos/' . $this->siteApp($site);
    }

    /**
     * Whether the site's application code has a composer.json to work from
     *
     * @param string $site
     * @return bool
     */
    protected function siteHasComposerJson(string $site): bool
    {
        return is_file($this->sitePath($site) . '/composer.json');
    }

    /**
     * Runs `composer <arguments>` in the site's application code (mounted at
     * /opt/repos/<site>/<IDE_APP_DIR>), echoing the command first like the
     * core commands do
     *
     * @param OutputInterface $output
     * @param string $site
     * @param array<int, string> $arguments
     * @return int the exit code
     */
    protected function composer(OutputInterface $output, string $site, array $arguments): int
    {
        $command = 'docker compose run --rm composer --working-dir=' . escapeshellarg($this->siteApp($site))
            . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        $output->writeLn($command);
        passthru($command, $code);

        return $code;
    }
}
