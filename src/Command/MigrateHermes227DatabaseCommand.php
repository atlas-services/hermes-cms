<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Migration\Hermes227SqliteMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe une base SQLite Hermes 2.2.x vers le schéma Hermes 3.
 *
 * Les arguments sont en général {@code data/<nom>.sqlite} : le stem {@code <nom>} (sans {@code .sqlite})
 * sert à réécrire les URLs {@code /{nom}/uploads/} → {@code /uploads/{nom}/} (source = {@code dataFrom}, cible = {@code dataTo}).
 *
 * Prérequis côté source : tables {@code template}, {@code menu}, {@code section}, {@code post}.
 * Optionnel : {@code sheet} (feuilles → menus racines), {@code user}.
 *
 * Optionnel : {@code data/config/<nom>.sqlite} (même {@code <nom>} que la base source) pour recopier
 * la table {@code config} (ex. {@code data/atlas.sqlite} + {@code data/config/atlas.sqlite}).
 *
 * Après migration : ajuster {@code DATABASE_URL} vers le fichier cible, puis copier les médias avec
 * {@code app:migrate-media} (les posts Vich 2.2.7 ne sont pas au même chemin qu’en 3.x).
 * Puis éventuellement {@code php bin/console app:init-hermes} pour (re)charger configs / gabarits par défaut sans doublons.
 */
#[AsCommand(
    name: 'app:migrate',
    description: 'Migre une base SQLite Hermes 2.2.x vers le schéma Hermes 3 (fichiers dataFrom / dataTo).',
)]
final class MigrateHermes227DatabaseCommand extends Command
{
    public function __construct(private readonly Hermes227SqliteMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dataFrom', InputArgument::REQUIRED, 'Ancienne base (ex. data/db/jazzenville.sqlite — stem = nom dans /{nom}/uploads/)')
            ->addArgument('dataTo', InputArgument::REQUIRED, 'Nouvelle base (ex. data/db/jazzenville.sqlite — stem = nom dans /uploads/{nom}/ ; créée si absente)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Réinitialiser la cible si elle existe déjà (drop du schéma Doctrine puis recréation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dataFrom = (string) $input->getArgument('dataFrom');
        $dataTo = (string) $input->getArgument('dataTo');
        $force = (bool) $input->getOption('force');

        try {
            $this->migrator->migrate($dataFrom, $dataTo, $force, static function (string $msg) use ($io): void {
                $io->writeln($msg);
            });
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Migration terminée.');

        return Command::SUCCESS;
    }
}
