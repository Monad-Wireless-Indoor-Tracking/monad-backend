# Monad

Mobile app backend for indoor wireless tracking.

## Stack

- Backend: Symfony 7.3 (PHP 8.3)
- Database: PostgreSQL 16
- Infrastructure: AWS EC2, ECR, S3
- CI/CD: GitHub Actions

## Deployment

Push to `main` triggers automatic deployment via GitHub Actions:
1. Docker image built and pushed to AWS ECR
2. Image pulled and started on EC2 via docker-compose
3. Database migrations run automatically

## Domain

- API: http://monad.martinvanco.sk
- Terms & Conditions: http://monad.martinvanco.sk/terms
- Privacy Policy: http://monad.martinvanco.sk/privacy-policy
