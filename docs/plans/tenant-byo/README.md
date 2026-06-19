# Tenant BYO and Self-Hosted Plans

Planning documents for bring-your-own infrastructure, data residency, and self-hosted commercial delivery.

| Document | Description |
|----------|-------------|
| [self-hosted-licensing-and-distribution.md](./self-hosted-licensing-and-distribution.md) | Central license authority, mandatory protected PHP, customer portal, enforcement |
| [self-hosted-deployment.md](./self-hosted-deployment.md) | Customer runbook: register on central → activate → Docker deploy |
| [phase-5-dedicated-self-hosted.md](./phase-5-dedicated-self-hosted.md) | Dedicated VPC / Terraform variant |

## Related code

- `HELPEFI_DEPLOYMENT_MODE=self_hosted`
- `docker-compose.self-hosted.yml`
- `.env.self-hosted.example`
- `app/Domains/Platform/Services/HelpefiLicenseService.php`
