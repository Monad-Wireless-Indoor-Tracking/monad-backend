# S3 Presigned URLs - Stateless Implementation Guide

## Decision: ✅ USE PRESIGNED URLs (No Database Tracking)

**Approach:** Backend generates presigned URLs on demand. No upload tracking in database.

**Flow:**
1. Mobile app → Backend: "Give me upload URL for this file"
2. Backend → Mobile app: "Here's a presigned URL (valid 15 min)"
3. Mobile app → S3: Direct upload
4. URL expires after 15 min

---

## Implementation Checklist

### 1. S3 Bucket Setup (5 minutes)

```bash
# Create bucket (if not exists)
aws s3 mb s3://martin-vanco-monad-test --region eu-north-1

# Enable encryption
aws s3api put-bucket-encryption \
  --bucket martin-vanco-monad-test \
  --server-side-encryption-configuration '{
    "Rules": [{"ApplyServerSideEncryptionByDefault": {"SSEAlgorithm": "AES256"}}]
  }'

# Block public access
aws s3api put-public-access-block \
  --bucket martin-vanco-monad-test \
  --public-access-block-configuration \
    "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

### 2. Install AWS SDK in Symfony

```bash
cd backend/monad-backend
composer require aws/aws-sdk-php
```

### 3. Configure Environment Variables

Add to `.env`:
```bash
AWS_REGION=eu-north-1
AWS_BUCKET_NAME=martin-vanco-monad-test
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
```

### 4. Create S3 Service (Stateless - No Database)

File: `src/Service/S3PresignedUrlService.php`

```php
<?php
namespace App\Service;

use Aws\S3\S3Client;
use App\Entity\User;
use App\Entity\Quest;

class S3PresignedUrlService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const URL_EXPIRATION = 900; // 15 minutes

    public function __construct(
        private string $awsRegion,
        private string $bucketName,
        private string $awsKey,
        private string $awsSecret
    ) {}

    private function getS3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => $this->awsRegion,
            'credentials' => [
                'key' => $this->awsKey,
                'secret' => $this->awsSecret,
            ],
        ]);
    }

    public function generateUploadUrl(
        User $user,
        Quest $quest,
        string $filename,
        string $contentType,
        int $fileSize
    ): array {
        // Validate file size
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File too large. Max 10MB');
        }

        // Validate content type
        if (!in_array($contentType, ['application/json', 'text/csv', 'application/octet-stream'])) {
            throw new \InvalidArgumentException('Invalid content type');
        }

        // Sanitize filename
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

        // Generate S3 key with structure: uploads/{user_id}/{quest_id}/{timestamp}_{filename}
        $timestamp = (new \DateTime())->format('YmdHis');
        $s3Key = sprintf(
            'uploads/%s/%s/%s_%s',
            $user->getId(),
            $quest->getId(),
            $timestamp,
            $safeFilename
        );

        // Generate presigned URL
        $s3Client = $this->getS3Client();
        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket' => $this->bucketName,
            'Key' => $s3Key,
            'ContentType' => $contentType,
            'ServerSideEncryption' => 'AES256',
        ]);

        $request = $s3Client->createPresignedRequest(
            $cmd,
            '+' . self::URL_EXPIRATION . ' seconds'
        );

        $presignedUrl = (string)$request->getUri();
        $expiresAt = (new \DateTime())->modify('+' . self::URL_EXPIRATION . ' seconds');

        return [
            'presigned_url' => $presignedUrl,
            's3_key' => $s3Key,
            'expires_at' => $expiresAt->format('c'),
            'max_file_size' => self::MAX_FILE_SIZE,
        ];
    }
}
```

### 5. Register Service

File: `config/services.yaml`

```yaml
services:
    App\Service\S3PresignedUrlService:
        arguments:
            $awsRegion: '%env(AWS_REGION)%'
            $bucketName: '%env(AWS_BUCKET_NAME)%'
            $awsKey: '%env(AWS_ACCESS_KEY_ID)%'
            $awsSecret: '%env(AWS_SECRET_ACCESS_KEY)%'
```

### 6. Create API Controller (Single Endpoint)

File: `src/Controller/Api/UploadController.php`

```php
<?php
namespace App\Controller\Api;

use App\Service\S3PresignedUrlService;
use App\Entity\Quest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/v1/upload')]
#[IsGranted('ROLE_USER')]
class UploadController extends AbstractController
{
    public function __construct(
        private S3PresignedUrlService $s3Service,
        private EntityManagerInterface $em
    ) {}

