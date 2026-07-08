<?php

return [
    'A knowledge base is the most scalable investment a support team can make. Every well-written article that answers a customer question before a ticket is created reduces queue pressure, shrinks handle time, and gives AI Copilot reliable grounding to draft accurate replies. But a knowledge base only delivers ROI when articles are accurate, findable, and maintained.',

    'This guide covers knowledge base strategy: writing articles that deflect, organizing content with collections, measuring performance with analytics, integrating with AI and deflection, and maintaining content quality over time.',

    'Writing articles that actually deflect tickets',

    'Deflection starts with intent coverage, not article count. A knowledge base with two hundred pages and one with twenty well-written answers to your top ticket subjects perform the same — until customers encounter question twenty-one. Build a top-twenty list from ticket tags: password resets, invoice downloads, shipping timeframes, integration OAuth errors, platform limits, and refund policy.',

    'Format matters more than length. Use clear headings customers can scan — not prose paragraphs that bury the answer. Short paragraphs, numbered steps for instructions, and a summary box at the top for experienced users. Screenshots with callouts reduce ambiguity for UI-related instructions. For developer content, code blocks and error message examples help customers self-diagnose without pasting screenshots into tickets.',

    'Review every article quarterly for accuracy. Outdated policy references are worse than no article: customers trust your content and act on stale information. Archive articles covering deprecated features and redirect search to current alternatives. Helpefi knowledge base supports article version history for audit and rollback.',

    'Organizing content with collections',

    'Not all knowledge is for all audiences. Collections organize articles by brand, product line, or customer segment. Enterprise support knowledge about dedicated infrastructure belongs on the enterprise portal; basic troubleshooting stays on the general portal. Agents see all collections so internal-only runbooks are available without exposing them to customers.',

    'Multi-brand workspaces extend the same model. Each brand maintains separate article collections scoped to its own portal and AI grounding. Customers never see content from another brand. AI Copilot suggestions stay within the correct collection so a fintech customer does not receive retail refund policy in an AI-drafted reply.',

    'Collection scoping also supports phased rollouts: publish draft content to internal-only collections during review, shift to customer-facing when approved, archive when products sunset. Helpefi knowledge base collections with visibility controls make this straightforward. Learn more on our knowledge base feature page.',

    'Measuring knowledge base performance',

    'Track three metrics per article: views, deflection rate (customers who viewed and did not submit a ticket), and reopen rate (customers who submitted a ticket within twenty-four hours after viewing). A high-view, low-deflection article needs rewriting. A low-view, high-deflection article is excellent but underutilized — promote it in portal search or chat deflection.',

    'Search analytics show what customers look for and whether they find it. Terms returning no results are content gaps to fill. Terms with high search volume and low deflection signal articles that exist but do not satisfy intent. Our analytics page covers configuring knowledge base performance dashboards.',

    'Knowledge base as AI grounding',

    'A knowledge base becomes exponentially more valuable when it powers AI. Helpefi Copilot grounds draft suggestions on published articles, and portal deflection suggests articles before tickets are created — both using your curated collections. Write one article that serves customer self-service, AI draft accuracy, and pre-ticket deflection simultaneously.',

    'Grounding quality depends on article quality. Treat knowledge base cleanup as a prerequisite for AI enablement. Archive outdated content, fill identified gaps, and maintain quarterly review cadence. Helpefi AI Agent documentation covers how grounding collections are configured and scoped.',

    'Building a knowledge base culture',

    'The best knowledge bases are built by the whole support team, not a dedicated content team working in isolation. Encourage agents to document solutions as they encounter new issues. Review and approve submissions before publication. Celebrate agents whose articles deflect the most tickets. Make knowledge base contribution a recognized part of the support role.',

    'Start with twenty articles covering eighty percent of ticket volume. Publish weekly during the first quarter and iterate based on deflection analytics. Agent feedback loops — where common workarounds become new articles — build knowledge base quality faster than content calendars alone. Helpefi knowledge base includes article analytics and review workflows to support this process.',
];
