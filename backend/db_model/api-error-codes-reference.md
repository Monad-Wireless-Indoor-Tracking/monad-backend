# API Error Codes Reference

Complete list of all error codes that can be returned by the API endpoints.

## Error Response Format

All error responses follow this structure:
```json
{
  "code": "ERROR_CODE",
  "message": "Human-readable description"
}
```

---

## Register Endpoint (`POST /api/auth/register`)

### Possible Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | `VALIDATION_100` | Email address is required |
| 400 | `VALIDATION_101` | Email address format is invalid |
| 400 | `VALIDATION_103` | Password is required |
| 400 | `VALIDATION_107` | Name is too long (max 255 characters) |
| 400 | `AUTH_007` | Email address already registered |
| 500 | `SYSTEM_900` | Internal server error |

### Example Error Responses

**Missing email:**
```json
{
  "code": "VALIDATION_100",
  "message": "Email address is required"
}
```

**Invalid email format:**
```json
{
  "code": "VALIDATION_101",
  "message": "Email address format is invalid"
}
```

**Email already exists:**
```json
{
  "code": "AUTH_007",
  "message": "Email address already registered"
}
```

---

## Login Endpoint (`POST /api/auth/login`)

### Possible Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | `VALIDATION_100` | Email address is required |
| 400 | `VALIDATION_101` | Email address format is invalid |
| 400 | `VALIDATION_102` | Email address is too long (max 180 characters) |
| 400 | `VALIDATION_103` | Password is required |
| 400 | `VALIDATION_104` | Password cannot be empty |
| 401 | `AUTH_001` | Invalid email or password |

### Example Error Responses

**Missing email:**
```json
{
  "code": "VALIDATION_100",
  "message": "Email address is required"
}
```

**Invalid credentials:**
```json
{
  "code": "AUTH_001",
  "message": "Invalid email or password"
}
```

**Empty password:**
```json
{
  "code": "VALIDATION_104",
  "message": "Password cannot be empty"
}
```

---

## Me Endpoint (`GET /api/auth/me`)

### Possible Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 401 | `AUTH_006` | Authentication required (no token provided) |
| 401 | `AUTH_004` | Authentication token has expired |
| 401 | `AUTH_005` | Authentication token is invalid |

### Example Error Responses

**No token provided:**
```json
{
  "code": "AUTH_006",
  "message": "Authentication required"
}
```

**Token expired:**
```json
{
  "code": "AUTH_004",
  "message": "Authentication token has expired"
}
```

**Invalid token:**
```json
{
  "code": "AUTH_005",
  "message": "Authentication token is invalid"
}
```

---

## Complete Error Code Catalog

### Authentication & Authorization (AUTH_XXX)

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `AUTH_001` | 401 | Invalid email or password |
| `AUTH_002` | 404 | Email address not found |
| `AUTH_003` | 403 | Account has been disabled |
| `AUTH_004` | 401 | Authentication token has expired |
| `AUTH_005` | 401 | Authentication token is invalid |
| `AUTH_006` | 401 | Authentication required |
| `AUTH_007` | 400 | Email address already registered |

### Validation Errors (VALIDATION_XXX)

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_100` | 400 | Email address is required |
| `VALIDATION_101` | 400 | Email address format is invalid |
| `VALIDATION_102` | 400 | Email address is too long (max 180 characters) |
| `VALIDATION_103` | 400 | Password is required |
| `VALIDATION_104` | 400 | Password cannot be empty |
| `VALIDATION_105` | 400 | Password is too short (min 8 characters) |
| `VALIDATION_106` | 400 | Password is too long (max 255 characters) |
| `VALIDATION_107` | 400 | Name is too long (max 255 characters) |
| `VALIDATION_199` | 400 | Validation failed (generic) |

### Resource Errors (RESOURCE_XXX)

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `RESOURCE_200` | 404 | Requested resource not found |
| `RESOURCE_201` | 409 | Resource already exists |
| `RESOURCE_202` | 403 | Access to resource is forbidden |

### System Errors (SYSTEM_XXX)

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `SYSTEM_900` | 500 | Internal server error |
| `SYSTEM_901` | 500 | Database operation failed |
| `SYSTEM_902` | 503 | Service temporarily unavailable |

---

## Usage in Frontend

### TypeScript Constants

```typescript
export const ErrorCode = {
  // Auth
  AUTH_INVALID_CREDENTIALS: 'AUTH_001',
  AUTH_EMAIL_NOT_FOUND: 'AUTH_002',
  AUTH_ACCOUNT_DISABLED: 'AUTH_003',
  AUTH_TOKEN_EXPIRED: 'AUTH_004',
  AUTH_TOKEN_INVALID: 'AUTH_005',
  AUTH_UNAUTHORIZED: 'AUTH_006',
  AUTH_EMAIL_ALREADY_EXISTS: 'AUTH_007',

  // Validation
  VALIDATION_EMAIL_REQUIRED: 'VALIDATION_100',
  VALIDATION_EMAIL_INVALID: 'VALIDATION_101',
  VALIDATION_EMAIL_TOO_LONG: 'VALIDATION_102',
  VALIDATION_PASSWORD_REQUIRED: 'VALIDATION_103',
  VALIDATION_PASSWORD_EMPTY: 'VALIDATION_104',
  VALIDATION_PASSWORD_TOO_SHORT: 'VALIDATION_105',
  VALIDATION_PASSWORD_TOO_LONG: 'VALIDATION_106',
  VALIDATION_NAME_TOO_LONG: 'VALIDATION_107',
  VALIDATION_FAILED: 'VALIDATION_199',

  // Resources
  RESOURCE_NOT_FOUND: 'RESOURCE_200',
  RESOURCE_ALREADY_EXISTS: 'RESOURCE_201',
  RESOURCE_FORBIDDEN: 'RESOURCE_202',

  // System
  SYSTEM_INTERNAL_ERROR: 'SYSTEM_900',
  SYSTEM_DATABASE_ERROR: 'SYSTEM_901',
  SYSTEM_SERVICE_UNAVAILABLE: 'SYSTEM_902',
} as const;
```

### Error Handling Example

```typescript
try {
  await api.login(email, password);
} catch (error) {
  switch (error.code) {
    case ErrorCode.VALIDATION_EMAIL_REQUIRED:
      setEmailError('Please enter your email');
      break;
    case ErrorCode.VALIDATION_EMAIL_INVALID:
      setEmailError('Invalid email format');
      break;
    case ErrorCode.AUTH_INVALID_CREDENTIALS:
      setGeneralError('Invalid email or password');
      break;
    case ErrorCode.AUTH_ACCOUNT_DISABLED:
      setGeneralError('Your account has been disabled');
      break;
    default:
      setGeneralError('An error occurred. Please try again.');
  }
}
```

---

## Adding New Error Codes

When adding new functionality, follow these steps:

### 1. Add Error Code Constant

```php
// src/Constants/ErrorCode.php
public const VALIDATION_PHONE_INVALID = 'VALIDATION_109';
```

### 2. Add Description

```php
// src/Constants/ErrorCode.php - getDescription() method
self::VALIDATION_PHONE_INVALID => 'Phone number format is invalid',
```

### 3. Update OpenAPI Documentation

Add the new error code to the relevant endpoint's error response enum in `src/OpenApi/AuthDecorator.php`.

### 4. Update This Reference

Add the new error code to the appropriate category table in this document.

### 5. Notify Frontend Team

Inform the frontend team about the new error code so they can update their error handling.
