#!/usr/bin/env python3
"""
Generate presigned S3 upload URL using AWS CLI credentials.
Usage: python3 generate-presigned-upload-url.py <filename> [content-type]
Example: python3 generate-presigned-upload-url.py test.txt text/plain
"""

import sys
import boto3
from botocore.exceptions import NoCredentialsError, ClientError

def generate_presigned_upload_url(bucket_name, object_key, content_type='application/octet-stream', expiration=900):
    """Generate a presigned URL for PUT operation"""

    # Create S3 client using credentials from AWS CLI config with regional endpoint
    s3_client = boto3.client(
        's3',
        region_name='eu-north-1',
        config=boto3.session.Config(
            signature_version='s3v4',
            s3={'addressing_style': 'virtual'}
        )
    )

    try:
        # Generate presigned URL for PUT operation
        url = s3_client.generate_presigned_url(
            'put_object',
            Params={
                'Bucket': bucket_name,
                'Key': object_key,
                'ContentType': content_type
            },
            ExpiresIn=expiration,
            HttpMethod='PUT'
        )
        return url
    except NoCredentialsError:
        print("Error: AWS credentials not found. Run 'aws configure' first.")
        sys.exit(1)
    except ClientError as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 generate-presigned-upload-url.py <filename> [content-type]")
        print("Example: python3 generate-presigned-upload-url.py test.txt text/plain")
        sys.exit(1)

    filename = sys.argv[1]
    content_type = sys.argv[2] if len(sys.argv) > 2 else 'application/octet-stream'

    bucket = "martin-vanco-monad-test"
    object_key = f"uploads/{filename}"

    print(f"Generating presigned upload URL for: {object_key}")
    print(f"Content-Type: {content_type}")
    print(f"Expires in: 15 minutes")
    print()

    url = generate_presigned_upload_url(bucket, object_key, content_type)

    print("Presigned Upload URL:")
    print(url)
    print()
    print("Test upload with:")
    print(f'curl -X PUT "{url}" -H "Content-Type: {content_type}" --data-binary "@{filename}"')
