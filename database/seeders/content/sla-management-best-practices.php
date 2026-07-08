<?php

return [
    'Service level agreements are the backbone of professional support. They define what customers can expect — and what agents are accountable for. But SLA management is only effective when policies match operational reality, timers respect business hours, and breaches trigger escalations before customers feel the delay. Without these elements, SLAs become spreadsheet fiction.',

    'This guide covers SLA management best practices: policy design, business hours and holiday calendars, escalation playbooks, reporting, and how SLA enforcement integrates with shared inbox, automation, and AI assistance.',

    'Designing SLA policies that match staffing',

    'The fastest way to lose agent trust in SLAs is setting targets that leadership will not staff. Start from capacity: if two agents cover chat during lunch, a fifteen-minute universal first-response SLA is unachievable. Map customer promises to ticket priorities — urgent, high, normal, low — and attach realistic targets to each.',

    'Separate first response from resolution. Customers feel ignored when nobody acknowledges their issue; they feel abandoned when acknowledgment never becomes progress. Helpefi tracks both independently so agents can send a holding reply while researching complex issues. Update SLAs on long-running tickets when waiting on customers, using pause reasons that reporting can filter later.',

    'Document policies in language agents can quote. "P1 enterprise outage: fifteen-minute first response, four-hour resolution during business hours" is clearer than abstract percentages. Review quarterly with customer success and sales so promised SLAs in new contracts already exist as Helpefi policies before deals close.',

    'Business hours and global coverage',

    'Business hours define when SLA clocks tick. Configure schedules per team or region — nine-to-six IST for India hub, Eastern US for North America — with timers pausing outside those windows. This prevents false breaches when agents are legitimately offline and aligns metrics with labor reality.',

    'Holiday calendars are equally important. Global companies must respect Diwali, Thanksgiving, and EU public holidays without manual ticket snoozes. Attach calendars to SLA policies so timers pause on configured dates. Communicate holiday coverage on your status page and portal so customers expect slower replies.',

    'Follow-the-sun models hand tickets between regions. Use assignment rules plus clear status notes so APAC does not breach a timer Europe was supposed to continue. SLA reports should show which team owned the ticket during each breach window for fair retrospectives.',

    'Escalations before breach',

    'Escalations should fire while there is still time to act — at seventy-five percent of SLA elapsed, not after breach. Helpefi automated escalations notify team leads on Slack or email, bump priority, and optionally reassign to available senior agents. Playbooks differ by priority: P1 pages on-call; low-priority breaches may only need next-day review.',

    'Avoid alert fatigue by tuning thresholds per queue. Pair escalations with automation that excludes tickets waiting on customers — SLA pause reasons should suppress noise when the ball is not in your court. Document which escalations are informational versus actionable so on-call engineers know when to engage.',

    'Managers use escalation history in retrospectives. Repeated breaches on the same product area signal documentation gaps, training needs, or staffing shortages. Helpefi audit trails show who received escalations and when reassignment happened for accountability.',

    'SLA by customer tier and ticket type',

    'Not all customers are equal commercially, and SLAs should reflect that. Map policies to customer tiers, organizations, or tags applied at intake. Enterprise accounts route to tighter targets; free-tier users receive best-effort clocks documented in the portal. Transparency prevents sales from promising bespoke SLAs that operations never configured.',

    'Multi-brand workspaces isolate policies per brand. An agency supporting a fintech client and a retail client attaches different calendars and targets while agents filter views. White-label portals set expectations in footer text that match backend timers.',

    'Optional Service Desk ITSM adds incident and change record types with ITIL-aligned targets. Customer support leaders enabling ITSM later extend the same SLA engine rather than rebuilding from scratch. Learn more on our SLA management feature page.',

    'Reporting SLA compliance stakeholders trust',

    'Executives distrust metrics they cannot trace to tickets. Dashboards show SLA compliance, breach counts, and mean time to respond with drill-down to ticket lists. Define reporting vocabulary upfront — met, breached, paused, excluded — so SLA reviews become operational improvements rather than blame sessions.',

    'Compare periods honestly: holiday weeks versus normal weeks, launch weeks versus steady state. CSAT alongside SLA prevents optimizing speed at the cost of quality. Export via API when finance wants BI tooling. Our analytics page covers SLA reporting configuration in detail.',

    'SLA with AI assistance',

    'AI Copilot assistance counts toward first-response SLAs — a draft that agents edit and send is still a reply. Deflection that prevents ticket creation reduces volume on intents where clocks never start. Helpefi AI Agent works alongside SLA policies without bypassing timers or creating loopholes. SLA compliance and AI adoption reinforce each other: faster responses on routine work, more capacity for complex tickets that need human judgment.',
];
