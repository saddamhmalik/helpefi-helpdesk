<?php

return [
    'AI helpdesk software has moved from novelty to operational necessity. Support teams that deploy AI effectively reduce handle time, deflect repetitive tickets, and improve agent satisfaction — but only when AI is grounded on accurate knowledge, governed with clear policies, and measured by honest metrics.',

    'This guide covers how to adopt AI in your helpdesk: evaluating AI capabilities, deploying Copilot for agents, setting up deflection on portal and chat, measuring impact, and scaling AI across your support operation.',

    'AI capabilities that matter in a helpdesk',

    'Not all AI helpdesk features are created equal. The capabilities that actually reduce workload are: AI Copilot that drafts replies from ticket context and knowledge base grounding, semantic search that matches customer intent to articles by meaning rather than keywords, deflection that suggests articles on portal and chat before tickets are created, and triage assistance that routes and prioritizes tickets based on content analysis.',

    'Evaluate AI on grounding quality — does the AI cite approved knowledge or invent answers? On edit-before-send workflow — can agents review every AI draft before it reaches customers? On pricing — is AI billed per agent, per resolution, or as a flat add-on? On governance — do audit trails show which AI suggestions were accepted, edited, or rejected?',

    'Our AI Agent feature page documents Helpefi\'s approach: Copilot inside the agent workspace, deflection on portal and chat, semantic knowledge search, and flat monthly pricing without per-resolution fees.',

    'Deploying AI Copilot for agents',

    'Copilot should feel like a senior teammate whispering suggestions, not a chatbot hijacking the reply box. When an agent opens a ticket, Copilot reads the thread, customer metadata, and linked knowledge articles to propose a draft response the agent can edit, accept, or discard. Summaries help during handoffs, and suggested next steps guide new agents through complex workflows.',

    'Start with a pilot team that already maintains good knowledge and macros — early wins build credibility. Enable Copilot for that team only, disable customer-facing deflection initially, and run daily standups on draft quality. Set a four-week pilot with explicit exit criteria: if reopen rate rises, pause and fix content before widening scope.',

    'Grounding is critical. Connect knowledge base collections that Copilot can reference, and restrict grounding to approved articles only. Internal-only articles should stay available to agents without entering customer-facing AI suggestions. Audit knowledge quality before enabling AI — Copilot citing a deprecated refund policy is worse than no Copilot at all.',

    'Measuring AI impact honestly',

    'Leaders often ask for a single deflection percentage by Friday. Real impact measures a basket of outcomes: first response time, resolution time, reopen rate, CSAT, and agent handle time on tagged routine intents. Segment by ticket type — password resets should deflect, nuanced integration debugging should not. Comparing blended numbers hides failures.',

    'Track cost per resolved ticket, especially if migrating from per-resolution AI pricing models. Flat Copilot pricing makes finance models simpler. Include content team effort: hours spent updating articles is part of ROI when deflection rises. Our analytics page covers configuring AI impact dashboards.',

    'AI with shared inbox, SLA, and ITSM',

    'AI delivers most value when it respects the rest of your operation. In Helpefi shared inbox, collision detection prevents two agents from sending different AI drafts to the same customer. SLAs continue measuring first response whether the reply was drafted by AI and edited by a human or written from scratch.',

    'Optional Service Desk ITSM extends Copilot to employee requests — summarizing incident threads for managers, suggesting knowledge for common IT issues, while keeping change approvals on formal workflows. Hybrid support and IT teams avoid buying separate AI products for customer and employee channels.',

    'Start your AI adoption with a free trial: connect your email, publish five knowledge base articles, enable Copilot for a pilot team, and measure deflection and handle time against your current baseline. Most teams see measurable improvement within two weeks.',
];
