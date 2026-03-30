<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/Responder.php';
require_once __DIR__ . '/Support/Request.php';
require_once __DIR__ . '/Support/Database.php';
require_once __DIR__ . '/Support/AdminToken.php';
require_once __DIR__ . '/Enums/TicketType.php';
require_once __DIR__ . '/Enums/TicketSeverity.php';
require_once __DIR__ . '/Enums/TicketStatus.php';
require_once __DIR__ . '/Repository/TicketRepository.php';

final class App
{
    /** @var array */
    private $appConfig;

    /** @var string */
    private $databaseConfigPath;

    /** @var bool */
    private $installed;

    /** @var array */
    private $dbConfig;

    public function __construct(array $appConfig, string $databaseConfigPath)
    {
        $this->appConfig = $appConfig;
        $this->databaseConfigPath = $databaseConfigPath;

        date_default_timezone_set($this->appConfig['timezone'] ?? 'Asia/Shanghai');

        $this->installed = is_file($this->databaseConfigPath);
        $this->dbConfig = $this->installed ? require $this->databaseConfigPath : [];
    }

    public function run(): void
    {
        $action = Request::query('action');

        if ($action === 'health') {
            Responder::send([
                'ok' => true,
                'installed' => $this->installed,
                'time' => date('c'),
            ]);
        }

        if ($action === 'install_status') {
            Responder::send([
                'ok' => true,
                'installed' => $this->installed,
                'uploadMode' => (string)($this->dbConfig['upload_mode'] ?? 'off'),
            ]);
        }

        if ($action === 'enum_options') {
            $this->enumOptions();
        }

        if (!$this->installed && $action !== 'install' && $action !== 'enum_options') {
            Responder::send([
                'ok' => false,
                'code' => 'NEED_INSTALL',
                'message' => '系统尚未安装，请先完成初始化。',
            ], 400);
        }

        $getActions = ['health', 'install_status', 'enum_options', 'ticket', 'ticket_search', 'admin_list', 'admin_detail', 'admin_attachment_download'];
        if (Request::method() !== 'POST' && !in_array($action, $getActions, true)) {
            Responder::send([
                'ok' => false,
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => '仅支持 POST 请求。',
            ], 405);
        }

        switch ($action) {
            case 'install':
                $this->install();
                break;
            case 'submit':
                $this->submitFeedback();
                break;
            case 'ticket':
                $this->queryTicket();
                break;
            case 'ticket_search':
                $this->searchTickets();
                break;
            case 'enum_options':
                $this->enumOptions();
                break;
            case 'admin_login':
                $this->adminLogin();
                break;
            case 'admin_list':
                $this->adminList();
                break;
            case 'admin_detail':
                $this->adminDetail();
                break;
            case 'admin_attachment_download':
                $this->adminAttachmentDownload();
                break;
            case 'admin_update':
                $this->adminUpdate();
                break;
            default:
                Responder::send([
                    'ok' => false,
                    'code' => 'NOT_FOUND',
                    'message' => '未找到对应接口。',
                ], 404);
        }
    }

    private function enumOptions(): void
    {
        $lang = strtolower(Request::query('lang', 'zh-CN'));
            $isEn = strpos($lang, 'en') === 0;

        $typeLabels = $isEn
            ? ['BUG', 'Feature', 'Suggestion', 'Other']
            : ['BUG', '优化', '建议', '其他'];

        $severityLabels = $isEn
            ? ['Low', 'Medium', 'High', 'Critical']
            : ['低', '中', '高', '致命'];

        $statusLabels = $isEn
            ? ['Pending', 'In Progress', 'Resolved', 'Closed']
            : ['待处理', '处理中', '已解决', '已关闭'];

        Responder::send([
            'ok' => true,
            'types' => [
                    ['label' => $typeLabels[0], 'value' => TicketType::Bug],
                    ['label' => $typeLabels[1], 'value' => TicketType::Feature],
                    ['label' => $typeLabels[2], 'value' => TicketType::Suggestion],
                    ['label' => $typeLabels[3], 'value' => TicketType::Other],
            ],
            'severities' => [
                    ['label' => $severityLabels[0], 'value' => TicketSeverity::Low],
                    ['label' => $severityLabels[1], 'value' => TicketSeverity::Medium],
                    ['label' => $severityLabels[2], 'value' => TicketSeverity::High],
                    ['label' => $severityLabels[3], 'value' => TicketSeverity::Critical],
            ],
            'statuses' => [
                    ['label' => $statusLabels[0], 'value' => TicketStatus::Pending],
                    ['label' => $statusLabels[1], 'value' => TicketStatus::InProgress],
                    ['label' => $statusLabels[2], 'value' => TicketStatus::Resolved],
                    ['label' => $statusLabels[3], 'value' => TicketStatus::Closed],
            ],
        ]);
    }

