<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WelcomeSiteInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:init-welcome-site',
    description: 'Crée le site d’accueil noir : ACCUEIL, CONTACT, footer et pages légales Atlas Services si aucun menu n’existe.',
)]
final class InitWelcomeSiteCommand extends Command
{
    public function __construct(
        private readonly WelcomeSiteInitializer $welcomeSiteInitializer,
        #[Autowire(param: 'app.name')]
        private readonly string $appName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale du menu ACCUEIL', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = (string) $input->getOption('locale');

        try {
            $result = $this->welcomeSiteInitializer->initializeIfEmpty($locale);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$result['created']) {
            $io->note('Aucun menu créé : des menus existent déjà dans la base.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Site d’accueil créé (menu ACCUEIL id %d) — accueil, contact, footer et pages légales pour « %s ».',
            $result['menu_id'],
            trim($this->appName) !== '' ? $this->appName : 'monsite',
        ));
        $io->writeln(sprintf('  • URL front : /%s/%s', $locale, WelcomeSiteInitializer::MENU_SLUG));
        $io->writeln(sprintf('  • URL contact : /%s/%s', $locale, WelcomeSiteInitializer::CONTACT_MENU_SLUG));

        return Command::SUCCESS;
    }
}
