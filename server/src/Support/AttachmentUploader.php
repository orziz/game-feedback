<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use CURLFile;

final class AttachmentUploader
{
    /** @var array<string, mixed> */
    private $dbConfig;

    /**
     * @param array<string, mixed> $dbConfig
     */
    public function __construct(array $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file
     * @return array{name:string|null,storage:string|null,key:string|null,mime:string|null,size:int|null}
     */
    public function handleUpload(?array $file): array
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
            Responder::error('UPLOAD_DISABLED', 'Attachment upload is disabled.', 422);
        }

        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($errorCode !== UPLOAD_ERR_OK) {
            Responder::error('UPLOAD_FAILED', 'Attachment upload failed. Please try again.', 422);
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $originalName = $this->sanitizeSingleLine((string)($file['name'] ?? ''), 255);
        $size = (int)($file['size'] ?? 0);
        if ($tmpName === '' || $originalName === '' || $size <= 0) {
            Responder::error('INVALID_UPLOAD_FILE', 'Invalid uploaded file.', 422);
        }

        $maxBytes = (int)($this->dbConfig['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($maxBytes <= 0) {
            $maxBytes = 5 * 1024 * 1024;
        }
        if ($size > $maxBytes) {
            $maxSizeMb = rtrim(rtrim(number_format($maxBytes / 1024 / 1024, 2, '.', ''), '0'), '.');
            Responder::error('UPLOAD_TOO_LARGE', 'Attachment size must be less than or equal to ' . $maxSizeMb . 'MB.', 422);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedByExt = ['zip', 'png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedByExt, true)) {
            Responder::error('UPLOAD_FILE_TYPE_NOT_ALLOWED', 'Only zip, png, jpg, and jpeg files are allowed.', 422);
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
            Responder::error('UPLOAD_FILE_TYPE_NOT_ALLOWED', 'Only zip, png, jpg, and jpeg files are allowed.', 422);
        }

        $mime = $detectedMime !== ''
            ? $detectedMime
            : ($extension === 'png'
                ? 'image/png'
                : ($extension === 'zip' ? 'application/zip' : 'image/jpeg'));

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

        Responder::error('UNSUPPORTED_STORAGE', 'Unsupported attachment storage mode.', 500);
    }

    private function storeLocalAttachment(string $tmpName, string $extension): string
    {
        $datePath = date('Y/m');
        $root = dirname(__DIR__, 2) . '/storage/uploads/' . $datePath;
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            Responder::error('UPLOAD_STORAGE_FAILED', 'Failed to create the local upload directory.', 500);
        }

        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $root . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            Responder::error('UPLOAD_STORAGE_FAILED', 'Failed to store the uploaded file locally.', 500);
        }

        return $datePath . '/' . $fileName;
    }

    private function storeQiniuAttachment(string $tmpName, string $extension, string $mime): string
    {
        $accessKey = trim((string)($this->dbConfig['qiniu_access_key'] ?? ''));
        $secretKey = trim((string)($this->dbConfig['qiniu_secret_key'] ?? ''));
        $bucket = trim((string)($this->dbConfig['qiniu_bucket'] ?? ''));

        if ($accessKey === '' || $secretKey === '' || $bucket === '') {
            Responder::error('QINIU_CONFIG_MISSING', 'Qiniu configuration is incomplete.', 500);
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
                Responder::error('QINIU_UPLOAD_FAILED', 'cURL error (' . $result['curlErrno'] . '): ' . $result['curlError'], 500);
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

        Responder::error('QINIU_UPLOAD_FAILED', 'Qiniu upload failed (HTTP ' . $lastHttpCode . '): ' . $lastError . $diagnostic, 500);

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
            Responder::error('QINIU_UPLOAD_FAILED', 'Failed to build the Qiniu upload policy.', 500);
        }

        $encodedPolicy = $this->base64UrlEncode($policyJson);
        $sign = hash_hmac('sha1', $encodedPolicy, $secretKey, true);
        $encodedSign = $this->base64UrlEncode($sign);

        return $accessKey . ':' . $encodedSign . ':' . $encodedPolicy;
    }

