<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

final class Game extends AdminSubModule
{
    /**
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'list' => [
                'methods' => ['GET'],
            ],
            'create' => [
                'methods' => ['POST'],
            ],
            'updateStatus' => [
                'methods' => ['POST'],
            ],
            'updateEntry' => [
                'methods' => ['POST'],
            ],
            'entrypoints' => [
                'methods' => ['GET'],
            ],
        ];
    }

    protected function list(): void
    {
        $this->ensureSuperAdmin();

        Responder::send([
            'ok' => true,
            'games' => $this->createGameRepository()->listGames(),
        ]);
    }

    protected function create(): void
    {
        $this->ensureSuperAdmin();

        $payload = Request::jsonBody();
        $gameKey = $this->sanitizer->sanitizeSingleLine((string)($payload['gameKey'] ?? ''), 64);
        $gameName = $this->sanitizer->sanitizeSingleLine((string)($payload['gameName'] ?? ''), 120);
        $entryPath = $this->sanitizeEntryPath((string)($payload['entryPath'] ?? ''));

        if ($gameKey === '' || $gameName === '' || $entryPath === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', 'gameKey、gameName、entryPath 不能为空。', 422);
        }

        if (preg_match('/^[a-z][a-z0-9_\-]{1,63}$/', $gameKey) !== 1) {
            Responder::error('INVALID_GAME_KEY', 'gameKey 仅支持小写字母、数字、下划线和短横线。', 422);
        }

        $repo = $this->createGameRepository();
        if ($repo->findByKey($gameKey)) {
            Responder::error('GAME_KEY_EXISTS', 'gameKey 已存在。', 409);
        }

        $repo->createGame($gameKey, $gameName, $entryPath);

        Responder::send([
            'ok' => true,
            'message' => '游戏已开通。',
        ]);
    }

    protected function updateStatus(): void
    {
        $this->ensureSuperAdmin();

        $payload = Request::jsonBody();
        $gameKey = $this->sanitizer->sanitizeSingleLine((string)($payload['gameKey'] ?? ''), 64);
        $enabledRaw = $payload['enabled'] ?? null;

        if ($gameKey === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', 'gameKey 不能为空。', 422);
        }

        if ($enabledRaw === null) {
            Responder::error('MISSING_REQUIRED_FIELDS', 'enabled 不能为空。', 422);
        }

        $enabled = (bool)$enabledRaw;
        $repo = $this->createGameRepository();
        $game = $repo->findByKey($gameKey);
        if (!$game) {
            Responder::error('GAME_NOT_FOUND', '游戏不存在。', 404);
        }

        $repo->updateGameStatus($gameKey, $enabled);

        Responder::send([
            'ok' => true,
            'message' => $enabled ? '游戏已开通。' : '游戏已停用。',
        ]);
    }

    protected function updateEntry(): void
    {
        $this->ensureSuperAdmin();

        $payload = Request::jsonBody();
        $gameKey = $this->sanitizer->sanitizeSingleLine((string)($payload['gameKey'] ?? ''), 64);
        $entryPath = $this->sanitizeEntryPath((string)($payload['entryPath'] ?? ''));

        if ($gameKey === '' || $entryPath === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', 'gameKey 和 entryPath 不能为空。', 422);
        }

        $repo = $this->createGameRepository();
        $game = $repo->findByKey($gameKey);
        if (!$game) {
            Responder::error('GAME_NOT_FOUND', '游戏不存在。', 404);
        }

        $repo->updateEntryPath($gameKey, $entryPath);

        Responder::send([
            'ok' => true,
            'message' => '入口路径已更新。',
        ]);
    }

    protected function entrypoints(): void
    {
        $this->ensureSuperAdmin();

        $games = $this->createGameRepository()->listGames();
        $rows = [];
        foreach ($games as $game) {
            if ((int)($game['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $entryPath = (string)($game['entry_path'] ?? '');
            $gameKey = (string)($game['game_key'] ?? '');
            if ($entryPath === '' || $gameKey === '') {
                continue;
            }

            $rows[] = [
                'gameKey' => $gameKey,
                'entryPath' => $entryPath,
                'playerUrlExample' => $entryPath . '?gameKey=' . rawurlencode($gameKey),
            ];
        }

        Responder::send([
            'ok' => true,
            'entrypoints' => $rows,
        ]);
    }

    private function sanitizeEntryPath(string $entryPath): string
    {
        $clean = $this->sanitizer->sanitizeSingleLine($entryPath, 160);
        if ($clean === '') {
            return '';
        }

        if (strpos($clean, '/') !== 0) {
            $clean = '/' . $clean;
        }

        if (preg_match('/^\/[a-zA-Z0-9_\-\/]{1,159}$/', $clean) !== 1) {
            Responder::error('INVALID_ENTRY_PATH', 'entryPath 格式不合法。', 422);
        }

        return $clean;
    }
}
