# Dedicated tenant VPC (Terraform skeleton)

Terraform module skeleton for deploying helpefi in a **customer-owned AWS account** with isolated networking.

## What this provisions

- VPC with public and private subnets, internet gateway, and optional NAT gateway
- RDS MySQL (single instance) for central + tenant databases
- S3 bucket for attachments (optional BYOS can use separate bucket)
- Application Load Balancer + ECS Fargate service (app tier)
- Optional ElastiCache Redis for queues, cache, and sessions
- Security groups for app tier → RDS and app → Redis

## Usage

```bash
cd deploy/terraform/dedicated-tenant
cp terraform.tfvars.example terraform.tfvars
terraform init
terraform plan
terraform apply
```

After apply, deploy the application with `docker-compose.self-hosted.yml` or point `container_image` at your ECR image. Set `DB_HOST` to the `rds_endpoint` output and `REDIS_HOST` to `redis_endpoint` when ElastiCache is enabled.

## State

Store Terraform state in a customer-managed S3 backend with locking (configure `backend.tf` before production use).

## Not included

- ECS/Fargate service definitions (customer-specific)
- ACM certificates and ALB (customer DNS/TLS)
- WAF rules
- Multi-AZ RDS (enable via variables)

See [self-hosted-deployment.md](../../docs/plans/tenant-byo/self-hosted-deployment.md) for application-level setup.