    private function install(): void
    {
        $payload = Request::jsonBody();

        $host = $this->sanitizeSingleLine((string)($payload['host'] ?? ''), 128);
        $port = (int)($payload['port'] ?? 3306);
        $database = $this->sanitizeSingleLine((string)($payload['database'] ?? ''), 64);
        $username = $this->sanitizeSingleLine((string)($payload['username'] ?? ''), 64);
        $password = $this->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);
        $adminPassword = $this->sanitizeSingleLine((string)($payload['adminPassword'] ?? ''), 128);
        $uploadMode = strtolower($this->sanitizeSingleLine((string)($payload['uploadMode'] ?? 'off'), 16));
        $qiniuAccessKey = $this->sanitizeSingleLine((string)($payload['qiniuAccessKey'] ?? ''), 128);
        $qiniuSecretKey = $this->sanitizeSingleLine((string)($payload['qiniuSecretKey'] ?? ''), 128);
        $qiniuBucket = $this->sanitizeSingleLine((string)($payload['qiniuBucket'] ?? ''), 128);
        $qiniuDomain = $this->sanitizeSingleLine((string)($payload['qiniuDomain'] ?? ''), 255);

        if ($host === '' || $database === '' || $username === '') {
            Responder::error('INVALID_INSTALL_PAYLOAD', '请完整填写数据库连接信息。', 422);
        }

        if (!in_array($uploadMode, ['off', 'local', 'qiniu'], true)) {
            Responder::error('INVALID_UPLOAD_MODE', '上传模式不合法。', 422);
        }

        if ($uploadMode === 'qiniu' && ($qiniuAccessKey === '' || $qiniuSecretKey === '' || $qiniuBucket === '' || $qiniuDomain === '')) {
            Responder::error('MISSING_QINIU_CONFIG', '七牛云模式下请完整填写 AK/SK/Bucket/域名。', 422);
        }

        $pdo = Database::createPdo($host, $port, $database, $username, $password);
        $repo = new TicketRepository($pdo);
        $repo->createTableIfNotExists();

