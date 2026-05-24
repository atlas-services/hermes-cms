<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Migration\Hermes227MediaMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Copie les répertoires médias Hermes 2.2.x vers Hermes 3 après {@see MigrateHermes227DatabaseCommand}.
 *
 * Utilise la base SQLite **déjà migrée** (menu_id Hermes 3) pour recalculer les chemins Vich des posts.
 *
 * Exemple :
 * {@code php bin/console app:migrate-media data/db/jazzenville.sqlite /var/www/hermes/public/jazzenville/uploads public/uploads/jazzenville}
 *
 * Copie bash équivalente pour {@code content/} et {@code entity/Config/} uniquement :
 * {@code rsync -a ancien/uploads/content/ nouveau/uploads/content/}
 * {@code rsync -a ancien/uploads/entity/Config/ nouveau/uploads/entity/Config/}
 */
#[AsCommand(
    name: 'app:migrate-media',
    description: 'Copie uploads 2.2.x → 3 (content, Config, posts Vich restructurés).',
)]
final class MigrateHermes227MediaCommand extends Command
{
    public function __construct(private readonly Hermes227MediaMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'dataDb',
                InputArgument::REQUIRED,
                'Base SQLite Hermes 3 migrée (post/section/menu à jour)',
            )
            ->addArgument(
                'from',
                InputArgument::REQUIRED,
                'Racine uploads source (contient entity/ et content/), ex. public/uploads de l’ancien site',
            )
            ->addArgument(
                'to',
                InputArgument::REQUIRED,
                'Racine uploads cible (souvent public/uploads du projet Hermes 3)',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lister sans copier')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Écraser les fichiers déjà présents en cible');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $stats = $this->migrator->migrate(
                (string) $input->getArgument('dataDb'),
                (string) $input->getArgument('from'),
                (string) $input->getArgument('to'),
                (bool) $input->getOption('dry-run'),
                (bool) $input->getOption('overwrite'),
                static function (string $msg) use ($io): void {
                    $io->writeln($msg);
                },
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Élément', 'Nombre'],
            [
                ['Config', (string) $stats['configFiles']],
                ['content (admin)', (string) $stats['contentFiles']],
                ['Posts copiés', (string) $stats['postsCopied']],
                ['Posts ignorés (déjà en cible)', (string) $stats['postsSkipped']],
                ['Posts sans fichier source', (string) $stats['postsMissing']],
                ['Fichiers renommés (espaces → _)', (string) $stats['filesRenamed']],
                ['Posts file_name mis à jour', (string) $stats['postsFileNameUpdated']],
                ['Publications HTML corrigées', (string) $stats['contentRowsUpdated']],
            ],
        );

        $io->success('Migration médias terminée.');

        return Command::SUCCESS;
    }
}
