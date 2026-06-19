variable "name_prefix" {
  type        = string
  description = "Prefix for resource names"
  default     = "helpefi"
}

variable "aws_region" {
  type    = string
  default = "eu-west-1"
}

variable "vpc_cidr" {
  type    = string
  default = "10.20.0.0/16"
}

variable "availability_zones" {
  type    = list(string)
  default = ["eu-west-1a", "eu-west-1b"]
}

variable "private_subnet_cidrs" {
  type    = list(string)
  default = ["10.20.1.0/24", "10.20.2.0/24"]
}

variable "public_subnet_cidrs" {
  type    = list(string)
  default = ["10.20.101.0/24", "10.20.102.0/24"]
}

variable "mysql_engine_version" {
  type    = string
  default = "8.4"
}

variable "rds_instance_class" {
  type    = string
  default = "db.t4g.medium"
}

variable "rds_allocated_storage" {
  type    = number
  default = 50
}

variable "rds_backup_retention_days" {
  type    = number
  default = 7
}

variable "database_name" {
  type    = string
  default = "helpdesk_central"
}

variable "database_username" {
  type    = string
  default = "helpefi"
}

variable "database_password" {
  type      = string
  sensitive = true
}

variable "files_bucket_name" {
  type        = string
  description = "Globally unique S3 bucket name"
}

variable "skip_final_snapshot" {
  type    = bool
  default = true
}

variable "enable_nat_gateway" {
  type    = bool
  default = true
}

variable "alb_ingress_cidr_blocks" {
  type    = list(string)
  default = ["0.0.0.0/0"]
}

variable "container_image" {
  type        = string
  description = "ECR image URI for helpefi app container"
}

variable "container_secrets" {
  type = list(object({
    name      = string
    valueFrom = string
  }))
  default     = []
  description = "ECS secrets from AWS Secrets Manager or SSM"
}

variable "ecs_cpu" {
  type    = string
  default = "1024"
}

variable "ecs_memory" {
  type    = string
  default = "2048"
}

variable "ecs_desired_count" {
  type    = number
  default = 2
}

variable "log_retention_days" {
  type    = number
  default = 30
}

variable "enable_elasticache" {
  type    = bool
  default = false
}

variable "elasticache_node_type" {
  type    = string
  default = "cache.t4g.micro"
}
