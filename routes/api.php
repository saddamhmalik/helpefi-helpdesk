<?php

use App\Domains\Assets\Controllers\Api\AssetController as ApiAssetController;
use App\Domains\Ai\Controllers\Api\AiAssistController as ApiAiAssistController;
use App\Domains\Ai\Controllers\Api\AiSettingController as ApiAiSettingController;
use App\Domains\Auth\Controllers\Api\AuthController as ApiAuthController;
use App\Domains\Auth\Controllers\Api\RoleController as ApiRoleController;
use App\Domains\Billing\Controllers\Api\BillingController as ApiBillingController;
use App\Domains\Csat\Controllers\Api\CsatController as ApiCsatController;
use App\Domains\Notifications\Controllers\Api\NotificationController as ApiNotificationController;
use App\Domains\Security\Controllers\Api\SecurityController as ApiSecurityController;
use App\Domains\Auth\Controllers\Api\PortalAuthController as ApiPortalAuthController;
use App\Domains\Automation\Controllers\Api\AutomationController as ApiAutomationController;
use App\Domains\Contacts\Controllers\Api\ContactController as ApiContactController;
use App\Domains\Contacts\Controllers\Api\OrganizationController as ApiOrganizationController;
use App\Domains\Ai\Controllers\Api\AiDeflectionController as ApiAiDeflectionController;
use App\Domains\Chat\Controllers\Api\ChatWidgetController as ApiChatWidgetController;
use App\Domains\Channels\Controllers\Api\ChannelController as ApiChannelController;
use App\Domains\Channels\Controllers\Api\EmailSettingController as ApiEmailSettingController;
use App\Domains\Integrations\Controllers\Api\IntegrationController as ApiIntegrationController;
use App\Domains\Integrations\Controllers\InboundIntegrationController;
use App\Domains\Knowledge\Controllers\Api\KbDeflectionController as ApiKbDeflectionController;
use App\Domains\Knowledge\Controllers\Api\KnowledgeArticleController as ApiKnowledgeArticleController;
use App\Domains\Knowledge\Controllers\Api\KnowledgeCollectionController as ApiKnowledgeCollectionController;
use App\Domains\Knowledge\Controllers\Api\PortalController as ApiPortalController;
use App\Domains\Reports\Controllers\Api\ReportController as ApiReportController;
use App\Domains\Search\Controllers\Api\GlobalSearchController as ApiGlobalSearchController;
use App\Domains\Settings\Controllers\Api\HelpdeskSettingController as ApiHelpdeskSettingController;
use App\Domains\Sla\Controllers\Api\SlaEscalationController as ApiSlaEscalationController;
use App\Domains\Sla\Controllers\Api\SlaPolicyController as ApiSlaPolicyController;
use App\Domains\Tickets\Controllers\Api\TicketController as ApiTicketController;
use App\Domains\Tickets\Controllers\Api\TicketExportController as ApiTicketExportController;
use App\Domains\TimeTracking\Controllers\Api\TicketTimeEntryController as ApiTicketTimeEntryController;
use App\Domains\Tickets\Controllers\Api\TicketViewController as ApiTicketViewController;
use App\Domains\Workspace\Controllers\Api\WorkspaceController as ApiWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiAuthController::class, 'login']);

    Route::prefix('portal/{brand:slug}')->middleware('brand')->group(function () {
        Route::get('/', [ApiPortalController::class, 'index']);
        Route::get('/collections/{collectionSlug}', [ApiPortalController::class, 'collection']);
        Route::get('/articles/{articleSlug}', [ApiPortalController::class, 'article']);
        Route::get('/search', [ApiPortalController::class, 'search']);
        Route::post('/tickets', [ApiPortalController::class, 'submit']);
        Route::post('/tickets/track', [ApiPortalController::class, 'track']);
        Route::post('/auth/register', [ApiPortalAuthController::class, 'register']);
        Route::post('/auth/login', [ApiPortalAuthController::class, 'login']);
        Route::get('/services', [ApiServiceCatalogController::class, 'index']);
        Route::get('/services/{service}', [ApiServiceCatalogController::class, 'show']);
        Route::post('/services/{service}', [ApiServiceCatalogController::class, 'submit']);
        Route::post('/kb-suggest', [ApiKbDeflectionController::class, 'suggest']);
        Route::post('/kb-deflect', [ApiKbDeflectionController::class, 'deflect']);
        Route::post('/kb-continue', [ApiKbDeflectionController::class, 'continue']);
        Route::post('/kb-article-click', [ApiKbDeflectionController::class, 'articleClick']);
    });

    Route::post('/channels/inbound/email', [ApiChannelController::class, 'inboundEmail']);
    Route::post('/integrations/inbound/jira', [InboundIntegrationController::class, 'jira']);
    Route::post('/integrations/inbound/linear', [InboundIntegrationController::class, 'linear']);

    Route::middleware(['chat.widget.cors', 'throttle:120,1'])->prefix('chat')->group(function () {
        Route::get('/config', [ApiChatWidgetController::class, 'config']);
        Route::post('/sessions', [ApiChatWidgetController::class, 'start']);
        Route::post('/sessions/{session}/messages', [ApiChatWidgetController::class, 'send']);
        Route::get('/sessions/{session}/poll', [ApiChatWidgetController::class, 'poll']);
    });

    Route::middleware(['chat.widget.cors', 'throttle:120,1'])->prefix('deflection')->group(function () {
        Route::get('/config', [ApiAiDeflectionController::class, 'config']);
        Route::post('/ask', [ApiAiDeflectionController::class, 'ask']);
        Route::post('/feedback', [ApiAiDeflectionController::class, 'feedback']);
        Route::post('/escalate', [ApiAiDeflectionController::class, 'escalate']);
    });

    Route::middleware('api.token')->group(function () {
        Route::get('/portal/my-tickets', [ApiPortalAuthController::class, 'myTickets']);
        Route::get('/portal/my-tickets/{ticket}', [ApiPortalAuthController::class, 'myTicket']);
        Route::post('/auth/logout', [ApiAuthController::class, 'logout']);
        Route::get('/auth/me', [ApiAuthController::class, 'me']);

        Route::get('/roles', [ApiRoleController::class, 'index']);
        Route::post('/roles', [ApiRoleController::class, 'store']);
        Route::put('/roles/{role}', [ApiRoleController::class, 'update']);
        Route::delete('/roles/{role}', [ApiRoleController::class, 'destroy']);

        Route::get('/tickets/meta', [ApiTicketController::class, 'meta']);
        Route::get('/tickets', [ApiTicketController::class, 'index']);
        Route::post('/tickets', [ApiTicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [ApiTicketController::class, 'show']);
        Route::put('/tickets/{ticket}', [ApiTicketController::class, 'update']);
        Route::post('/tickets/{ticket}/reply', [ApiTicketController::class, 'reply']);
        Route::post('/tickets/{ticket}/attachments', [ApiTicketController::class, 'storeAttachment']);
        Route::post('/tickets/{ticket}/watchers', [ApiTicketController::class, 'storeWatcher']);
        Route::delete('/tickets/{ticket}/watchers/{user}', [ApiTicketController::class, 'destroyWatcher']);
        Route::post('/tickets/{ticket}/merge', [ApiTicketController::class, 'merge']);
        Route::post('/tickets/{ticket}/split', [ApiTicketController::class, 'split']);
        Route::get('/tickets/{ticket}/export/pdf', [ApiTicketExportController::class, 'pdf']);
        Route::post('/tickets/{ticket}/export/email', [ApiTicketExportController::class, 'email']);
        Route::get('/tickets/{ticket}/time-entries', [ApiTicketTimeEntryController::class, 'index']);
        Route::post('/tickets/{ticket}/time-entries', [ApiTicketTimeEntryController::class, 'store']);
        Route::delete('/tickets/{ticket}/time-entries/{entry}', [ApiTicketTimeEntryController::class, 'destroy']);

        Route::get('/contacts/meta', [ApiContactController::class, 'meta']);
        Route::get('/contacts/search', [ApiContactController::class, 'search']);
        Route::get('/contacts', [ApiContactController::class, 'index']);
        Route::post('/contacts', [ApiContactController::class, 'store']);
        Route::get('/contacts/{contact}', [ApiContactController::class, 'show']);
        Route::put('/contacts/{contact}', [ApiContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ApiContactController::class, 'destroy']);
        Route::post('/contacts/{contact}/notes', [ApiContactController::class, 'storeNote']);

        Route::get('/organizations', [ApiOrganizationController::class, 'index']);
        Route::post('/organizations', [ApiOrganizationController::class, 'store']);
        Route::get('/organizations/{organization}', [ApiOrganizationController::class, 'show']);
        Route::put('/organizations/{organization}', [ApiOrganizationController::class, 'update']);
        Route::delete('/organizations/{organization}', [ApiOrganizationController::class, 'destroy']);

        Route::get('/ticket-views', [ApiTicketViewController::class, 'index']);
        Route::post('/ticket-views', [ApiTicketViewController::class, 'store']);
        Route::delete('/ticket-views/{view}', [ApiTicketViewController::class, 'destroy']);

        Route::get('/knowledge/collections', [ApiKnowledgeCollectionController::class, 'index']);
        Route::post('/knowledge/collections', [ApiKnowledgeCollectionController::class, 'store']);
        Route::put('/knowledge/collections/{collection}', [ApiKnowledgeCollectionController::class, 'update']);
        Route::delete('/knowledge/collections/{collection}', [ApiKnowledgeCollectionController::class, 'destroy']);

        Route::get('/knowledge/articles', [ApiKnowledgeArticleController::class, 'index']);
        Route::post('/knowledge/articles', [ApiKnowledgeArticleController::class, 'store']);
        Route::get('/knowledge/articles/{article}', [ApiKnowledgeArticleController::class, 'show']);
        Route::put('/knowledge/articles/{article}', [ApiKnowledgeArticleController::class, 'update']);
        Route::get('/knowledge/articles/{article}/versions', [ApiKnowledgeArticleController::class, 'versions']);
        Route::post('/knowledge/articles/{article}/versions/{version}/restore', [ApiKnowledgeArticleController::class, 'restoreVersion']);

        Route::get('/sla/policies', [ApiSlaPolicyController::class, 'index']);
        Route::get('/sla/policies/{policy}', [ApiSlaPolicyController::class, 'show']);
        Route::put('/sla/targets/{target}', [ApiSlaPolicyController::class, 'updateTarget']);
        Route::get('/sla/tickets/{ticket}/timer', [ApiSlaPolicyController::class, 'ticketTimer']);
        Route::get('/sla/escalations/meta', [ApiSlaEscalationController::class, 'meta']);
        Route::get('/sla/escalations', [ApiSlaEscalationController::class, 'index']);
        Route::post('/sla/escalations', [ApiSlaEscalationController::class, 'store']);
        Route::delete('/sla/escalations/{rule}', [ApiSlaEscalationController::class, 'destroy']);

        Route::get('/settings/helpdesk', [ApiHelpdeskSettingController::class, 'show']);
        Route::put('/settings/helpdesk', [ApiHelpdeskSettingController::class, 'update']);

        Route::get('/search', ApiGlobalSearchController::class);

        Route::get('/workspace/meta', [ApiWorkspaceController::class, 'meta']);
        Route::get('/workspace/queue', [ApiWorkspaceController::class, 'queue']);
        Route::get('/workspace/queue/poll', [ApiWorkspaceController::class, 'pollQueue']);
        Route::get('/workspace/tickets/{ticket}', [ApiWorkspaceController::class, 'show']);
        Route::get('/workspace/tickets/{ticket}/poll', [ApiWorkspaceController::class, 'pollTicket']);
        Route::put('/workspace/tickets/{ticket}/draft', [ApiWorkspaceController::class, 'saveDraft']);
        Route::post('/workspace/tickets/{ticket}/reply', [ApiWorkspaceController::class, 'reply']);
        Route::patch('/workspace/tickets/{ticket}', [ApiWorkspaceController::class, 'quickUpdate']);

        Route::get('/reports/meta', [ApiReportController::class, 'meta']);
        Route::get('/reports/dashboard', [ApiReportController::class, 'dashboard']);
        Route::get('/reports/run', [ApiReportController::class, 'run']);
        Route::get('/reports/export', [ApiReportController::class, 'export']);
        Route::get('/reports/saved', [ApiReportController::class, 'saved']);
        Route::post('/reports/saved', [ApiReportController::class, 'store']);
        Route::delete('/reports/saved/{report}', [ApiReportController::class, 'destroy']);

        Route::get('/channels', [ApiChannelController::class, 'index']);

        Route::get('/email/inboxes', [ApiEmailSettingController::class, 'inboxes']);
        Route::post('/email/inboxes', [ApiEmailSettingController::class, 'storeInbox']);
        Route::put('/email/inboxes/{inbox}', [ApiEmailSettingController::class, 'updateInbox']);
        Route::delete('/email/inboxes/{inbox}', [ApiEmailSettingController::class, 'destroyInbox']);
        Route::get('/email/outbound', [ApiEmailSettingController::class, 'outboundSettings']);
        Route::put('/email/outbound', [ApiEmailSettingController::class, 'updateOutbound']);
        Route::post('/email/outbound/test', [ApiEmailSettingController::class, 'testOutbound']);

        Route::get('/automation/meta', [ApiAutomationController::class, 'meta']);
        Route::get('/automation/rules', [ApiAutomationController::class, 'index']);
        Route::post('/automation/rules', [ApiAutomationController::class, 'store']);
        Route::put('/automation/rules/{rule}', [ApiAutomationController::class, 'update']);
        Route::delete('/automation/rules/{rule}', [ApiAutomationController::class, 'destroy']);

        Route::get('/integrations/meta', [ApiIntegrationController::class, 'meta']);
        Route::get('/integrations/webhooks', [ApiIntegrationController::class, 'index']);
        Route::post('/integrations/webhooks', [ApiIntegrationController::class, 'store']);
        Route::put('/integrations/webhooks/{webhook}', [ApiIntegrationController::class, 'update']);
        Route::delete('/integrations/webhooks/{webhook}', [ApiIntegrationController::class, 'destroy']);
        Route::post('/integrations/webhooks/{webhook}/test', [ApiIntegrationController::class, 'test']);
        Route::post('/integrations/webhooks/{webhook}/regenerate-secret', [ApiIntegrationController::class, 'regenerateSecret']);

        Route::get('/ai/settings', [ApiAiSettingController::class, 'show']);
        Route::put('/ai/settings', [ApiAiSettingController::class, 'update']);
        Route::post('/ai/tickets/{ticket}/suggest-reply', [ApiAiAssistController::class, 'suggestReply']);
        Route::post('/ai/tickets/{ticket}/summarize', [ApiAiAssistController::class, 'summarize']);
        Route::get('/ai/tickets/{ticket}/kb-assist', [ApiAiAssistController::class, 'kbAssist']);
        Route::get('/deflection/metrics', [ApiAiDeflectionController::class, 'metrics']);
        Route::get('/kb-deflection/metrics', [ApiKbDeflectionController::class, 'metrics']);

        Route::get('/service-catalog', [ApiServiceCatalogController::class, 'adminIndex']);
        Route::post('/service-catalog/categories', [ApiServiceCatalogController::class, 'storeCategory']);
        Route::put('/service-catalog/categories/{category}', [ApiServiceCatalogController::class, 'updateCategory']);
        Route::delete('/service-catalog/categories/{category}', [ApiServiceCatalogController::class, 'destroyCategory']);
        Route::post('/service-catalog/items', [ApiServiceCatalogController::class, 'storeItem']);
        Route::put('/service-catalog/items/{item}', [ApiServiceCatalogController::class, 'updateItem']);
        Route::delete('/service-catalog/items/{item}', [ApiServiceCatalogController::class, 'destroyItem']);

        Route::get('/assets/meta', [ApiAssetController::class, 'meta']);
        Route::get('/assets', [ApiAssetController::class, 'index']);
        Route::post('/assets', [ApiAssetController::class, 'store']);
        Route::get('/assets/{asset}', [ApiAssetController::class, 'show']);
        Route::put('/assets/{asset}', [ApiAssetController::class, 'update']);
        Route::delete('/assets/{asset}', [ApiAssetController::class, 'destroy']);
        Route::post('/assets/{asset}/tickets', [ApiAssetController::class, 'attachTicket']);
        Route::delete('/assets/{asset}/tickets/{ticket}', [ApiAssetController::class, 'detachTicket']);

        Route::get('/billing', [ApiBillingController::class, 'show']);
        Route::put('/billing/plan', [ApiBillingController::class, 'updatePlan']);

        Route::get('/security', [ApiSecurityController::class, 'show']);
        Route::put('/security', [ApiSecurityController::class, 'update']);
        Route::get('/security/audit-logs', [ApiSecurityController::class, 'auditLogs']);
        Route::get('/security/two-factor', [ApiSecurityController::class, 'twoFactorStatus']);
        Route::post('/security/purge-retention', [ApiSecurityController::class, 'purgeRetention']);

        Route::get('/notifications/summary', [ApiNotificationController::class, 'summary']);
        Route::get('/notifications', [ApiNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markRead']);
        Route::get('/notifications/settings', [ApiNotificationController::class, 'settings']);
        Route::put('/notifications/settings', [ApiNotificationController::class, 'updateSettings']);

        Route::get('/csat/settings', [ApiCsatController::class, 'settings']);
        Route::put('/csat/settings', [ApiCsatController::class, 'updateSettings']);
        Route::get('/csat/report', [ApiCsatController::class, 'summary']);
        Route::post('/portal/my-tickets/{ticket}/csat', [ApiCsatController::class, 'submitPortal']);
    });
});
