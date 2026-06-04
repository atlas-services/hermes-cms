<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LegalPagesInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-mentions-legales',
    description: 'Crée les pages légales (menus inactifs) : /mentions-legales, /confidentialite, /cgu-cgv avec contenu libre depuis l’API Hermes (un modèle par page).',
)]
final class InitMentionsLegalesCommand extends Command
{
    public function __construct(
        private readonly LegalPagesInitializer $legalPagesInitializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale des menus', 'fr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = (string) $input->getOption('locale');

        try {
            $result = $this->legalPagesInitializer->initialize($locale);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d page(s) créée(s), %d déjà présente(s).',
            $result['created'],
            $result['skipped'],
        ));

        $anyApi = false;
        foreach (LegalPagesInitializer::PAGES as $pageDef) {
            $slug = $pageDef['slug'];
            $report = $result['pages'][$slug] ?? null;
            $path = sprintf('/%s/%s', $locale, $slug);

            if ($report !== null && ($report['skipped'] ?? false)) {
                $io->writeln(sprintf('  • %s — déjà existante, ignorée', $path));
                continue;
            }

            if ($report !== null && $report['template_iri'] !== null) {
                $anyApi = true;
                $io->writeln(sprintf(
                    '  • %s — modèle API : %s (%s)',
                    $path,
                    $report['template_label'] ?? '—',
                    $report['template_iri'],
                ));
            } else {
                $io->writeln(sprintf('  • %s — contenu de secours (aucun modèle API correspondant)', $path));
            }
        }

        if ($result['created'] > 0 && !$anyApi) {
            $io->warning('Catalogue API Hermes vide ou sans entrées mentions-legales / confidentialite / cgu-cgv. Vérifiez API_HERMES_* dans .env.');
        }

        return Command::SUCCESS;
    }
}
