terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = "eu-north-1"
}

# Variables
variable "db_password" {
  type      = string
  sensitive = true
  default   = "monad_secure_pwd_123"
}

variable "app_secret" {
  type      = string
  sensitive = true
  default   = "your-app-secret-here"
}

variable "jwt_passphrase" {
  type      = string
  sensitive = true
  default   = "your-jwt-passphrase"
}

variable "aws_s3_bucket" {
  type    = string
  default = "monad-uploads"
}

variable "aws_s3_access_key" {
  type      = string
  sensitive = true
  default   = ""
}

variable "aws_s3_secret_key" {
  type      = string
  sensitive = true
  default   = ""
}

# Default VPC
data "aws_vpc" "default" {
  default = true
}

data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

data "aws_availability_zones" "available" {
  state = "available"
}

# ECR Repository
resource "aws_ecr_repository" "app" {
  name                 = "monad-backend"
  image_tag_mutability = "MUTABLE"
  force_delete         = true

  image_scanning_configuration {
    scan_on_push = false
  }
}

# Security Groups
resource "aws_security_group" "alb" {
  name        = "monad-alb-sg"
  description = "ALB Security Group"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "monad-alb-sg" }
}

resource "aws_security_group" "ecs" {
  name        = "monad-ecs-sg"
  description = "ECS Tasks Security Group"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port       = 8000
    to_port         = 8000
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "monad-ecs-sg" }
}

resource "aws_security_group" "rds" {
  name        = "monad-rds-sg"
  description = "RDS Security Group"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs.id]
  }

  tags = { Name = "monad-rds-sg" }
}

# RDS PostgreSQL
resource "aws_db_subnet_group" "main" {
  name       = "monad-db-subnet"
  subnet_ids = data.aws_subnets.default.ids

  tags = { Name = "monad-db-subnet" }
}

resource "aws_db_instance" "postgres" {
  identifier           = "monad-db"
  engine               = "postgres"
  engine_version       = "16.6"
  instance_class       = "db.t3.micro"
  allocated_storage    = 20
  storage_type         = "gp2"
  db_name              = "monad"
  username             = "monad"
  password             = var.db_password
  skip_final_snapshot  = true
  publicly_accessible  = false
  db_subnet_group_name = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.rds.id]

  tags = { Name = "monad-db" }
}

# ALB
resource "aws_lb" "main" {
  name               = "monad-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = data.aws_subnets.default.ids

  tags = { Name = "monad-alb" }
}

resource "aws_lb_target_group" "app" {
  name        = "monad-tg"
  port        = 8000
  protocol    = "HTTP"
  vpc_id      = data.aws_vpc.default.id
  target_type = "ip"

  health_check {
    path                = "/"
    healthy_threshold   = 2
    unhealthy_threshold = 10
    timeout             = 30
    interval            = 60
    matcher             = "200-404"
  }

  tags = { Name = "monad-tg" }
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app.arn
  }
}

# ECS Cluster
resource "aws_ecs_cluster" "main" {
  name = "monad-cluster"

  tags = { Name = "monad-cluster" }
}

# IAM Roles
resource "aws_iam_role" "ecs_task_execution" {
  name = "monad-ecs-task-execution"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Action = "sts:AssumeRole"
      Effect = "Allow"
      Principal = { Service = "ecs-tasks.amazonaws.com" }
    }]
  })
}

resource "aws_iam_role_policy_attachment" "ecs_task_execution" {
  role       = aws_iam_role.ecs_task_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role" "ecs_task" {
  name = "monad-ecs-task"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Action = "sts:AssumeRole"
      Effect = "Allow"
      Principal = { Service = "ecs-tasks.amazonaws.com" }
    }]
  })
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "app" {
  name              = "/ecs/monad-backend"
  retention_in_days = 7
}

# ECS Task Definition
resource "aws_ecs_task_definition" "app" {
  family                   = "monad-backend"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = "256"
  memory                   = "512"
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([{
    name  = "app"
    image = "${aws_ecr_repository.app.repository_url}:latest"
    portMappings = [{
      containerPort = 8000
      protocol      = "tcp"
    }]
    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = "postgresql://monad:${var.db_password}@${aws_db_instance.postgres.endpoint}/monad?serverVersion=16&charset=utf8" },
      { name = "JWT_PASSPHRASE", value = var.jwt_passphrase },
      { name = "CORS_ALLOW_ORIGIN", value = "*" },
      { name = "AWS_S3_REGION", value = "eu-central-1" },
      { name = "AWS_S3_BUCKET", value = var.aws_s3_bucket },
      { name = "AWS_S3_ACCESS_KEY_ID", value = var.aws_s3_access_key },
      { name = "AWS_S3_SECRET_ACCESS_KEY", value = var.aws_s3_secret_key },
    ]
    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = aws_cloudwatch_log_group.app.name
        "awslogs-region"        = "eu-north-1"
        "awslogs-stream-prefix" = "ecs"
      }
    }
  }])

  tags = { Name = "monad-backend" }
}

# ECS Service
resource "aws_ecs_service" "app" {
  name            = "monad-backend"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = data.aws_subnets.default.ids
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "app"
    container_port   = 8000
  }

  depends_on = [aws_lb_listener.http]

  tags = { Name = "monad-backend" }
}

# Outputs
output "alb_url" {
  value = "http://${aws_lb.main.dns_name}"
}

output "ecr_url" {
  value = aws_ecr_repository.app.repository_url
}

output "db_endpoint" {
  value = aws_db_instance.postgres.endpoint
}
