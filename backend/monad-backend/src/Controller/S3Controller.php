<?php

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Entity\User;
use App\Exception\AuthException;
use App\Exception\ValidationException;
use App\Service\LabTelemetry;
use App\Service\S3Service;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

class S3Controller extends AbstractController
{
    /** Sidecars are a few kB; anything larger is not a sidecar and is streamed without inspection. */
    private const MAX_INSPECTABLE_SIDECAR = 1024 * 1024;

    public function __construct(
        private S3Service $s3Service,
        private LabTelemetry $telemetry,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/storage/upload-url', name: 'api_storage_upload_url', methods: ['POST'])]
    #[OA\Post(
        path: '/api/storage/upload-url',
        summary: 'Get pre-signed URL for S3 upload',
        description: 'Generates a pre-signed URL that allows direct upload to S3. The client should use this URL to upload the file directly to S3 using a PUT request.',
        security: [['Bearer' => []]],
        tags: ['Storage']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['filename', 'contentType', 'fileSize'],
            properties: [
                new OA\Property(
                    property: 'filename',
                    type: 'string',
                    example: 'ble_data_2024.csv',
                    description: 'Original filename'
                ),
                new OA\Property(
                    property: 'contentType',
                    type: 'string',
                    example: 'text/csv',
                    description: 'MIME type of the file. Allowed: application/octet-stream, application/json, text/csv, text/plain'
                ),
                new OA\Property(
                    property: 'fileSize',
                    type: 'integer',
                    example: 1048576,
                    description: 'File size in bytes (max 50 MB)'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Pre-signed URL generated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'uploadUrl',
                    type: 'string',
                    example: 'https://fsn1.your-objectstorage.com/monad-knowledge/uploads/user-id/uuid/filename.csv?X-Amz-...',
                    description: 'Pre-signed URL for uploading. Use HTTP PUT with the file content.'
                ),
                new OA\Property(
                    property: 'objectKey',
                    type: 'string',
                    example: 'uploads/550e8400-e29b-41d4-a716-446655440000/a1b2c3d4/ble_data.csv',
                    description: 'S3 object key where the file will be stored'
                ),
                new OA\Property(
                    property: 'expiresAt',
                    type: 'string',
                    format: 'date-time',
                    example: '2024-01-15T10:30:00+00:00',
                    description: 'URL expiration timestamp (ISO 8601)'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - validation errors',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_301', description: 'Error code'),
                new OA\Property(property: 'message', type: 'string', example: 'File size exceeds maximum allowed (50 MB)', description: 'Human-readable error message')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'AUTH_006', description: 'Error code'),
                new OA\Property(property: 'message', type: 'string', example: 'Authentication required', description: 'Human-readable error message')
            ]
        )
    )]
    #[OA\Response(
        response: 503,
        description: 'Service unavailable - S3 error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_304', description: 'Error code'),
                new OA\Property(property: 'message', type: 'string', example: 'Storage service is temporarily unavailable', description: 'Human-readable error message')
            ]
        )
    )]
    public function getUploadUrl(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['filename']) || empty(trim($data['filename']))) {
            throw new ValidationException(ErrorCode::STORAGE_FILENAME_REQUIRED);
        }

        if (!isset($data['contentType'])) {
            throw new ValidationException(ErrorCode::STORAGE_INVALID_FILE_TYPE);
        }

        if (!isset($data['fileSize']) || !is_int($data['fileSize']) || $data['fileSize'] <= 0) {
            throw new ValidationException(ErrorCode::STORAGE_FILE_TOO_LARGE);
        }

        $result = $this->s3Service->generateUploadUrl(
            filename: $data['filename'],
            contentType: $data['contentType'],
            fileSize: $data['fileSize'],
            userId: $user->getId()->toRfc4122(),
        );

        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/api/storage/config', name: 'api_storage_config', methods: ['GET'])]
    #[OA\Get(
        path: '/api/storage/config',
        summary: 'Get storage configuration',
        description: 'Returns the storage service configuration including allowed file types and size limits',
        security: [['Bearer' => []]],
        tags: ['Storage']
    )]
    #[OA\Response(
        response: 200,
        description: 'Storage configuration',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'maxFileSize',
                    type: 'integer',
                    example: 52428800,
                    description: 'Maximum file size in bytes'
                ),
                new OA\Property(
                    property: 'maxFileSizeMB',
                    type: 'integer',
                    example: 50,
                    description: 'Maximum file size in megabytes'
                ),
                new OA\Property(
                    property: 'allowedContentTypes',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['application/octet-stream', 'application/json', 'text/csv', 'text/plain'],
                    description: 'List of allowed MIME types'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'AUTH_006', description: 'Error code'),
                new OA\Property(property: 'message', type: 'string', example: 'Authentication required', description: 'Human-readable error message')
            ]
        )
    )]
    public function getConfig(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
        }

        return $this->json([
            'maxFileSize' => $this->s3Service->getMaxFileSize(),
            'maxFileSizeMB' => (int) ($this->s3Service->getMaxFileSize() / 1024 / 1024),
            'allowedContentTypes' => $this->s3Service->getAllowedContentTypes(),
        ]);
    }

    #[Route('/api/storage/upload', name: 'api_storage_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/storage/upload',
        summary: 'Upload file to S3 (direct stream)',
        description: 'Uploads a file directly to S3 by streaming from request body. NO temp file is created on the backend. Send raw binary body with required headers.',
        security: [['Bearer' => []]],
        tags: ['Storage']
    )]
    #[OA\Header(
        header: 'X-Filename',
        description: 'Original filename',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'ble_data.csv')
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Raw binary file content (NOT multipart/form-data)',
        content: new OA\MediaType(
            mediaType: 'application/octet-stream',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'File uploaded successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'objectKey',
                    type: 'string',
                    example: 'uploads/550e8400-e29b-41d4-a716-446655440000/a1b2c3d4/ble_data.csv',
                    description: 'S3 object key where the file was stored'
                ),
                new OA\Property(
                    property: 'url',
                    type: 'string',
                    example: 'https://fsn1.your-objectstorage.com/monad-knowledge/uploads/...',
                    description: 'URL of the uploaded file'
                ),
                new OA\Property(property: 'size', type: 'integer', example: 1048576, description: 'File size in bytes'),
                new OA\Property(property: 'contentType', type: 'string', example: 'application/octet-stream', description: 'MIME type')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - validation errors',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_301'),
                new OA\Property(property: 'message', type: 'string', example: 'File size exceeds maximum allowed (50 MB)')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'AUTH_006'),
                new OA\Property(property: 'message', type: 'string', example: 'Authentication required')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Upload failed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_300'),
                new OA\Property(property: 'message', type: 'string', example: 'File upload failed')
            ]
        )
    )]
    public function upload(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
        }

        // Get metadata from headers
        $filename = $request->headers->get('X-Filename');
        $contentType = $request->headers->get('Content-Type', 'application/octet-stream');
        $contentLength = (int) $request->headers->get('Content-Length', 0);

        if (!$filename) {
            throw new ValidationException(ErrorCode::STORAGE_FILENAME_REQUIRED);
        }

        if ($contentLength <= 0) {
            throw new ValidationException(ErrorCode::STORAGE_FILE_TOO_LARGE);
        }

        // Direct stream from php://input to S3 - no temp file!
        $result = $this->s3Service->directStreamUpload(
            filename: $filename,
            contentType: $contentType,
            contentLength: $contentLength,
            userId: $user->getId()->toRfc4122(),
        );

        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/api/storage/session-upload', name: 'api_storage_session_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/storage/session-upload',
        summary: 'Upload one lab-session artefact to S3 (direct stream)',
        description: 'Streams one artefact of a lab session straight from the request body to object storage; no temp file is created. Artefacts are stored at datasets/monad-app-sessions/{participantId}/{sessionId}/{filename}, the same convention the csid fleet captures use, so a phone session and a radio capture are siblings in one bucket. Upload the streams first and metadata.json last: its presence marks the session complete.',
        security: [['Bearer' => []]],
        tags: ['Storage']
    )]
    #[OA\Header(
        header: 'X-Filename',
        description: 'Original filename',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'ble_data.tsv')
    )]
    #[OA\Header(
        header: 'X-Session-Id',
        description: 'Lab session UUID',
        required: true,
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\Header(
        header: 'X-Participant-Id',
        description: 'Pseudonymous participant key. Never an e-mail: the account belongs to the game, the dataset carries only the pseudonym.',
        required: false,
        schema: new OA\Schema(type: 'string', example: '0198f2c1-1f3f-7c3a-9a1d-2f2b0a5f2f11')
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Raw binary file content (NOT multipart/form-data)',
        content: new OA\MediaType(
            mediaType: 'text/tab-separated-values',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'File uploaded successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'objectKey',
                    type: 'string',
                    example: 'experiments/2024/01/15/550e8400-e29b-41d4-a716-446655440000/a1b2c3d4/ble_data.tsv',
                    description: 'S3 object key where the file was stored'
                ),
                new OA\Property(
                    property: 'url',
                    type: 'string',
                    example: 'https://fsn1.your-objectstorage.com/monad-knowledge/datasets/monad-app-sessions/...',
                    description: 'URL of the uploaded file'
                ),
                new OA\Property(property: 'size', type: 'integer', example: 1048576, description: 'File size in bytes'),
                new OA\Property(property: 'contentType', type: 'string', example: 'text/tab-separated-values', description: 'MIME type')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - validation errors',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_301'),
                new OA\Property(property: 'message', type: 'string', example: 'File size exceeds maximum allowed (50 MB)')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'AUTH_006'),
                new OA\Property(property: 'message', type: 'string', example: 'Authentication required')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Upload failed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'STORAGE_300'),
                new OA\Property(property: 'message', type: 'string', example: 'File upload failed')
            ]
        )
    )]
    public function sessionUpload(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
        }

        $filename = $request->headers->get('X-Filename');
        $sessionId = $request->headers->get('X-Session-Id');
        $contentType = $request->headers->get('Content-Type', 'text/tab-separated-values');
        $contentLength = (int) $request->headers->get('Content-Length', 0);

        // The client may carry its own pseudonym; the authenticated user id is the fallback so a
        // session can never be filed under an unattributable prefix.
        $participantId = $request->headers->get('X-Participant-Id') ?: $user->getId()->toRfc4122();

        if (!$filename) {
            throw new ValidationException(ErrorCode::STORAGE_FILENAME_REQUIRED);
        }

        if (!$sessionId) {
            throw new ValidationException(ErrorCode::STORAGE_EXPERIMENT_ID_REQUIRED);
        }

        if ($contentLength <= 0) {
            throw new ValidationException(ErrorCode::STORAGE_FILE_TOO_LARGE);
        }

        // The sidecar is small and is the only artefact worth reading here; the sample streams are
        // forwarded to object storage untouched. Read it before streaming, because php://input can
        // only be consumed once.
        $sidecar = null;
        if ($filename === 'metadata.json' && $contentLength <= self::MAX_INSPECTABLE_SIDECAR) {
            $sidecar = file_get_contents('php://input') ?: null;
        }

        // Direct stream from php://input to object storage — no temp file.
        //
        // Timed and failure-counted because this is the hop the whole instrument depends on and the
        // one nobody could see: a participant on a lab AP with no route out fails here, and until
        // this was instrumented the only trace of it was a session that never appeared in S3.
        $startedAt = microtime(true);
        try {
            $result = $this->s3Service->directSessionStreamUpload(
                filename: $filename,
                contentType: $contentType,
                contentLength: $contentLength,
                participantId: $participantId,
                sessionId: $sessionId,
                body: $sidecar,
            );
        } catch (\Throwable $e) {
            // Counted, logged, and RETHROWN. The client must still see the failure so its retry
            // logic runs — swallowing it here would turn a recoverable upload into a lost session.
            $this->telemetry->artefactFailed($filename);
            $this->logger->error('[lab-upload] artefact FAILED', [
                'session_id' => $sessionId,
                'participant' => $participantId,
                'artefact' => $filename,
                'bytes' => $contentLength,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        $elapsed = microtime(true) - $startedAt;

        $this->telemetry->artefactAccepted($filename);
        $this->telemetry->artefactStored($filename, $contentLength, $elapsed);
        if ($sidecar !== null) {
            $this->telemetry->sessionCompleted($sidecar);
        }

        // The counter above says how many artefacts arrived; it cannot say WHICH, for WHICH session,
        // or how big. This hop — phone to archive — is the most fragile step in the whole instrument
        // and until now it wrote nothing to the journal: on 2026-08-19 the `api` service produced 37
        // log lines in seven hours and every one was an Internet scanner probing for `.env`. A
        // session that failed to upload was therefore indistinguishable from a session nobody ran.
        $this->logger->info('[lab-upload] artefact stored', [
            'session_id' => $sessionId,
            'participant' => $participantId,
            'artefact' => $filename,
            'bytes' => $contentLength,
            'content_type' => $contentType,
            'seconds' => round($elapsed, 3),
            // `metadata.json` arrives last, by client contract, so this flag is the line that marks
            // a session complete rather than merely in progress.
            'session_complete' => $sidecar !== null,
        ]);

        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/api/storage/test', name: 'api_storage_test', methods: ['GET'])]
    #[OA\Get(
        path: '/api/storage/test',
        summary: 'Test S3 connection',
        description: 'Tests the connection to the configured S3 bucket. Use this to verify your S3 configuration is correct.',
        security: [['Bearer' => []]],
        tags: ['Storage']
    )]
    #[OA\Response(
        response: 200,
        description: 'Connection test result',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'bucket', type: 'string', example: 'my-bucket'),
                new OA\Property(property: 'region', type: 'string', example: 'eu-central-1'),
                new OA\Property(property: 'message', type: 'string', example: 'Successfully connected to S3 bucket')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - missing or invalid token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'AUTH_006'),
                new OA\Property(property: 'message', type: 'string', example: 'Authentication required')
            ]
        )
    )]
    public function testConnection(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AuthException(ErrorCode::AUTH_UNAUTHORIZED);
        }

        $result = $this->s3Service->testConnection();

        return $this->json($result, Response::HTTP_OK);
    }
}
