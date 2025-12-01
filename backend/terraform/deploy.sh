#!/bin/bash
set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== Monad Backend Manual Deploy ===${NC}"
echo -e "${YELLOW}Note: Use GitHub Actions for automated deployments${NC}"

# Get values from Terraform
AWS_REGION=$(terraform output -raw aws_region 2>/dev/null || echo "eu-north-1")
ECR_REPO=$(terraform output -raw ecr_repository_url 2>/dev/null)
ECS_CLUSTER=$(terraform output -raw ecs_cluster_name 2>/dev/null)
ECS_SERVICE=$(terraform output -raw ecs_service_name 2>/dev/null)

if [ -z "$ECR_REPO" ]; then
    echo -e "${RED}Error: Run 'terraform apply' first${NC}"
    exit 1
fi

echo -e "${GREEN}ECR: ${ECR_REPO}${NC}"

# Login to ECR
echo -e "${YELLOW}Logging into ECR...${NC}"
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $ECR_REPO

# Build and push
echo -e "${YELLOW}Building Docker image...${NC}"
cd ../monad-backend
docker build -t monad-backend:latest .
docker tag monad-backend:latest $ECR_REPO:latest
docker push $ECR_REPO:latest

# Update ECS service
echo -e "${YELLOW}Updating ECS service...${NC}"
aws ecs update-service \
    --cluster $ECS_CLUSTER \
    --service $ECS_SERVICE \
    --force-new-deployment \
    --region $AWS_REGION

echo -e "${GREEN}=== Deployment initiated ===${NC}"
echo -e "${GREEN}App URL: $(cd ../terraform && terraform output -raw app_url)${NC}"
