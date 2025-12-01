variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "eu-north-1"
}

variable "project_name" {
  description = "Project name"
  type        = string
  default     = "monad"
}

variable "environment" {
  description = "Environment"
  type        = string
  default     = "prod"
}

# ECS Configuration
variable "container_port" {
  description = "Container port"
  type        = number
  default     = 8000
}

variable "container_cpu" {
  description = "Container CPU units"
  type        = number
  default     = 256
}

variable "container_memory" {
  description = "Container memory in MB"
  type        = number
  default     = 512
}

variable "desired_count" {
  description = "Number of ECS tasks"
  type        = number
  default     = 1
}

# Application Configuration
variable "app_secret" {
  description = "Symfony APP_SECRET"
  type        = string
  sensitive   = true
}

variable "database_url" {
  description = "PostgreSQL connection string"
  type        = string
  sensitive   = true
}

variable "jwt_passphrase" {
  description = "JWT passphrase"
  type        = string
  sensitive   = true
}

variable "cors_allow_origin" {
  description = "CORS allowed origins"
  type        = string
  default     = "^https?://(localhost|127\\.0\\.0\\.1)(:[0-9]+)?$"
}

# S3 Configuration
variable "s3_bucket_name" {
  description = "S3 bucket name"
  type        = string
  default     = "martin-vanco-monad-test"
}

variable "s3_access_key_id" {
  description = "S3 Access Key ID"
  type        = string
  sensitive   = true
}

variable "s3_secret_access_key" {
  description = "S3 Secret Access Key"
  type        = string
  sensitive   = true
}