        $databaseConfig = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'admin_password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
            'upload_mode' => $uploadMode,
            'qiniu_access_key' => $qiniuAccessKey,
            'qiniu_secret_key' => $qiniuSecretKey,
            'qiniu_bucket' => $qiniuBucket,
            'qiniu_domain' => $qiniuDomain,
        ];

        Database::writeConfig($this->databaseConfigPath, $databaseConfig);

        Responder::send([
            'ok' => true,
            'message' => '安装成功。',
        ]);
    }

    private function submitFeedback(): void
    {
        $payload = Request::isMultipartFormData() ? Request::formBody() : Request::jsonBody();

        $type = TicketType::tryFrom((int)($payload['type'] ?? -1));
        $severity = TicketSeverity::tryFrom((int)($payload['severity'] ?? -1));
        $title = $this->sanitizeSingleLine((string)($payload['title'] ?? ''), 120);
        $description = $this->sanitizeText((string)($payload['description'] ?? ''), 3000);
        $contact = $this->sanitizeSingleLine((string)($payload['contact'] ?? ''), 120);

        if ($type === null || $severity === null || $title === '' || $description === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', '反馈类型、严重程度、标题、详细介绍均为必填。', 422);
        }

        $attachmentMeta = $this->handleAttachmentUpload(Request::uploadedFile('attachment'));

        $details = $description;

        $repo = $this->createTicketRepository();
            $existingTicketNo = $repo->findDuplicateTicketNo($type, $title, $details);

        if ($existingTicketNo !== false) {
            Responder::send([
                'ok' => false,
                'code' => 'DUPLICATE_TICKET',
                'message' => '已有相同反馈，请勿重复提交。',
                'ticketNo' => $existingTicketNo,
            ], 409);
        }

        $ticketNo = $repo->generateTicketNo();
        $now = date('Y-m-d H:i:s');

        $repo->insertTicket([
            ':ticket_no' => $ticketNo,
                ':type' => $type,
                ':severity' => $severity,
            ':title' => $title,
            ':details' => $details,
            ':contact' => $contact,
            ':attachment_name' => $attachmentMeta['name'],
            ':attachment_storage' => $attachmentMeta['storage'],
            ':attachment_key' => $attachmentMeta['key'],
            ':attachment_mime' => $attachmentMeta['mime'],
            ':attachment_size' => $attachmentMeta['size'],
            ':status' => TicketStatus::Pending,
            ':admin_note' => null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        Responder::send([
            'ok' => true,
            'message' => '提交成功。',
            'ticketNo' => $ticketNo,
        ], 201);
    }

    private function queryTicket(): void
    {
        $ticketNo = $this->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '') {
            Responder::error('MISSING_TICKET_NO', '请提供工单号。', 422);
        }

        if (!$this->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);

        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        unset(
            $ticket['attachment_name'],
            $ticket['attachment_storage'],
            $ticket['attachment_key'],
            $ticket['attachment_mime'],
            $ticket['attachment_size']
        );

        Responder::send([
            'ok' => true,
            'ticket' => $ticket,
        ]);
    }

    private function adminLogin(): void
    {
        $payload = Request::jsonBody();
        $password = $this->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);

        $hash = (string)($this->dbConfig['admin_password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            Responder::error('INVALID_ADMIN_PASSWORD', '管理员密码错误。', 401);
        }

        Responder::send([
            'ok' => true,
            'token' => AdminToken::create($hash),
        ]);
    }

    private function adminList(): void
    {
        $this->ensureAdmin();

        $statusRaw = Request::query('status');
        $statusEnum = $statusRaw !== '' ? TicketStatus::tryFrom((int)$statusRaw) : null;

        $typeRaw = Request::query('type');
        $typeEnum = $typeRaw !== '' ? TicketType::tryFrom((int)$typeRaw) : null;

        $keyword = $this->sanitizeSingleLine(Request::query('keyword'), 120);

        $page = $this->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->parseInt(Request::query('pageSize', '20'), 5, 100);

        $repo = $this->createTicketRepository();
        $result = $repo->listTickets(
            $statusEnum,
            $typeEnum,
            $keyword,
            $page,
            $pageSize
        );

        Responder::send([
            'ok' => true,
            'tickets' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => max(1, (int)ceil($result['total'] / $pageSize)),
            ],
        ]);
    }

    private function searchTickets(): void
    {
        $keyword = $this->sanitizeSingleLine(Request::query('keyword'), 120);
        if ($keyword === '') {
            Responder::error('MISSING_KEYWORD', '请输入标题或内容关键词。', 422);
        }

        $page = $this->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->parseInt(Request::query('pageSize', '10'), 5, 50);

        $repo = $this->createTicketRepository();
        $result = $repo->searchPublicSolvedTickets($keyword, $page, $pageSize);

        Responder::send([
            'ok' => true,
            'tickets' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => max(1, (int)ceil($result['total'] / $pageSize)),
            ],
        ]);
    }

    private function adminDetail(): void
    {
        $this->ensureAdmin();

        $ticketNo = $this->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '') {
            Responder::error('MISSING_TICKET_NO', '请提供工单号。', 422);
        }

        if (!$this->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);

        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        Responder::send([
            'ok' => true,
            'ticket' => $ticket,
        ]);
    }

    private function adminUpdate(): void
    {
        $this->ensureAdmin();

        $payload = Request::jsonBody();
        $ticketNo = $this->sanitizeSingleLine((string)($payload['ticketNo'] ?? ''), 32);
        $status = TicketStatus::tryFrom((int)($payload['status'] ?? -1));
        $severity = array_key_exists('severity', $payload)
            ? TicketSeverity::tryFrom((int)$payload['severity'])
            : null;
        $adminNote = $this->sanitizeText((string)($payload['adminNote'] ?? ''), 2000);

        if ($ticketNo === '' || $status === null) {
            Responder::error('MISSING_REQUIRED_FIELDS', '工单号和状态不能为空。', 422);
        }

        if (!$this->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $safeSeverity = null;
        if ((int)($ticket['type'] ?? -1) === TicketType::Bug) {
            if ($severity === null) {
                Responder::error('INVALID_SEVERITY', 'BUG 工单的严重程度不合法。', 422);
            }
                $safeSeverity = $severity;
        }

        $repo->updateTicket(
            $ticketNo,
                $status,
            $safeSeverity,
            $adminNote !== '' ? $adminNote : null,
            date('Y-m-d H:i:s')
        );

        Responder::send([
            'ok' => true,
            'message' => '更新成功。',
        ]);
    }

    private function adminAttachmentDownload(): void
    {
        $this->ensureAdmin();

        $ticketNo = $this->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '' || !$this->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $attachmentKey = (string)($ticket['attachment_key'] ?? '');
        $attachmentStorage = (string)($ticket['attachment_storage'] ?? '');
        $attachmentName = (string)($ticket['attachment_name'] ?? '');
        $attachmentMime = (string)($ticket['attachment_mime'] ?? 'application/octet-stream');

        if ($attachmentKey === '' || $attachmentStorage === '' || $attachmentName === '') {
            Responder::error('ATTACHMENT_NOT_FOUND', '该工单没有附件。', 404);
        }

        if ($attachmentStorage === 'local') {
            $absolutePath = dirname(__DIR__, 2) . '/storage/uploads/' . ltrim($attachmentKey, '/');
            if (!is_file($absolutePath)) {
                Responder::error('ATTACHMENT_NOT_FOUND', '附件文件不存在。', 404);
            }

            header('Content-Type: ' . $attachmentMime);
            header('Content-Disposition: attachment; filename="' . rawurlencode($attachmentName) . '"; filename*=UTF-8\'\'' . rawurlencode($attachmentName));
            header('Content-Length: ' . (string)filesize($absolutePath));
            readfile($absolutePath);
            exit;
        }

        if ($attachmentStorage === 'qiniu') {
            $qiniuUrl = $this->buildQiniuPublicUrl($attachmentKey);
            if ($qiniuUrl === '') {
                Responder::error('QINIU_CONFIG_MISSING', '七牛云下载地址未配置。', 500);
            }

            if (!function_exists('curl_init')) {
                Responder::error('QINIU_DOWNLOAD_FAILED', '当前环境未启用 cURL，无法下载七牛云附件。', 500);
            }

            $ch = curl_init($qiniuUrl);
            if ($ch === false) {
                Responder::error('QINIU_DOWNLOAD_FAILED', '初始化七牛云下载失败。', 500);
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $content = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($content) || $httpCode < 200 || $httpCode >= 300) {
                Responder::error('QINIU_DOWNLOAD_FAILED', '七牛云附件下载失败。', 500);
            }

            header('Content-Type: ' . $attachmentMime);
            header('Content-Disposition: attachment; filename="' . rawurlencode($attachmentName) . '"; filename*=UTF-8\'\'' . rawurlencode($attachmentName));
            header('Content-Length: ' . (string)strlen($content));
            echo $content;
            exit;
        }

        Responder::error('UNSUPPORTED_STORAGE', '不支持的附件存储方式。', 500);
    }

    private function handleAttachmentUpload(?array $file): array
    {
        $mode = (string)($this->dbConfig['upload_mode'] ?? 'off');
        if (!in_array($mode, ['off', 'local', 'qiniu'], true)) {
            $mode = 'off';
        }

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [
                'name' => null,
                'storage' => null,
                'key' => null,
                'mime' => null,
                'size' => null,
            ];
        }

        if ($mode === 'off') {
            Responder::error('UPLOAD_DISABLED', '当前系统已关闭附件上传。', 422);
        }

        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($errorCode !== UPLOAD_ERR_OK) {
            Responder::error('UPLOAD_FAILED', '附件上传失败，请重试。', 422);
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $originalName = $this->sanitizeSingleLine((string)($file['name'] ?? ''), 255);
        $size = (int)($file['size'] ?? 0);
        if ($tmpName === '' || $originalName === '' || $size <= 0) {
            Responder::error('INVALID_UPLOAD_FILE', '附件文件不合法。', 422);
        }

        $maxBytes = 5 * 1024 * 1024;
        if ($size > $maxBytes) {
            Responder::error('UPLOAD_TOO_LARGE', '附件大小不能超过 5MB。', 422);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedByExt = ['zip', 'png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedByExt, true)) {
            Responder::error('UPLOAD_FILE_TYPE_NOT_ALLOWED', '仅支持 zip、png、jpg 格式。', 422);
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $detectedMime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = [
            'application/zip',
            'application/x-zip-compressed',
            'multipart/x-zip',
            'image/png',
            'image/jpeg',
        ];
        if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimes, true)) {
            Responder::error('UPLOAD_FILE_TYPE_NOT_ALLOWED', '仅支持 zip、png、jpg 格式。', 422);
        }

        $mime = $detectedMime !== '' ? $detectedMime : ($extension === 'png' ? 'image/png' : ($extension === 'zip' ? 'application/zip' : 'image/jpeg'));

        if ($mode === 'local') {
            $stored = $this->storeLocalAttachment($tmpName, $extension);
            return [
                'name' => $originalName,
                'storage' => 'local',
                'key' => $stored,
                'mime' => $mime,
                'size' => $size,
            ];
        }

        if ($mode === 'qiniu') {
            $stored = $this->storeQiniuAttachment($tmpName, $extension, $mime);
            return [
                'name' => $originalName,
                'storage' => 'qiniu',
                'key' => $stored,
                'mime' => $mime,
                'size' => $size,
            ];
        }

        Responder::error('UNSUPPORTED_STORAGE', '不支持的附件存储方式。', 500);
    }

    private function storeLocalAttachment(string $tmpName, string $extension): string
    {
        $datePath = date('Y/m');
        $root = dirname(__DIR__, 2) . '/storage/uploads/' . $datePath;
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            Responder::error('UPLOAD_STORAGE_FAILED', '无法创建本地附件目录。', 500);
        }

        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $root . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            Responder::error('UPLOAD_STORAGE_FAILED', '保存本地附件失败。', 500);
        }

        return $datePath . '/' . $fileName;
    }

    private function storeQiniuAttachment(string $tmpName, string $extension, string $mime): string
    {
        $accessKey = trim((string)($this->dbConfig['qiniu_access_key'] ?? ''));
        $secretKey = trim((string)($this->dbConfig['qiniu_secret_key'] ?? ''));
        $bucket = trim((string)($this->dbConfig['qiniu_bucket'] ?? ''));

        if ($accessKey === '' || $secretKey === '' || $bucket === '') {
            Responder::error('QINIU_CONFIG_MISSING', '七牛云配置不完整。', 500);
        }

        $key = 'feedback/' . date('Y/m') . '/' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $deadline = time() + 3600;
        $uploadHost = trim((string)($this->dbConfig['qiniu_upload_host'] ?? 'https://up.qiniup.com'));
        if ($uploadHost === '') {
            $uploadHost = 'https://up.qiniup.com';
        }

        $scopes = [$bucket . ':' . $key, $bucket];
        $lastHttpCode = 0;
        $lastError = '';

        foreach ($scopes as $scope) {
            $uploadToken = $this->buildQiniuUploadToken($accessKey, $secretKey, $scope, $deadline);
            $result = $this->postQiniuUpload($uploadHost, $uploadToken, $key, $tmpName, $mime);

            if ($result['curlError'] !== '') {
                Responder::error('QINIU_UPLOAD_FAILED', 'cURL 错误 (' . $result['curlErrno'] . ')：' . $result['curlError'], 500);
            }

            if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
                return $key;
            }

            $lastHttpCode = (int)$result['httpCode'];
            $responseData = @json_decode((string)$result['response'], true);
            if (is_array($responseData) && isset($responseData['error'])) {
                $lastError = (string)$responseData['error'];
            } else {
                $lastError = (string)$result['response'];
            }

            // bad token usually means policy/signature mismatch; retry with alternate scope.
            if (strpos($lastError, 'bad token') === false) {
                break;
            }
        }

        $diagnostic = ' [deadline=' . (string)$deadline
            . ', now=' . (string)time()
            . ', ak_tail=' . substr($accessKey, -6)
            . ', host=' . $uploadHost
            . ', scope_try=' . implode('|', $scopes)
            . ']';

        Responder::error('QINIU_UPLOAD_FAILED', '七牛云错误 (HTTP ' . $lastHttpCode . ')：' . $lastError . $diagnostic, 500);

        return $key;
    }

    private function buildQiniuUploadToken(string $accessKey, string $secretKey, string $scope, int $deadline): string
    {
        $policy = [
            'scope' => $scope,
            'deadline' => $deadline,
        ];

        $policyJson = json_encode($policy, JSON_UNESCAPED_SLASHES);
        if (!is_string($policyJson)) {
            Responder::error('QINIU_UPLOAD_FAILED', '七牛云上传策略生成失败。', 500);
        }

        $encodedPolicy = $this->base64UrlEncode($policyJson);
        $sign = hash_hmac('sha1', $encodedPolicy, $secretKey, true);
        $encodedSign = $this->base64UrlEncode($sign);

        return $accessKey . ':' . $encodedSign . ':' . $encodedPolicy;
    }

    /** @return array{httpCode:int,response:string,curlError:string,curlErrno:int} */
    private function postQiniuUpload(string $uploadHost, string $uploadToken, string $key, string $tmpName, string $mime): array
    {
        if (!function_exists('curl_init')) {
            Responder::error('QINIU_UPLOAD_FAILED', '当前环境未启用 cURL，无法上传到七牛云。', 500);
        }

        $ch = curl_init($uploadHost);
        if ($ch === false) {
            Responder::error('QINIU_UPLOAD_FAILED', '初始化七牛云上传失败。', 500);
        }

        $postFields = [
            'token' => $uploadToken,
            'key' => $key,
            'file' => new CURLFile($tmpName, $mime, basename($key)),
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        return [
            'httpCode' => $httpCode,
            'response' => is_string($response) ? $response : '',
            'curlError' => $curlError,
            'curlErrno' => $curlErrno,
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    private function buildQiniuPublicUrl(string $key): string
    {
        $domain = trim((string)($this->dbConfig['qiniu_domain'] ?? ''));
        if ($domain === '') {
            return '';
        }

        if (strpos($domain, 'http://') !== 0 && strpos($domain, 'https://') !== 0) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/') . '/' . ltrim($key, '/');
    }

    private function ensureAdmin(): void
    {
        $authHeader = Request::authorizationHeader();
        if (strpos($authHeader, 'Bearer ') !== 0) {
            Responder::error('UNAUTHORIZED', '缺少管理员身份信息。', 401);
        }

        $token = trim(substr($authHeader, 7));
        $hash = (string)($this->dbConfig['admin_password_hash'] ?? '');
        if (!AdminToken::verify($token, $hash)) {
            Responder::error('UNAUTHORIZED', '管理员身份验证失败。', 401);
        }
    }

    private function createTicketRepository(): TicketRepository
    {
        $repo = new TicketRepository(Database::createConfiguredPdo($this->dbConfig));
        $repo->createTableIfNotExists();

        return $repo;
    }

    private function sanitizeSingleLine(string $value, int $maxLength): string
    {
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($this->stringLength($clean) > $maxLength) {
            Responder::error('PAYLOAD_TOO_LARGE', '输入内容超出长度限制。', 422);
        }

        return $clean;
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? '';
        $clean = trim($clean);

        if ($this->stringLength($clean) > $maxLength) {
            Responder::error('PAYLOAD_TOO_LARGE', '输入内容超出长度限制。', 422);
        }

        return $clean;
    }

    private function parseInt(string $value, int $min, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false) {
            return $min;
        }

        $number = (int)$number;
        if ($number < $min) {
            return $min;
        }

        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    private function isValidTicketNo(string $ticketNo): bool
    {
        return preg_match('/^FB\d{8}[A-F0-9]{6}$/', $ticketNo) === 1;
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
