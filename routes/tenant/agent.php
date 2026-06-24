<?php

declare(strict_types=1);

use App\Domains\Admin\Controllers\AdminHubController;
use App\Domains\Ai\Controllers\AiAssistController;
use App\Domains\Ai\Controllers\AgentCopilotController;
use App\Domains\Ai\Controllers\AiSettingController;
use App\Domains\Assets\Controllers\AssetController;
use App\Domains\Assets\Controllers\AssetDiscoveryController;
use App\Domains\Assets\Controllers\AssetExportController;
use App\Domains\Assets\Controllers\AssetImportController;
use App\Domains\Assets\Controllers\AssetTypeController;
use App\Domains\Assignment\Controllers\AssignmentRuleController;
use App\Domains\Auth\Controllers\CustomerAccountController;
use App\Domains\Auth\Controllers\MemberController;
use App\Domains\Auth\Controllers\MemberExportController;
use App\Domains\Auth\Controllers\ProfileController;
use App\Domains\Auth\Controllers\RoleController;
use App\Domains\Automation\Controllers\AutomationController;
use App\Domains\Brands\Controllers\BrandController;
use App\Domains\Channels\Controllers\ChannelController;
use App\Domains\Channels\Controllers\EmailSettingController;
use App\Domains\Channels\Controllers\EmailTemplateController;
use App\Domains\Contacts\Controllers\ContactController;
use App\Domains\Contacts\Controllers\ContactExportController;
use App\Domains\Contacts\Controllers\CustomerContextController;
use App\Domains\Contacts\Controllers\OrganizationController;
use App\Domains\Contacts\Controllers\OrganizationExportController;
use App\Domains\Csat\Controllers\CsatSettingController;
use App\Domains\Dashboard\Controllers\DashboardController;
use App\Domains\Growth\Controllers\GrowthHubController;
use App\Domains\Integrations\Controllers\IntegrationController;
use App\Domains\Integrations\Controllers\TicketExternalIssueController;
use App\Domains\Knowledge\Controllers\KnowledgeArticleController;
use App\Domains\Knowledge\Controllers\KnowledgeCollectionController;
use App\Domains\Knowledge\Controllers\KnowledgeSettingController;
use App\Domains\Knowledge\Controllers\PlatformHandbookController;
use App\Domains\Macros\Controllers\CannedResponseController;
use App\Domains\Notifications\Controllers\NotificationController;
use App\Domains\Notifications\Controllers\NotificationSettingController;
use App\Domains\Performance\Controllers\PerformanceController;
use App\Domains\Platform\Controllers\Tenant\PlatformFeedbackController;
use App\Domains\Platform\Controllers\Tenant\PlatformNoticeController;
use App\Domains\Reports\Controllers\ReportController;
use App\Domains\Reports\Controllers\ReportScheduleController;
use App\Domains\Search\Controllers\GlobalSearchController;
use App\Domains\Security\Controllers\SecuritySettingController;
use App\Domains\Security\Controllers\SsoController;
use App\Domains\Security\Controllers\TwoFactorController;
use App\Domains\ServiceCatalog\Controllers\ServiceCatalogController;
use App\Domains\ServiceDesk\Controllers\ApprovalController;
use App\Domains\ServiceDesk\Controllers\ChangeController;
use App\Domains\ServiceDesk\Controllers\MajorIncidentController;
use App\Domains\ServiceDesk\Controllers\ProblemController;
use App\Domains\ServiceDesk\Controllers\ServiceDeskController;
use App\Domains\SideConversations\Controllers\SideConversationController;
use App\Domains\Sla\Controllers\SlaPolicyController;
use App\Domains\Tenancy\Controllers\CustomDomainController;
use App\Domains\Tenancy\Controllers\InfrastructureController;
use App\Domains\Tickets\Controllers\TicketBulkController;
use App\Domains\Tickets\Controllers\TicketController;
use App\Domains\Tickets\Controllers\TicketExportController;
use App\Domains\Tickets\Controllers\TicketViewController;
use App\Domains\TimeTracking\Controllers\TicketTimeEntryController;
use App\Domains\Workforce\Controllers\WorkforceController;
use App\Domains\Workspace\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

    Route::middleware('agent')->group(function () {
        Route::middleware('two-factor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/growth', [GrowthHubController::class, 'index'])
            ->middleware('admin')
            ->name('growth.index');
        Route::get('/settings', [AdminHubController::class, 'index'])->name('settings');
        Route::redirect('/admin', '/settings')->name('admin.hub');

        Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('settings.profile');
        Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
        Route::post('/settings/profile/avatar', [ProfileController::class, 'uploadAvatar'])
            ->middleware('throttle:10,1')
            ->name('settings.profile.avatar.upload');
        Route::delete('/settings/profile/avatar', [ProfileController::class, 'destroyAvatar'])
            ->middleware('throttle:10,1')
            ->name('settings.profile.avatar.destroy');
        Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password.update');
        Route::post('/settings/two-factor/setup', [TwoFactorController::class, 'setup'])->name('settings.two-factor.setup');
        Route::post('/settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('settings.two-factor.confirm');
        Route::delete('/settings/two-factor', [TwoFactorController::class, 'destroy'])->name('settings.two-factor.destroy');

        Route::middleware('audit.view')->group(function () {
            Route::get('/settings/audit-logs', [\App\Domains\Security\Controllers\AuditLogController::class, 'index'])->name('settings.audit-logs');
            Route::get('/settings/audit-logs/export', [\App\Domains\Security\Controllers\AuditLogController::class, 'export'])->name('settings.audit-logs.export');
        });

        Route::middleware('admin')->group(function () {
            Route::get('/settings/members', [MemberController::class, 'index'])->name('settings.members');
            Route::get('/settings/members/export', [MemberExportController::class, 'csv'])->name('settings.members.export');
            Route::get('/settings/members/{member}', [MemberController::class, 'show'])->name('settings.members.show');
            Route::post('/settings/members/invite', [MemberController::class, 'invite'])->name('settings.members.invite');
            Route::put('/settings/members/{member}', [MemberController::class, 'updateRole'])->name('settings.members.update');
            Route::patch('/settings/members/{member}/custom-fields', [MemberController::class, 'updateCustomFields'])->name('settings.members.custom-fields');
        Route::put('/settings/members/{member}/skills', [MemberController::class, 'updateSkills'])->name('settings.members.skills');
        Route::put('/settings/members/{member}/teams', [MemberController::class, 'updateTeams'])->name('settings.members.teams');
        Route::delete('/settings/members/{member}', [MemberController::class, 'destroy'])->name('settings.members.destroy');
            Route::get('/customers/accounts', [CustomerAccountController::class, 'index'])->name('customers.accounts');
            Route::delete('/customers/accounts/{customer}', [CustomerAccountController::class, 'destroy'])->name('customers.accounts.destroy');
            Route::get('/settings/roles', [RoleController::class, 'index'])->name('settings.roles');
            Route::post('/settings/roles', [RoleController::class, 'store'])->name('settings.roles.store');
            Route::put('/settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update');
            Route::delete('/settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy');
            Route::get('/settings/sla', [SlaPolicyController::class, 'index'])->name('settings.sla');
            Route::put('/settings/sla/business-hours/{businessHours}', [SlaPolicyController::class, 'updateBusinessHours'])->name('settings.sla.business-hours.update');
            Route::post('/settings/sla/policies', [SlaPolicyController::class, 'storePolicy'])->name('settings.sla.policies.store');
            Route::delete('/settings/sla/policies/{policy}', [SlaPolicyController::class, 'destroyPolicy'])->name('settings.sla.policies.destroy');
            Route::put('/settings/sla/targets/{target}', [SlaPolicyController::class, 'updateTarget'])->name('settings.sla.targets.update');
            Route::post('/settings/sla/escalations', [SlaPolicyController::class, 'storeEscalation'])->name('settings.sla.escalations.store');
            Route::delete('/settings/sla/escalations/{rule}', [SlaPolicyController::class, 'destroyEscalation'])->name('settings.sla.escalations.destroy');
            Route::get('/settings/workforce', [WorkforceController::class, 'index'])->name('settings.workforce');
            Route::post('/settings/workforce/departments', [WorkforceController::class, 'storeDepartment'])->name('settings.workforce.departments.store');
            Route::put('/settings/workforce/departments/{department}', [WorkforceController::class, 'updateDepartment'])->name('settings.workforce.departments.update');
            Route::delete('/settings/workforce/departments/{department}', [WorkforceController::class, 'destroyDepartment'])->name('settings.workforce.departments.destroy');
            Route::post('/settings/workforce/teams', [WorkforceController::class, 'storeTeam'])->name('settings.workforce.teams.store');
            Route::put('/settings/workforce/teams/{team}', [WorkforceController::class, 'updateTeam'])->name('settings.workforce.teams.update');
            Route::delete('/settings/workforce/teams/{team}', [WorkforceController::class, 'destroyTeam'])->name('settings.workforce.teams.destroy');
            Route::get('/settings/performance/{user}', [PerformanceController::class, 'show'])->name('settings.performance.show');
            Route::get('/settings/channels', [ChannelController::class, 'index'])->name('settings.channels');
            Route::put('/settings/channels/{channel}', [ChannelController::class, 'update'])->name('settings.channels.update');
            Route::get('/settings/messaging', [\App\Domains\Channels\Controllers\MessagingSettingController::class, 'index'])->name('settings.messaging');
            Route::put('/settings/messaging', [\App\Domains\Channels\Controllers\MessagingSettingController::class, 'update'])->name('settings.messaging.update');
            Route::get('/settings/brands', [BrandController::class, 'index'])->name('settings.brands');
            Route::post('/settings/brands', [BrandController::class, 'store'])->name('settings.brands.store');
            Route::put('/settings/brands/{brand}', [BrandController::class, 'update'])->name('settings.brands.update');
            Route::delete('/settings/brands/{brand}', [BrandController::class, 'destroy'])->name('settings.brands.destroy');
            Route::get('/settings/email', [EmailSettingController::class, 'index'])->name('settings.email');
            Route::post('/settings/email/inboxes', [EmailSettingController::class, 'storeInbox'])->name('settings.email.inboxes.store');
            Route::put('/settings/email/inboxes/{inbox}', [EmailSettingController::class, 'updateInbox'])->name('settings.email.inboxes.update');
            Route::delete('/settings/email/inboxes/{inbox}', [EmailSettingController::class, 'destroyInbox'])->name('settings.email.inboxes.destroy');
            Route::post('/settings/email/inboxes/{inbox}/regenerate-token', [EmailSettingController::class, 'regenerateInboxToken'])->name('settings.email.inboxes.regenerate');
            Route::post('/settings/email/inboxes/{inbox}/mailbox/test', [EmailSettingController::class, 'testInboxMailbox'])->name('settings.email.inboxes.mailbox.test');
            Route::post('/settings/email/inboxes/{inbox}/mailbox/poll', [EmailSettingController::class, 'pollInboxMailbox'])->name('settings.email.inboxes.mailbox.poll');
            Route::get('/settings/email/inboxes/{inbox}/oauth/{provider}', [\App\Domains\Channels\Controllers\MailboxOAuthController::class, 'redirect'])->name('settings.email.inboxes.oauth.redirect');
            Route::post('/settings/email/inboxes/{inbox}/oauth/disconnect', [\App\Domains\Channels\Controllers\MailboxOAuthController::class, 'disconnect'])->name('settings.email.inboxes.oauth.disconnect');
            Route::put('/settings/email/outbound', [EmailSettingController::class, 'updateOutbound'])->name('settings.email.outbound');
            Route::put('/settings/email/advanced', [EmailSettingController::class, 'updateAdvanced'])->name('settings.email.advanced');
            Route::post('/settings/email/outbound/test', [EmailSettingController::class, 'testOutbound'])->name('settings.email.outbound.test');
            Route::post('/settings/email/outbound/test-inbox', [EmailSettingController::class, 'testInboxOutbound'])->name('settings.email.outbound.test-inbox');
            Route::get('/settings/email-templates', [EmailTemplateController::class, 'index'])->name('settings.email-templates.index');
            Route::get('/settings/email-templates/{template}/edit', [EmailTemplateController::class, 'edit'])->name('settings.email-templates.edit');
            Route::put('/settings/email-templates/{template}', [EmailTemplateController::class, 'update'])->name('settings.email-templates.update');
            Route::post('/settings/email-templates/{template}/preview', [EmailTemplateController::class, 'preview'])->name('settings.email-templates.preview');
            Route::post('/settings/email-templates/{template}/reset', [EmailTemplateController::class, 'reset'])->name('settings.email-templates.reset');
            Route::get('/settings/automation', [AutomationController::class, 'index'])->name('settings.automation');
            Route::post('/settings/automation', [AutomationController::class, 'store'])->name('settings.automation.store');
            Route::put('/settings/automation/{rule}', [AutomationController::class, 'update'])->name('settings.automation.update');
            Route::delete('/settings/automation/{rule}', [AutomationController::class, 'destroy'])->name('settings.automation.destroy');
            Route::get('/settings/assignment', [AssignmentRuleController::class, 'index'])->name('settings.assignment');
            Route::post('/settings/assignment', [AssignmentRuleController::class, 'store'])->name('settings.assignment.store');
            Route::put('/settings/assignment/{rule}', [AssignmentRuleController::class, 'update'])->name('settings.assignment.update');
            Route::delete('/settings/assignment/{rule}', [AssignmentRuleController::class, 'destroy'])->name('settings.assignment.destroy');
            Route::get('/settings/skills', [\App\Domains\Workforce\Controllers\SkillController::class, 'index'])->name('settings.skills');
            Route::post('/settings/skills', [\App\Domains\Workforce\Controllers\SkillController::class, 'store'])->name('settings.skills.store');
            Route::put('/settings/skills/{skill}', [\App\Domains\Workforce\Controllers\SkillController::class, 'update'])->name('settings.skills.update');
            Route::delete('/settings/skills/{skill}', [\App\Domains\Workforce\Controllers\SkillController::class, 'destroy'])->name('settings.skills.destroy');
            Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations');
            Route::post('/settings/integrations/webhooks', [IntegrationController::class, 'store'])->name('settings.integrations.webhooks.store');
            Route::put('/settings/integrations/webhooks/{webhook}', [IntegrationController::class, 'update'])->name('settings.integrations.webhooks.update');
            Route::delete('/settings/integrations/webhooks/{webhook}', [IntegrationController::class, 'destroy'])->name('settings.integrations.webhooks.destroy');
            Route::post('/settings/integrations/webhooks/{webhook}/test', [IntegrationController::class, 'test'])->name('settings.integrations.webhooks.test');
            Route::post('/settings/integrations/webhooks/{webhook}/regenerate-secret', [IntegrationController::class, 'regenerateSecret'])->name('settings.integrations.webhooks.regenerate-secret');
            Route::put('/settings/integrations/slack', [IntegrationController::class, 'updateSlack'])->name('settings.integrations.slack.update');
            Route::put('/settings/integrations/jira', [IntegrationController::class, 'updateJira'])->name('settings.integrations.jira.update');
            Route::put('/settings/integrations/linear', [IntegrationController::class, 'updateLinear'])->name('settings.integrations.linear.update');
            Route::post('/settings/integrations/slack/test', [IntegrationController::class, 'testSlack'])->name('settings.integrations.slack.test');
            Route::put('/settings/integrations/shopify', [IntegrationController::class, 'updateShopify'])->name('settings.integrations.shopify.update');
            Route::put('/settings/integrations/hubspot', [IntegrationController::class, 'updateHubspot'])->name('settings.integrations.hubspot.update');
            Route::put('/settings/integrations/salesforce', [IntegrationController::class, 'updateSalesforce'])->name('settings.integrations.salesforce.update');
            Route::put('/settings/integrations/teams', [IntegrationController::class, 'updateTeams'])->name('settings.integrations.teams.update');
            Route::put('/settings/integrations/zapier', [IntegrationController::class, 'updateZapier'])->name('settings.integrations.zapier.update');
            Route::post('/settings/integrations/shopify/test', [IntegrationController::class, 'testShopify'])->name('settings.integrations.shopify.test');
            Route::post('/settings/integrations/hubspot/test', [IntegrationController::class, 'testHubspot'])->name('settings.integrations.hubspot.test');
            Route::post('/settings/integrations/salesforce/test', [IntegrationController::class, 'testSalesforce'])->name('settings.integrations.salesforce.test');
            Route::post('/settings/integrations/teams/test', [IntegrationController::class, 'testTeams'])->name('settings.integrations.teams.test');
            Route::get('/settings/ai', [AiSettingController::class, 'edit'])->name('settings.ai');
            Route::put('/settings/ai', [AiSettingController::class, 'update'])->name('settings.ai.update');
            Route::get('/settings/service-catalog', [ServiceCatalogController::class, 'index'])->name('settings.service-catalog');
            Route::post('/settings/service-catalog/categories', [ServiceCatalogController::class, 'storeCategory'])->name('settings.service-catalog.categories.store');
            Route::put('/settings/service-catalog/categories/{category}', [ServiceCatalogController::class, 'updateCategory'])->name('settings.service-catalog.categories.update');
            Route::delete('/settings/service-catalog/categories/{category}', [ServiceCatalogController::class, 'destroyCategory'])->name('settings.service-catalog.categories.destroy');
            Route::post('/settings/service-catalog/items', [ServiceCatalogController::class, 'storeItem'])->name('settings.service-catalog.items.store');
            Route::put('/settings/service-catalog/items/{item}', [ServiceCatalogController::class, 'updateItem'])->name('settings.service-catalog.items.update');
            Route::delete('/settings/service-catalog/items/{item}', [ServiceCatalogController::class, 'destroyItem'])->name('settings.service-catalog.items.destroy');
            Route::get('/settings/custom-domain', [CustomDomainController::class, 'index'])->name('settings.custom-domain');
            Route::post('/settings/custom-domain', [CustomDomainController::class, 'store'])->name('settings.custom-domain.store');
            Route::post('/settings/custom-domain/verify', [CustomDomainController::class, 'verify'])->name('settings.custom-domain.verify');
            Route::put('/settings/custom-domain/preferences', [CustomDomainController::class, 'updatePreferences'])->name('settings.custom-domain.preferences');
            Route::delete('/settings/custom-domain', [CustomDomainController::class, 'destroy'])->name('settings.custom-domain.destroy');
            Route::get('/settings/infrastructure', [InfrastructureController::class, 'index'])->name('settings.infrastructure');
            Route::put('/settings/infrastructure', [InfrastructureController::class, 'update'])->name('settings.infrastructure.update');
            Route::post('/settings/infrastructure/test-database', [InfrastructureController::class, 'testDatabase'])
                ->middleware('throttle:tenant-infrastructure-verify')
                ->name('settings.infrastructure.test-database');
            Route::post('/settings/infrastructure/test-storage', [InfrastructureController::class, 'testStorage'])
                ->middleware('throttle:tenant-infrastructure-verify')
                ->name('settings.infrastructure.test-storage');
            Route::post('/settings/infrastructure/verify', [InfrastructureController::class, 'verify'])
                ->middleware('throttle:tenant-infrastructure-verify')
                ->name('settings.infrastructure.verify');
            Route::post('/settings/infrastructure/migrate-database', [InfrastructureController::class, 'migrateDatabase'])
                ->name('settings.infrastructure.migrate-database');
            Route::post('/settings/infrastructure/migrate-storage', [InfrastructureController::class, 'migrateStorage'])
                ->name('settings.infrastructure.migrate-storage');
            Route::post('/settings/infrastructure/export-backup', [InfrastructureController::class, 'exportBackup'])
                ->name('settings.infrastructure.export-backup');
            Route::put('/settings/infrastructure/auto-backup', [InfrastructureController::class, 'updateAutoBackup'])
                ->name('settings.infrastructure.auto-backup');
            Route::put('/settings/infrastructure/backups/{backup}', [InfrastructureController::class, 'updateBackup'])
                ->name('settings.infrastructure.backups.update');
            Route::delete('/settings/infrastructure/backups/{backup}', [InfrastructureController::class, 'destroyBackup'])
                ->name('settings.infrastructure.backups.destroy');
            Route::get('/settings/security', [SecuritySettingController::class, 'index'])->name('settings.security');
            Route::put('/settings/security', [SecuritySettingController::class, 'update'])->name('settings.security.update');
            Route::put('/settings/security/sso', [SsoController::class, 'update'])->name('settings.security.sso.update');
            Route::post('/settings/security/purge', [SecuritySettingController::class, 'purge'])->name('settings.security.purge');
            Route::get('/settings/notifications', [NotificationSettingController::class, 'edit'])->name('settings.notifications');
            Route::put('/settings/notifications', [NotificationSettingController::class, 'update'])->name('settings.notifications.update');
            Route::get('/settings/csat', [CsatSettingController::class, 'edit'])->name('settings.csat');
            Route::put('/settings/csat', [CsatSettingController::class, 'update'])->name('settings.csat.update');
            Route::get('/settings/platform-feedback', [PlatformFeedbackController::class, 'create'])->name('settings.platform-feedback');
            Route::post('/settings/platform-feedback', [PlatformFeedbackController::class, 'store'])->name('settings.platform-feedback.store');
            Route::get('/settings/tickets', [\App\Domains\Settings\Controllers\TicketSettingController::class, 'edit'])->name('settings.tickets');
            Route::put('/settings/tickets', [\App\Domains\Settings\Controllers\TicketSettingController::class, 'update'])->name('settings.tickets.update');
            Route::get('/settings/ticket-statuses', [\App\Domains\Tickets\Controllers\TicketStatusController::class, 'index'])->name('settings.ticket-statuses');
            Route::post('/settings/ticket-statuses', [\App\Domains\Tickets\Controllers\TicketStatusController::class, 'store'])->name('settings.ticket-statuses.store');
            Route::put('/settings/ticket-statuses/{status}', [\App\Domains\Tickets\Controllers\TicketStatusController::class, 'update'])->name('settings.ticket-statuses.update');
            Route::delete('/settings/ticket-statuses/{status}', [\App\Domains\Tickets\Controllers\TicketStatusController::class, 'destroy'])->name('settings.ticket-statuses.destroy');
        });

        Route::get('/settings/macros', [CannedResponseController::class, 'index'])->name('settings.macros');
        Route::post('/settings/macros', [CannedResponseController::class, 'store'])->name('settings.macros.store');
        Route::put('/settings/macros/{cannedResponse}', [CannedResponseController::class, 'update'])->name('settings.macros.update');
        Route::delete('/settings/macros/{cannedResponse}', [CannedResponseController::class, 'destroy'])->name('settings.macros.destroy');

        Route::get('/global-search', GlobalSearchController::class)->name('global-search');
        Route::post('/ai/copilot/ask', [AgentCopilotController::class, 'ask'])->name('ai.copilot.ask');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');

        Route::post('/platform-notices/{notice}/dismiss', [PlatformNoticeController::class, 'dismiss'])->name('platform-notices.dismiss');

        Route::middleware('permission:contacts.view')->group(function () {
            Route::get('/contacts/search', [ContactController::class, 'search'])->name('contacts.search');
            Route::get('/contacts/export', [ContactExportController::class, 'csv'])->name('contacts.export');
            Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
            Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
        });

        Route::middleware('permission:contacts.manage')->group(function () {
            Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
            Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
            Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
            Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
            Route::post('/contacts/{contact}/notes', [ContactController::class, 'storeNote'])->name('contacts.notes.store');
            Route::get('/organizations/export', [OrganizationExportController::class, 'csv'])->name('organizations.export');
            Route::resource('organizations', OrganizationController::class)->except(['edit']);
        });

        Route::get('/assets/types', [AssetTypeController::class, 'index'])->name('assets.types.index');
        Route::post('/assets/types', [AssetTypeController::class, 'store'])->name('assets.types.store');
        Route::put('/assets/types/{type}', [AssetTypeController::class, 'update'])->name('assets.types.update');
        Route::delete('/assets/types/{type}', [AssetTypeController::class, 'destroy'])->name('assets.types.destroy');
        Route::get('/assets/export', [AssetExportController::class, 'csv'])->name('assets.export');
        Route::post('/assets/import', [AssetImportController::class, 'store'])->name('assets.import');
        Route::get('/assets/discovery', [AssetDiscoveryController::class, 'index'])->name('assets.discovery.index');
        Route::post('/assets/discovery/scans', [AssetDiscoveryController::class, 'store'])->name('assets.discovery.store');
        Route::get('/assets/discovery/scans/{scan}', [AssetDiscoveryController::class, 'show'])->name('assets.discovery.show');
        Route::post('/assets/discovery/scans/{scan}/import', [AssetDiscoveryController::class, 'import'])->name('assets.discovery.import');
        Route::resource('assets', AssetController::class)->except(['edit']);
        Route::post('/tickets/{ticket}/assets', [AssetController::class, 'attachTicket'])->name('tickets.assets.store');
        Route::delete('/tickets/{ticket}/assets/{asset}', [AssetController::class, 'detachTicket'])->name('tickets.assets.destroy');

        Route::get('/workspace', [WorkspaceController::class, 'index'])->name('workspace.index');
        Route::get('/workspace/tickets/{ticket}', [WorkspaceController::class, 'index'])->name('workspace.show')->whereNumber('ticket');
        Route::get('/workspace/tickets/{ticket}/poll', [WorkspaceController::class, 'pollTicket'])->name('workspace.poll.ticket');
        Route::post('/workspace/tickets/{ticket}/read', [WorkspaceController::class, 'markRead'])->name('workspace.tickets.read');
        Route::get('/workspace/queue/poll', [WorkspaceController::class, 'pollQueue'])->name('workspace.poll.queue');
        Route::post('/tickets/{ticket}/read', [WorkspaceController::class, 'markRead'])->name('tickets.read');
        Route::put('/workspace/tickets/{ticket}/draft', [WorkspaceController::class, 'saveDraft'])->name('workspace.draft.save');
        Route::post('/workspace/tickets/{ticket}/reply', [WorkspaceController::class, 'reply'])->name('workspace.reply');
        Route::patch('/workspace/tickets/{ticket}', [WorkspaceController::class, 'quickUpdate'])->name('workspace.quick-update');
        Route::post('/workspace/tickets/{ticket}/snooze', [WorkspaceController::class, 'snooze'])->name('workspace.snooze');
        Route::delete('/workspace/tickets/{ticket}/snooze', [WorkspaceController::class, 'unsnooze'])->name('workspace.unsnooze');
        Route::post('/workspace/tickets/{ticket}/presence', [WorkspaceController::class, 'presence'])->name('workspace.presence');
        Route::delete('/workspace/tickets/{ticket}/presence', [WorkspaceController::class, 'leave'])->name('workspace.presence.leave');
        Route::get('/workspace/tickets/{ticket}/realtime-token', [WorkspaceController::class, 'realtimeToken'])->name('workspace.realtime.token');
        Route::get('/canned-responses/search', [CannedResponseController::class, 'search'])->name('canned-responses.search');
        Route::post('/canned-responses/{cannedResponse}/apply', [CannedResponseController::class, 'apply'])->name('canned-responses.apply');
        Route::post('/workspace/tickets/{ticket}/ai/suggest-reply', [AiAssistController::class, 'suggestReply'])->name('workspace.ai.suggest-reply');
        Route::post('/workspace/tickets/{ticket}/ai/summarize', [AiAssistController::class, 'summarize'])->name('workspace.ai.summarize');
        Route::get('/workspace/tickets/{ticket}/ai/kb-assist', [AiAssistController::class, 'kbAssist'])->name('workspace.ai.kb-assist');
        Route::get('/workspace/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'index'])->name('workspace.ai.copilot.index');
        Route::post('/workspace/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'store'])->name('workspace.ai.copilot.store');
        Route::delete('/workspace/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'destroy'])->name('workspace.ai.copilot.destroy');
        Route::get('/tickets/{ticket}/customer-context', [CustomerContextController::class, 'show'])->name('tickets.customer-context');
        Route::post('/tickets/{ticket}/customer-context/refresh', [CustomerContextController::class, 'refresh'])->name('tickets.customer-context.refresh');
        Route::post('/tickets/{ticket}/ai/suggest-reply', [AiAssistController::class, 'suggestReply'])->name('tickets.ai.suggest-reply');
        Route::post('/tickets/{ticket}/ai/summarize', [AiAssistController::class, 'summarize'])->name('tickets.ai.summarize');
        Route::get('/tickets/{ticket}/ai/kb-assist', [AiAssistController::class, 'kbAssist'])->name('tickets.ai.kb-assist');
        Route::get('/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'index'])->name('tickets.ai.copilot.index');
        Route::post('/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'store'])->name('tickets.ai.copilot.store');
        Route::delete('/tickets/{ticket}/ai/copilot', [AgentCopilotController::class, 'destroy'])->name('tickets.ai.copilot.destroy');

        Route::get('/service-desk', [ServiceDeskController::class, 'index'])->name('service-desk.index');
        Route::get('/service-desk/approvals', [ApprovalController::class, 'index'])->name('service-desk.approvals.index');
        Route::post('/service-desk/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('service-desk.approvals.approve');
        Route::post('/service-desk/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('service-desk.approvals.reject');
        Route::get('/settings/service-desk/approvals', [ApprovalController::class, 'settings'])->name('settings.service-desk.approvals');
        Route::put('/settings/service-desk/approvals', [ApprovalController::class, 'updateSettings'])->name('settings.service-desk.approvals.update');
        Route::get('/service-desk/changes/calendar', [ChangeController::class, 'calendar'])->name('service-desk.changes.calendar');
        Route::get('/service-desk/major-incidents', [MajorIncidentController::class, 'index'])->name('service-desk.major-incidents.index');
        Route::get('/service-desk/major-incidents/{ticket}/war-room', [MajorIncidentController::class, 'warRoom'])->name('service-desk.major-incidents.war-room');
        Route::post('/tickets/{ticket}/major-incident', [MajorIncidentController::class, 'declare'])->name('tickets.major-incident.declare');
        Route::put('/tickets/{ticket}/major-incident', [MajorIncidentController::class, 'update'])->name('tickets.major-incident.update');
        Route::post('/tickets/{ticket}/major-incident/resolve', [MajorIncidentController::class, 'resolve'])->name('tickets.major-incident.resolve');
        Route::post('/tickets/{ticket}/major-incident/complete-review', [MajorIncidentController::class, 'completeReview'])->name('tickets.major-incident.complete-review');
        Route::put('/tickets/{ticket}/change-record', [ChangeController::class, 'update'])->name('tickets.change-record.update');
        Route::put('/tickets/{ticket}/problem-record', [ProblemController::class, 'update'])->name('tickets.problem-record.update');
        Route::post('/tickets/{ticket}/problem-incidents', [ProblemController::class, 'linkIncident'])->name('tickets.problem-incidents.store');
        Route::delete('/tickets/{ticket}/problem-incidents/{incident}', [ProblemController::class, 'unlinkIncident'])->name('tickets.problem-incidents.destroy');
        Route::get('/service-desk/queues/{type}', [ServiceDeskController::class, 'queue'])->name('service-desk.queue');
        Route::middleware('permission:tickets.view')->group(function () {
        Route::get('/tickets/{ticket}/panels', [TicketController::class, 'panels'])->name('tickets.panels');
        Route::get('/tickets/{ticket}/merge-candidates', [TicketController::class, 'mergeCandidates'])->name('tickets.merge-candidates');
        Route::resource('tickets', TicketController::class)->except(['edit', 'destroy']);
        Route::get('/tickets/{ticket}/export/pdf', [TicketExportController::class, 'pdf'])->name('tickets.export.pdf');
        Route::get('/tickets/export/csv', [TicketExportController::class, 'csv'])->name('tickets.export.csv');
        });

        Route::middleware('permission:tickets.manage')->group(function () {
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/attachments', [TicketController::class, 'storeAttachment'])->name('tickets.attachments.store');
        Route::post('/tickets/{ticket}/watchers', [TicketController::class, 'storeWatcher'])->name('tickets.watchers.store');
        Route::delete('/tickets/{ticket}/watchers/{user}', [TicketController::class, 'destroyWatcher'])->name('tickets.watchers.destroy');
        Route::post('/tickets/{ticket}/merge', [TicketController::class, 'merge'])->name('tickets.merge');
        Route::post('/tickets/{ticket}/split', [TicketController::class, 'split'])->name('tickets.split');
        Route::post('/tickets/{ticket}/side-conversations', [SideConversationController::class, 'store'])->name('tickets.side-conversations.store');
        Route::post('/tickets/{ticket}/side-conversations/{sideConversation}/reply', [SideConversationController::class, 'reply'])->name('tickets.side-conversations.reply');
        Route::patch('/tickets/{ticket}/side-conversations/{sideConversation}/close', [SideConversationController::class, 'close'])->name('tickets.side-conversations.close');
        Route::post('/tickets/{ticket}/time-entries', [TicketTimeEntryController::class, 'store'])->name('tickets.time-entries.store');
        Route::delete('/tickets/{ticket}/time-entries/{entry}', [TicketTimeEntryController::class, 'destroy'])->name('tickets.time-entries.destroy');
        Route::post('/tickets/{ticket}/external-issues', [TicketExternalIssueController::class, 'store'])->name('tickets.external-issues.store');
        Route::post('/tickets/{ticket}/external-issues/sync', [TicketExternalIssueController::class, 'sync'])->name('tickets.external-issues.sync');
        Route::delete('/tickets/{ticket}/external-issues/{issue}', [TicketExternalIssueController::class, 'destroy'])->name('tickets.external-issues.destroy');
        Route::post('/tickets/{ticket}/export/email', [TicketExportController::class, 'email'])->name('tickets.export.email');
        Route::post('/tickets/bulk', [TicketBulkController::class, 'store'])->name('tickets.bulk');
        });

        Route::post('/ticket-views', [TicketViewController::class, 'store'])->name('ticket-views.store');
        Route::delete('/ticket-views/{view}', [TicketViewController::class, 'destroy'])->name('ticket-views.destroy');

        Route::get('/knowledge', [KnowledgeArticleController::class, 'index'])->name('knowledge.index');
        Route::get('/how-to', [PlatformHandbookController::class, 'index'])->name('handbook.index');
        Route::get('/knowledge/settings', [KnowledgeSettingController::class, 'edit'])->name('knowledge.settings');
        Route::put('/knowledge/settings', [KnowledgeSettingController::class, 'update'])->name('knowledge.settings.update');
        Route::get('/knowledge/collections', [KnowledgeCollectionController::class, 'index'])->name('knowledge.collections.index');
        Route::post('/knowledge/collections', [KnowledgeCollectionController::class, 'store'])->name('knowledge.collections.store');
        Route::put('/knowledge/collections/{collection}', [KnowledgeCollectionController::class, 'update'])->name('knowledge.collections.update');
        Route::delete('/knowledge/collections/{collection}', [KnowledgeCollectionController::class, 'destroy'])->name('knowledge.collections.destroy');
        Route::get('/knowledge/create', [KnowledgeArticleController::class, 'create'])->name('knowledge.create');
        Route::post('/knowledge', [KnowledgeArticleController::class, 'store'])->name('knowledge.store');
        Route::get('/knowledge/{article}/edit', [KnowledgeArticleController::class, 'edit'])->name('knowledge.edit')->whereNumber('article');
        Route::get('/knowledge/{article}', [KnowledgeArticleController::class, 'show'])->name('knowledge.show')->whereNumber('article');
        Route::put('/knowledge/{article}', [KnowledgeArticleController::class, 'update'])->name('knowledge.update');
        Route::post('/knowledge/{article}/translations', [KnowledgeArticleController::class, 'storeTranslation'])->name('knowledge.translations.store');
        Route::post('/knowledge/{article}/versions/{version}/restore', [KnowledgeArticleController::class, 'restoreVersion'])->name('knowledge.versions.restore');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::put('/reports/{report}/schedule', [ReportScheduleController::class, 'upsert'])->name('reports.schedule.upsert');
        Route::delete('/reports/{report}/schedule', [ReportScheduleController::class, 'destroy'])->name('reports.schedule.destroy');
        Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
        });
    });
