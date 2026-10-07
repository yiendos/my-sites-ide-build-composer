<?php

namespace Yiendos\MySitesIde\Build\Composer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Build\Composer\Traits\InteractsWithComposer;

class ComposerInstallCommand extends Command
{
    use InteractsWithComposer;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('build:composer-install')
            ->setDescription("Install a site's composer dependencies (Repos/<site>/<IDE_APP_DIR>)")
            ->addArgument('site', InputArgument::REQUIRED, 'Which site, as in Repos/<site>')
        ;
    }

    /**
     * Two passes, as the old core ide:composer-install did: the first
     * installs the packages without running scripts or building the
     * autoloader - a fresh Laravel site's scripts (package:discover) need
     * vendor/ complete first - the second runs both.
     *
     * Platform requirements are ignored because the composer image's PHP
     * and extensions aren't the site's; fpm is what runs the code.
     *
     * Also run by the site-dependencies hook, after
     * ide:repo-clone --laravel.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!$this->siteHasComposerJson($site)) {
            $io->warning('Repos/' . $this->siteApp($site) . "/composer.json doesn't exist - nothing to install.");
            return Command::SUCCESS;
        }

        $passes = [
            ['install', '--no-scripts', '--ignore-platform-reqs', '--no-autoloader', '--prefer-dist'],
            ['install', '--ignore-platform-reqs', '--prefer-dist'],
        ];

        foreach ($passes as $arguments) {
            if ($this->composer($output, $site, $arguments) !== 0) {
                $io->error("composer install failed for {$site} - see above.");
                return Command::FAILURE;
            }
        }

        $io->success("Composer dependencies installed for {$site}.");

        return Command::SUCCESS;
    }
}
