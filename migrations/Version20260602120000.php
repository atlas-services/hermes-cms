<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Désactive les gabarits liste retirés de l’admin (card1, card2, folio2, carousel2, folio5).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE template SET active = 0 WHERE code IN ('card1', 'card2', 'folio2', 'carousel2', 'folio5')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE template SET active = 1 WHERE code IN ('card1', 'card2', 'folio2', 'carousel2', 'folio5')");
    }
}