    /**
     * @return array{httpCode:int,response:string,curlError:string,curlErrno:int}
     */
    private function postQiniuUpload(string $uploadHost, string $uploadToken, string $key, string $tmpName, string $mime): array
    {
        if (!function_exists('curl_init')) {
            Responder::error('QINIU_UPLOAD_FAILED', 'cURL is not enabled in the current PHP environment.', 500);
        }

        $ch = curl_init($uploadHost);
        if ($ch === false) {
            Responder::error('QINIU_UPLOAD_FAILED', 'Failed to initialize the Qiniu upload request.', 500);
        }

        $postFields = [
            'token' => $uploadToken,
            'key' => $key,
            'file' => new CURLFile($tmpName, $mime, basename($key)),
        ];

        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        foreach ($this->buildCurlSslOptions() as $option => $value) {
            $options[$option] = $value;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = $this->buildCurlErrorMessage((string)curl_error($ch), $curlErrno);
        curl_close($ch);

        return [
            'httpCode' => $httpCode,
            'response' => is_string($response) ? $response : '',
            'curlError' => $curlError,
            'curlErrno' => $curlErrno,
        ];
    }

    /**
     * @return array<int, bool|int|string>
     */
    private function buildCurlSslOptions(): array
    {
        $verifySsl = $this->configFlag('curl_verify_ssl', true);
        if (!$verifySsl) {
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }

        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caFile = $this->resolveConfigPath('curl_ca_file');
        if ($caFile !== '') {
            if (!is_file($caFile) || !is_readable($caFile)) {
                Responder::error('QINIU_SSL_CONFIG_INVALID', 'CA file is not readable: ' . $caFile, 500);
            }

            if (defined('CURLOPT_CAINFO')) {
                $options[CURLOPT_CAINFO] = $caFile;
            }
        }

        $caPath = $this->resolveConfigPath('curl_ca_path');
        if ($caPath !== '') {
            if (!is_dir($caPath) || !is_readable($caPath)) {
                Responder::error('QINIU_SSL_CONFIG_INVALID', 'CA directory is not readable: ' . $caPath, 500);
            }

            if (defined('CURLOPT_CAPATH')) {
                $options[CURLOPT_CAPATH] = $caPath;
            }
        }

        if ($this->configFlag('curl_use_native_ca', true)
            && defined('CURLOPT_SSL_OPTIONS')
            && defined('CURLSSLOPT_NATIVE_CA')
        ) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }

        return $options;
    }

    private function buildCurlErrorMessage(string $curlError, int $curlErrno): string
    {
        if ($curlErrno !== 60) {
            return $curlError;
        }

        $tips = [
            'SSL certificate validation failed. Configure a CA bundle for PHP/cURL.',
        ];

        if ($this->configFlag('curl_use_native_ca', true) && defined('CURLSSLOPT_NATIVE_CA')) {
            $tips[] = 'Tried using the native OS CA store.';
        }

        if ($this->resolveConfigPath('curl_ca_file') === '' && $this->resolveConfigPath('curl_ca_path') === '') {
            $tips[] = 'Set curl_ca_file or curl_ca_path in server/config/database.php.';
        }

        $tips[] = 'On Windows PHP, you usually need a CA bundle or native root certificates.';

        if ($curlError !== '') {
            $tips[] = 'cURL: ' . $curlError;
        }

        return implode(' ', $tips);
    }

    private function configFlag(string $key, bool $default): bool
    {
        if (!array_key_exists($key, $this->dbConfig)) {
            return $default;
        }

        $value = $this->dbConfig[$key];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function resolveConfigPath(string $key): string
    {
        $value = trim((string)($this->dbConfig[$key] ?? ''));
        if ($value === '') {
            return '';
        }

        if ($this->isAbsolutePath($value)) {
            return $value;
        }

        return dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $value), '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    private function sanitizeSingleLine(string $value, int $maxLength): string
    {
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($this->stringLength($clean) > $maxLength) {
            Responder::error('PAYLOAD_TOO_LARGE', 'Input exceeds the maximum allowed length.', 422);
        }

        return $clean;
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
