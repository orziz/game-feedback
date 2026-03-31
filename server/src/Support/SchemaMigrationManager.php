<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\UserRepository;
use PDO;

final class SchemaMigrationManager
{
    /**
     * 1: 整数化 type/severity/status
     * 2: 附件字段
     * 3: assigned_to 与索引
     * 4: ticket_operations 表
     */
    const CURRENT_SCHEMA_VERSION = 4;

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function installLatestSchema(): void
    {
        $this->createBaseTables();
    }

    public function migrateFromVersion(int $schemaVersion): int
    {
        $ticketRepository = new TicketRepository($this->pdo);
        $version = $schemaVersion;

        $this->createBaseTables();

        if ($version < 1) {
            $ticketRepository->migrateLegacyEnumColumns();
            $version = 1;
        }

        if ($version < 2) {
            $ticketRepository->migrateAttachmentColumns();
            $version = 2;
        }

        if ($version < 3) {
            $ticketRepository->migrateAssignmentSupport();
            $version = 3;
        }

        if ($version < 4) {
            $ticketRepository->migrateTicketOperationsSupport();
            $version = 4;
        }

        return $version;
    }

    private function createBaseTables(): void
    {
        (new TicketRepository($this->pdo))->createTableIfNotExists();
        (new UserRepository($this->pdo))->createTableIfNotExists();
    }
}