    #[Route('/presigned-url', methods: ['POST'])]
    public function getPresignedUrl(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['quest_id'], $data['filename'], $data['content_type'], $data['file_size'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        // Get quest
        $quest = $this->em->getRepository(Quest::class)->find($data['quest_id']);
        if (!$quest) {
            return $this->json(['error' => 'Quest not found'], 404);
        }

        // Optional: Verify user has access to this quest
        // if ($quest->getUser() !== $this->getUser()) {
        //     return $this->json(['error' => 'Unauthorized'], 403);
        // }

        try {
            $result = $this->s3Service->generateUploadUrl(
                user: $this->getUser(),
                quest: $quest,
                filename: $data['filename'],
                contentType: $data['content_type'],
                fileSize: (int)$data['file_size']
            );

            return $this->json($result);

        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

---

## Mobile App Implementation

### iOS (Swift) - 2 Steps Only

```swift
func uploadBLEData(fileURL: URL, questId: String) async throws {
    let fileData = try Data(contentsOf: fileURL)

    // Step 1: Request presigned URL from backend
    let requestBody: [String: Any] = [
        "quest_id": questId,
        "filename": fileURL.lastPathComponent,
        "content_type": "application/json",
        "file_size": fileData.count
    ]

    let response = try await apiClient.post("/api/v1/upload/presigned-url", body: requestBody)
    let presignedUrl = response["presigned_url"] as! String

    // Step 2: Upload directly to S3
    var request = URLRequest(url: URL(string: presignedUrl)!)
    request.httpMethod = "PUT"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.httpBody = fileData

    let (_, uploadResponse) = try await URLSession.shared.data(for: request)

    guard (uploadResponse as? HTTPURLResponse)?.statusCode == 200 else {
        throw UploadError.failed
    }

    // Done! No need to notify backend
}
```

### Android (Kotlin) - 2 Steps Only

```kotlin
suspend fun uploadBLEData(file: File, questId: String) {
    val fileData = file.readBytes()

    // Step 1: Request presigned URL from backend
    val request = JSONObject().apply {
        put("quest_id", questId)
        put("filename", file.name)
        put("content_type", "application/json")
        put("file_size", fileData.size)
    }

    val response = apiClient.post("/api/v1/upload/presigned-url", request)
    val presignedUrl = response.getString("presigned_url")

    // Step 2: Upload directly to S3
    val requestBody = fileData.toRequestBody("application/json".toMediaType())
    val s3Request = Request.Builder()
        .url(presignedUrl)
        .put(requestBody)
        .header("Content-Type", "application/json")
        .build()

    val s3Response = httpClient.newCall(s3Request).execute()
    if (!s3Response.isSuccessful) {
        throw Exception("Upload failed: ${s3Response.code}")
    }

    // Done! No need to notify backend
}
```

---

## API Documentation

### Endpoint: `POST /api/v1/upload/presigned-url`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "quest_id": "123",
  "filename": "ble_data_20250114_143022.json",
  "content_type": "application/json",
  "file_size": 1048576
}
```

**Response (200 OK):**
```json
{
  "presigned_url": "https://martin-vanco-monad-test.s3.eu-north-1.amazonaws.com/uploads/456/123/20250114143022_ble_data.json?X-Amz-Algorithm=...",
  "s3_key": "uploads/456/123/20250114143022_ble_data.json",
  "expires_at": "2025-01-14T14:45:22+00:00",
  "max_file_size": 10485760
}
```

**Errors:**
- `400` - Missing fields, invalid file size/type
- `403` - User not authorized
- `404` - Quest not found

---

## S3 File Organization

Files will be organized as:
```
martin-vanco-monad-test/
└── uploads/
    └── {user_id}/
        └── {quest_id}/
            ├── 20250114143022_ble_data_1.json
            ├── 20250114143500_ble_data_2.json
            └── 20250114144000_ble_data_3.json
```

**Benefits:**
- Easy to find all files for a user
- Easy to find all files for a quest
- Timestamp prevents filename conflicts
- Can set different S3 lifecycle policies per user/quest

---

## Security Considerations

### What's Protected ✅

1. **User authentication required** - Only logged-in users get URLs
2. **Time-limited access** - URLs expire after 15 minutes
3. **File size limits** - Backend rejects files >10MB
4. **Content-type validation** - Only allowed types
5. **HTTPS enforced** - S3 uses HTTPS by default
6. **Encryption at rest** - AES256 on S3
7. **No public access** - Bucket is private
8. **Organized storage** - Files separated by user/quest

### Potential Risks ⚠️

| Risk | Impact | Mitigation |
|------|--------|------------|
| URL shared/leaked | Medium | Short 15-min expiration limits exposure |
| No upload verification | Low | Mobile app can verify HTTP 200 response |
| No storage quotas | High | **Add quota check before generating URL** |
| Multiple uploads with same filename | Low | Timestamp prefix prevents collisions |

### Recommended: Add User Quota

```php
// In S3PresignedUrlService::generateUploadUrl(), add before generating URL:

// Check user's current storage usage
$userStorageUsed = $this->calculateUserStorage($user);
$userQuota = 100 * 1024 * 1024; // 100MB per user

if ($userStorageUsed + $fileSize > $userQuota) {
    throw new \InvalidArgumentException('Storage quota exceeded');
}

private function calculateUserStorage(User $user): int
{
    $s3Client = $this->getS3Client();
    $prefix = sprintf('uploads/%s/', $user->getId());

    $totalSize = 0;
    $paginator = $s3Client->getPaginator('ListObjectsV2', [
        'Bucket' => $this->bucketName,
        'Prefix' => $prefix,
    ]);

    foreach ($paginator as $result) {
        foreach ($result['Contents'] ?? [] as $object) {
            $totalSize += $object['Size'];
        }
    }

    return $totalSize;
}
```

---

## Testing

### 1. Test Backend Endpoint

```bash
# Get presigned URL
curl -X POST http://localhost:8000/api/v1/upload/presigned-url \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "quest_id": "1",
    "filename": "test.json",
    "content_type": "application/json",
    "file_size": 100
  }'
```

Response:
```json
{
  "presigned_url": "https://martin-vanco-monad-test.s3.eu-north-1.amazonaws.com/...",
  "s3_key": "uploads/1/1/20250114143022_test.json",
  "expires_at": "2025-01-14T14:45:22+00:00",
  "max_file_size": 10485760
}
```

### 2. Test S3 Upload

```bash
# Create test file
echo '{"test": "data"}' > test.json

# Upload using presigned URL (copy from previous response)
curl -X PUT "PASTE_PRESIGNED_URL_HERE" \
  -H "Content-Type: application/json" \
  --data-binary "@test.json"
```

### 3. Verify Upload in S3

```bash
# List files for user 1, quest 1
aws s3 ls s3://martin-vanco-monad-test/uploads/1/1/
```

---

## Optional: Cleanup Old Files

Add S3 lifecycle policy to automatically delete or archive old files:

```bash
# Create lifecycle-policy.json
cat > lifecycle-policy.json <<EOF
{
  "Rules": [
    {
      "Id": "DeleteOldBLEData",
      "Status": "Enabled",
      "Prefix": "uploads/",
      "Expiration": {
        "Days": 365
      }
    }
  ]
}
EOF

# Apply lifecycle policy
aws s3api put-bucket-lifecycle-configuration \
  --bucket martin-vanco-monad-test \
  --lifecycle-configuration file://lifecycle-policy.json
```

This will automatically delete files older than 365 days.

---

## Cost Estimate

**For 1000 users uploading 10MB/day:**
- Storage: 300GB/month = **$7/month**
- PUT requests: 30k/month = **$0.15/month**
- Presigned URL generation: **Free** (backend operation)
- **Total: ~$8/month**

**Comparison to uploading through backend:**
- Would require 300GB/month of bandwidth = **$27/month**
- Plus backend server costs for handling traffic
- **Savings: ~$20+/month**

---

## Complete Implementation Time

1. ✅ Set up S3 bucket - **5 minutes**
2. ✅ Install AWS SDK - **1 minute**
3. ✅ Create S3PresignedUrlService - **5 minutes**
4. ✅ Create UploadController - **5 minutes**
5. ✅ Test with curl - **5 minutes**
6. ✅ Integrate into mobile app - **15 minutes**

**Total: ~35 minutes**

---

## Summary

**What you get:**
- ✅ Stateless backend (no database tracking)
- ✅ One endpoint: `/upload/presigned-url`
- ✅ Direct uploads to S3
- ✅ 15-minute URL expiration
- ✅ ~$8/month for 1000 users
- ✅ 35-minute implementation

**What to watch:**
- ⚠️ Consider adding user storage quotas
- ⚠️ No verification that uploads completed (trust mobile app)
- ⚠️ Leaked URLs are valid for 15 minutes

**This is the simplest, most cost-effective approach for your use case.**
