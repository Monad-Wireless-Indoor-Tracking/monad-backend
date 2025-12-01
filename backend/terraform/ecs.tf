# ECS Cluster
resource "aws_ecs_cluster" "app" {
  name = "${var.project_name}-cluster"

  setting {
    name  = "containerInsights"
    value = "disabled"
  }

  tags = {
    Name = "${var.project_name}-cluster"
  }
}

# ECS Task Definition
resource "aws_ecs_task_definition" "app" {
  family                   = "${var.project_name}-backend"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.container_cpu
  memory                   = var.container_memory
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name  = "${var.project_name}-backend"
      image = "${aws_ecr_repository.app.repository_url}:latest"

      portMappings = [
        {
          containerPort = var.container_port
          hostPort      = var.container_port
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "APP_ENV", value = "prod" },
        { name = "APP_SECRET", value = var.app_secret },
        { name = "DATABASE_URL", value = var.database_url },
        { name = "JWT_PASSPHRASE", value = var.jwt_passphrase },
        { name = "CORS_ALLOW_ORIGIN", value = var.cors_allow_origin },
        { name = "AWS_S3_REGION", value = var.aws_region },
        { name = "AWS_S3_BUCKET", value = var.s3_bucket_name },
        { name = "AWS_S3_ACCESS_KEY_ID", value = var.s3_access_key_id },
        { name = "AWS_S3_SECRET_ACCESS_KEY", value = var.s3_secret_access_key }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.ecs.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = "ecs"
        }
      }

      essential = true
    }
  ])

  tags = {
    Name = "${var.project_name}-backend-task"
  }
}

# ECS Service
resource "aws_ecs_service" "app" {
  name            = "${var.project_name}-backend-service"
  cluster         = aws_ecs_cluster.app.id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = data.aws_subnets.default.ids
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "${var.project_name}-backend"
    container_port   = var.container_port
  }

  depends_on = [aws_lb_listener.http]

  tags = {
    Name = "${var.project_name}-backend-service"
  }

  lifecycle {
    ignore_changes = [task_definition]
  }
}
