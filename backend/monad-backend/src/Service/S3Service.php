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
    ];

    private S3Client $s3Client;
    private string $bucket;
    private string $region;
    private string $presignedUrlExpiry;

    public function __construct(
        string $awsRegion,
        string $awsBucket,
        string $awsAccessKeyId,
        string $awsSecretAccessKey,
        string $presignedUrlExpiry,
    ) {
        $this->bucket = $awsBucket;
        $this->region = $awsRegion;
        $this->presignedUrlExpiry = $presignedUrlExpiry;

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => [
                'key' => $awsAccessKeyId,
                'secret' => $awsSecretAccessKey,
            ],
        ]);
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
                'url' => $result['ObjectURL'] ?? sprintf(
                    'https://%s.s3.%s.amazonaws.com/%s',
                    $this->bucket,
                    $this->region,
                    $objectKey
                ),
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
            // Open php://input as a stream - this reads directly from request body
            // No temp file is created!
            $inputStream = fopen('php://input', 'rb');

            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'Body' => $inputStream,
                'ContentType' => $contentType,
                'ContentLength' => $contentLength,
            ]);

            if (is_resource($inputStream)) {
                fclose($inputStream);
            }

            return [
                'success' => true,
                'objectKey' => $objectKey,
                'url' => $result['ObjectURL'] ?? sprintf(
                    'https://%s.s3.%s.amazonaws.com/%s',
                    $this->bucket,
                    $this->region,
                    $objectKey
                ),
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
