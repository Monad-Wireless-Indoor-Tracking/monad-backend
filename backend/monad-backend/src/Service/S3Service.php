<?php

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\SystemException;
use App\Exception\ValidationException;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

class S3Service
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB
    private const ALLOWED_CONTENT_TYPES = [
        'application/octet-stream',
        'application/json',
        'text/csv',
        'text/plain',
        'text/tab-separated-values',
    ];

    private S3Client $s3Client;
    private string $bucket;
    private string $region;
    private string $endpoint;
    private string $presignedUrlExpiry;

    /**
     * The store is Hetzner Object Storage, not AWS.
     *
     * It speaks the S3 API, so the SDK is unchanged, but two settings are mandatory: an explicit
     * endpoint, and path-style addressing (`https://<endpoint>/<bucket>/<key>` rather than
     * `https://<bucket>.<endpoint>/<key>`) — virtual-hosted addressing needs a wildcard TLS
     * certificate the provider does not issue.
     *
     * Sharing the project's bucket is the point: phone sessions then land in the same tenancy as
     * the `csid` fleet captures and the simulation artefacts, so one set of credentials and one
     * lifecycle policy covers every kind of measurement this project produces.
     */
    public function __construct(
        string $awsRegion,
        string $awsBucket,
        string $awsAccessKeyId,
        string $awsSecretAccessKey,
        string $presignedUrlExpiry,
        string $endpoint = '',
        bool $usePathStyle = true,
    ) {
        $this->bucket = $awsBucket;
        $this->region = $awsRegion;
        $this->endpoint = $endpoint;
        $this->presignedUrlExpiry = $presignedUrlExpiry;

        $config = [
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => [
                'key' => $awsAccessKeyId,
                'secret' => $awsSecretAccessKey,
            ],
        ];

        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = $usePathStyle;
        }

        $this->s3Client = new S3Client($config);
    }

    /**
     * Public URL for an object key. AWS-shaped URLs are only correct on AWS; with a custom
     * endpoint the address is endpoint + bucket + key.
     */
    private function objectUrl(string $objectKey): string
    {
        if ($this->endpoint !== '') {
            return sprintf('%s/%s/%s', rtrim($this->endpoint, '/'), $this->bucket, $objectKey);
        }

        return sprintf('https://%s.s3.%s.amazonaws.com/%s', $this->bucket, $this->region, $objectKey);
    }

    /**
     * Test S3 connection by checking if bucket exists and is accessible
     *
     * @return array{success: bool, bucket: string, region: string, message: string}
     */
    public function testConnection(): array
    {
        try {
            $this->s3Client->headBucket([
                'Bucket' => $this->bucket,
            ]);

            return [
                'success' => true,
                'bucket' => $this->bucket,
                'region' => $this->region,
                'message' => 'Successfully connected to S3 bucket',
            ];
        } catch (AwsException $e) {
            $errorCode = $e->getAwsErrorCode();
            $message = match ($errorCode) {
                'NoSuchBucket' => 'Bucket does not exist',
                'AccessDenied', 'Forbidden' => 'Access denied - check IAM permissions',
                'InvalidAccessKeyId' => 'Invalid AWS Access Key ID',
                'SignatureDoesNotMatch' => 'Invalid AWS Secret Access Key',
                default => $e->getAwsErrorMessage() ?? $e->getMessage(),
            };

            return [
                'success' => false,
                'bucket' => $this->bucket,
                'region' => $this->region,
                'message' => $message,
                'errorCode' => $errorCode,
            ];
        }
    }

    /**
     * Stream upload a file directly to S3 without storing locally
     *
     * @param UploadedFile $file The uploaded file
     * @param string $userId User ID for organizing uploads
     * @return array{success: bool, objectKey: string, url: string, size: int}
     */
    public function streamUpload(UploadedFile $file, string $userId): array
    {
        $filename = $file->getClientOriginalName();
        $contentType = $file->getMimeType() ?? 'application/octet-stream';
        $fileSize = $file->getSize();

        $this->validateUploadRequest($filename, $contentType, $fileSize);

        // Generate unique object key
        $objectKey = sprintf(
            'uploads/%s/%s/%s',
            $userId,
            Uuid::v4()->toRfc4122(),
            $this->sanitizeFilename($filename)
        );

        try {
            // Open file stream - this avoids loading entire file into memory
            $stream = fopen($file->getPathname(), 'rb');

            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Body' => $stream,
                'ContentType' => $contentType,
                'ContentLength' => $fileSize,
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return [
                'success' => true,
                'objectKey' => $objectKey,
                'url' => $result['ObjectURL'] ?? $this->objectUrl($objectKey),
                'size' => $fileSize,
                'contentType' => $contentType,
            ];
        } catch (AwsException $e) {
            throw new SystemException(
                ErrorCode::STORAGE_UPLOAD_FAILED,
                previous: $e
            );
        }
    }

    /**
     * Generate a pre-signed URL for uploading a file directly to S3
     *
     * @param string $filename Original filename
     * @param string $contentType MIME type of the file
     * @param int $fileSize Expected file size in bytes
     * @param string $userId User ID for organizing uploads
     * @return array{uploadUrl: string, objectKey: string, expiresAt: string}
     */
    public function generateUploadUrl(
        string $filename,
        string $contentType,
        int $fileSize,
        string $userId,
    ): array {
        $this->validateUploadRequest($filename, $contentType, $fileSize);

        // Generate unique object key: uploads/{userId}/{uuid}/{original_filename}
        $objectKey = sprintf(
            'uploads/%s/%s/%s',
            $userId,
            Uuid::v4()->toRfc4122(),
            $this->sanitizeFilename($filename)
        );

        try {
            $cmd = $this->s3Client->getCommand('PutObject', [
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'ContentType' => $contentType,
                'ContentLength' => $fileSize,
            ]);

            $presignedRequest = $this->s3Client->createPresignedRequest(
                $cmd,
                $this->presignedUrlExpiry
            );

            $expiresAt = new \DateTimeImmutable($this->presignedUrlExpiry);

            return [
                'uploadUrl' => (string) $presignedRequest->getUri(),
                'objectKey' => $objectKey,
                'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
            ];
        } catch (\Exception $e) {
            throw new SystemException(
                ErrorCode::STORAGE_S3_UNAVAILABLE,
                previous: $e
            );
        }
    }

    /**
     * Pre-signed PUT URL for one lab-session artefact.
     *
     * Same key layout as {@see directSessionStreamUpload}; used when a client would rather push
     * bytes straight at the object store than proxy them through this API.
     *
     * @return array{uploadUrl: string, objectKey: string, expiresAt: string}
     */
    public function generateSessionUploadUrl(
        string $filename,
        string $contentType,
        int $fileSize,
        string $participantId,
        string $sessionId,
    ): array {
        $this->validateUploadRequest($filename, $contentType, $fileSize);

        $objectKey = sprintf(
            'datasets/monad-app-sessions/%s/%s/%s',
            $this->sanitizeIdentifier($participantId),
            $this->sanitizeIdentifier($sessionId),
            $this->sanitizeFilename($filename)
        );

        try {
            $cmd = $this->s3Client->getCommand('PutObject', [
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'ContentType' => $contentType,
                'ContentLength' => $fileSize,
            ]);

            $presignedRequest = $this->s3Client->createPresignedRequest(
                $cmd,
                $this->presignedUrlExpiry
            );

            return [
                'uploadUrl' => (string) $presignedRequest->getUri(),
                'objectKey' => $objectKey,
                'expiresAt' => (new \DateTimeImmutable($this->presignedUrlExpiry))->format(\DateTimeInterface::ATOM),
            ];
        } catch (AwsException $e) {
            throw new SystemException(
                ErrorCode::STORAGE_UPLOAD_FAILED,
                previous: $e
            );
        }
    }

    /**
     * Validate the upload request parameters
     */
    private function validateUploadRequest(
        string $filename,
        string $contentType,
        int $fileSize,
    ): void {
        if (empty(trim($filename))) {
            throw new ValidationException(ErrorCode::STORAGE_FILENAME_REQUIRED);
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new ValidationException(ErrorCode::STORAGE_FILE_TOO_LARGE);
        }

        if (!in_array($contentType, self::ALLOWED_CONTENT_TYPES, true)) {
            throw new ValidationException(ErrorCode::STORAGE_INVALID_FILE_TYPE);
        }
    }

    /**
     * Sanitize filename to prevent path traversal and other issues
     */
    /**
     * Path-safe form of a participant or session identifier.
     *
     * These arrive from a client and become object-key path segments, so anything that could
     * traverse (`..`, `/`) or collide must go. Restricting to `[A-Za-z0-9._-]` keeps UUIDs and
     * pseudonymous participant keys intact while making traversal impossible by construction.
     */
    private function sanitizeIdentifier(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?? '';
        $clean = trim($clean, '.');

        return $clean === '' ? 'unknown' : substr($clean, 0, 128);
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove any directory components
        $filename = basename($filename);

        // Replace any potentially problematic characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Ensure filename is not empty after sanitization
        if (empty($filename)) {
            $filename = 'file';
        }

        return $filename;
    }

    /**
     * Stream upload directly from php://input to S3 (no temp file)
     *
     * Client must send raw binary body (not multipart/form-data)
     *
     * @param string $filename Filename from header
     * @param string $contentType Content-Type from header
     * @param int $contentLength Content-Length from header
     * @param string $userId User ID for organizing uploads
     * @return array{success: bool, objectKey: string, url: string, size: int}
     */
    public function directStreamUpload(
        string $filename,
        string $contentType,
        int $contentLength,
        string $userId,
    ): array {
        $this->validateUploadRequest($filename, $contentType, $contentLength);

        // Generate unique object key
        $objectKey = sprintf(
            'uploads/%s/%s/%s',
            $userId,
            Uuid::v4()->toRfc4122(),
            $this->sanitizeFilename($filename)
        );

        try {
            // php://input can only be consumed once. When the caller already read it (to inspect a
            // sidecar), forward that string; otherwise stream straight through with no temp file.
            $inputStream = $body === null ? fopen('php://input', 'rb') : null;

            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Body' => $body ?? $inputStream,
                'ContentType' => $contentType,
                'ContentLength' => $body === null ? $contentLength : strlen($body),
            ]);

            if (is_resource($inputStream)) {
                fclose($inputStream);
            }

            return [
                'success' => true,
                'objectKey' => $objectKey,
                'url' => $result['ObjectURL'] ?? $this->objectUrl($objectKey),
                'size' => $contentLength,
                'contentType' => $contentType,
            ];
        } catch (AwsException $e) {
            throw new SystemException(
                ErrorCode::STORAGE_UPLOAD_FAILED,
                previous: $e
            );
        }
    }

    /**
     * Stream one lab-session artefact from php://input straight to S3 (no temp file).
     *
     * Key layout: `datasets/monad-app-sessions/{participantId}/{sessionId}/{filename}`.
     *
     * This replaces the previous `experiments/{y}/{m}/{d}/{userId}/{enrollmentId}/` layout, which
     * partitioned by *upload date*. That made a session's artefacts land in different prefixes
     * whenever an upload was retried across midnight, and it could not be joined to a `csid`
     * capture, which is addressed by session rather than by date. The prefix now mirrors the
     * fleet's own convention so a phone session and a radio capture are siblings in one bucket.
     *
     * @return array{success: bool, objectKey: string, url: string, size: int, contentType: string}
     */
    public function directSessionStreamUpload(
        string $filename,
        string $contentType,
        int $contentLength,
        string $participantId,
        string $sessionId,
        ?string $body = null,
    ): array {
        $this->validateUploadRequest($filename, $contentType, $contentLength);

        $objectKey = sprintf(
            'datasets/monad-app-sessions/%s/%s/%s',
            $this->sanitizeIdentifier($participantId),
            $this->sanitizeIdentifier($sessionId),
            $this->sanitizeFilename($filename)
        );

        try {
            // php://input can only be consumed once. When the caller already read it (to inspect a
            // sidecar), forward that string; otherwise stream straight through with no temp file.
            $inputStream = $body === null ? fopen('php://input', 'rb') : null;

            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Body' => $body ?? $inputStream,
                'ContentType' => $contentType,
                'ContentLength' => $body === null ? $contentLength : strlen($body),
            ]);

            if (is_resource($inputStream)) {
                fclose($inputStream);
            }

            return [
                'success' => true,
                'objectKey' => $objectKey,
                'url' => $result['ObjectURL'] ?? $this->objectUrl($objectKey),
                'size' => $contentLength,
                'contentType' => $contentType,
            ];
        } catch (AwsException $e) {
            throw new SystemException(
                ErrorCode::STORAGE_UPLOAD_FAILED,
                previous: $e
            );
        }
    }

    /**
     * Get the maximum allowed file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get allowed content types
     *
     * @return string[]
     */
    public function getAllowedContentTypes(): array
    {
        return self::ALLOWED_CONTENT_TYPES;
    }
}
