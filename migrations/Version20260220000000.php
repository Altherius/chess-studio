<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game and analysis tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game (
            id SERIAL PRIMARY KEY,
            pgn TEXT NOT NULL,
            player_white VARCHAR(255) DEFAULT NULL,
            player_black VARCHAR(255) DEFAULT NULL,
            result VARCHAR(20) DEFAULT NULL,
            event VARCHAR(255) DEFAULT NULL,
            date DATE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql("COMMENT ON COLUMN game.created_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE TABLE analysis (
            id SERIAL PRIMARY KEY,
            game_id INT NOT NULL,
            depth INT NOT NULL DEFAULT 20,
            evaluation JSON DEFAULT NULL,
            best_moves JSON DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT fk_analysis_game FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE
        )');
        $this->addSql("COMMENT ON COLUMN analysis.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_analysis_game_id ON analysis (game_id)');

        $this->addSql('CREATE TABLE messenger_messages (
            id BIGSERIAL PRIMARY KEY,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        )');
        $this->addSql("COMMENT ON COLUMN messenger_messages.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.available_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_messenger_queue ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_available ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_delivered ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE analysis');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
