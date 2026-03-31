<?php

declare(strict_types=1);

namespace GameFeedback\Repository;

use PDO;

final class GameRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createTableIfNotExists(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS feedback_games (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_key VARCHAR(64) NOT NULL UNIQUE,
  game_name VARCHAR(120) NOT NULL,
  entry_path VARCHAR(160) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $this->pdo->exec($sql);
    }

    public function ensureDefaultGame(): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM feedback_games WHERE game_key = :game_key LIMIT 1');
        $stmt->execute([':game_key' => 'default']);

        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $insert = $this->pdo->prepare(
            'INSERT INTO feedback_games (game_key, game_name, entry_path, is_enabled, created_at, updated_at) VALUES (:game_key, :game_name, :entry_path, :is_enabled, :created_at, :updated_at)'
        );
        $insert->execute([
            ':game_key' => 'default',
            ':game_name' => '默认游戏',
            ':entry_path' => '/default',
            ':is_enabled' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findByKey(string $gameKey)
    {
        $stmt = $this->pdo->prepare('SELECT id, game_key, game_name, entry_path, is_enabled, created_at, updated_at FROM feedback_games WHERE game_key = :game_key LIMIT 1');
        $stmt->execute([':game_key' => $gameKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGames(): array
    {
        $stmt = $this->pdo->query('SELECT id, game_key, game_name, entry_path, is_enabled, created_at, updated_at FROM feedback_games ORDER BY id ASC');

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function createGame(string $gameKey, string $gameName, string $entryPath): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO feedback_games (game_key, game_name, entry_path, is_enabled, created_at, updated_at) VALUES (:game_key, :game_name, :entry_path, :is_enabled, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':game_key' => $gameKey,
            ':game_name' => $gameName,
            ':entry_path' => $entryPath,
            ':is_enabled' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function updateGameStatus(string $gameKey, bool $enabled): void
    {
        $stmt = $this->pdo->prepare('UPDATE feedback_games SET is_enabled = :is_enabled, updated_at = :updated_at WHERE game_key = :game_key');
        $stmt->execute([
            ':is_enabled' => $enabled ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':game_key' => $gameKey,
        ]);
    }

    public function updateEntryPath(string $gameKey, string $entryPath): void
    {
        $stmt = $this->pdo->prepare('UPDATE feedback_games SET entry_path = :entry_path, updated_at = :updated_at WHERE game_key = :game_key');
        $stmt->execute([
            ':entry_path' => $entryPath,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':game_key' => $gameKey,
        ]);
    }
}
