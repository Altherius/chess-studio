<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222133544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add whiteElo, blackElo, round columns to game and backfill from PGN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD white_elo INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD black_elo INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD round VARCHAR(50) DEFAULT NULL');

        // Backfill from PGN headers
        $this->addSql("UPDATE game SET white_elo = CAST(substring(pgn FROM '\\[WhiteElo \"(\\d+)\"\\]') AS INT) WHERE white_elo IS NULL AND pgn ~ '\\[WhiteElo \"\\d+\"\\]'");
        $this->addSql("UPDATE game SET black_elo = CAST(substring(pgn FROM '\\[BlackElo \"(\\d+)\"\\]') AS INT) WHERE black_elo IS NULL AND pgn ~ '\\[BlackElo \"\\d+\"\\]'");
        $this->addSql("UPDATE game SET round = substring(pgn FROM '\\[Round \"([^\"?]+)\"\\]') WHERE round IS NULL AND pgn ~ '\\[Round \"[^\"?]+\"\\]'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game DROP white_elo');
        $this->addSql('ALTER TABLE game DROP black_elo');
        $this->addSql('ALTER TABLE game DROP round');
    }
}
