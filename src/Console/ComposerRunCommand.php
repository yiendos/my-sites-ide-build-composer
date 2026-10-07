<?php

namespace Yiendos\MySitesIde\Build\Composer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Build\Composer\Traits\InteractsWithComposer;

class ComposerRunCommand extends Command
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
            ->setName('build:composer-run')
            ->setDescription('Run any composer command for a site, e.g. build:composer-run mysite -- require laravel/sanctum')
            ->addArgument('site', InputArgument::REQUIRED, 'Which site, as in Repos/<site>')
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::REQUIRED, "Composer's own arguments - put them after -- so their options reach composer")
        ;
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!is_dir((getenv('IDE_ROOT') ?: getcwd()) . "/Repos/{$site}/Sites")) {
            $io->error("Repos/{$site}/Sites doesn't exist.");
            return Command::FAILURE;
        }

        return $this->composer($output, $site, $input->getArgument('arguments')) === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
