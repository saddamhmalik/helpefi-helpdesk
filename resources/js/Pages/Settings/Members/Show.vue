<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SettingsLayout from '../../../Layouts/SettingsLayout.vue';
import AppAvatar from '../../../Components/AppAvatar.vue';
import MemberTicketTable from '../../../Components/MemberTicketTable.vue';

const props = defineProps({
    member: Object,
    memberships: Array,
    departments: Array,
    ticketStats: Object,
    assignedByStatus: Array,
    assignedByPriority: Array,
    recentAssignedTickets: Array,
    recentTeamTickets: Array,
    recentDepartmentTickets: Array,
    performance: Object,
    recentPerformanceEvents: Object,
    customFieldDefinitions: { type: Array, default: () => [] },
    allSkills: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const skillsForm = useForm({
    skill_ids: props.member.skills?.map((skill) => skill.id) ?? [],
});

const teamsForm = useForm({
    team_ids: props.memberships?.map((membership) => membership.team_id) ?? [],
});

const toggleSkill = (skillId) => {
    const index = skillsForm.skill_ids.indexOf(skillId);

    if (index === -1) {
        skillsForm.skill_ids.push(skillId);
    } else {
        skillsForm.skill_ids.splice(index, 1);
    }
};

const saveSkills = () => {
    skillsForm.put(`/settings/members/${props.member.id}/skills`, { preserveScroll: true });
};

const toggleTeam = (teamId) => {
    const index = teamsForm.team_ids.indexOf(teamId);

    if (index === -1) {
        teamsForm.team_ids.push(teamId);
    } else {
        teamsForm.team_ids.splice(index, 1);
    }
};

const saveTeams = () => {
    teamsForm.put(`/settings/members/${props.member.id}/teams`, { preserveScroll: true });
};

const roleName = computed(() => props.member.roles?.[0]?.name ?? 'member');

const scoreClass = (score) => {
    if (score >= 80) return 'text-emerald-600';
    if (score >= 60) return 'text-amber-600';

    return 'text-red-600';
};

const statusBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('open')) return 'bg-emerald-100 text-emerald-800';
    if (value.includes('pending')) return 'bg-amber-100 text-amber-800';
    if (value.includes('closed') || value.includes('resolved')) return 'bg-slate-200 text-slate-700';

    return 'bg-slate-100 text-slate-700';
};

const priorityBadgeClass = (name) => {
    const value = (name || '').toLowerCase();
    if (value.includes('urgent') || value.includes('critical')) return 'bg-red-100 text-red-800';
    if (value.includes('high')) return 'bg-orange-100 text-orange-800';
    if (value.includes('low')) return 'bg-slate-100 text-slate-600';

    return 'bg-blue-100 text-blue-800';
};

const customFieldLabel = (name) => props.customFieldDefinitions.find((field) => field.name === name)?.label ?? name;

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
</script>

<template>
    <SettingsLayout :title="member.name" :description="member.email" :head-title="`${member.name} · Profile`">
        <div class="relative -mx-4 pb-8 sm:-mx-6 lg:-mx-8">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-gradient-to-br from-blue-600/5 via-violet-500/5 to-transparent" />

            <div class="relative mb-8">
                <Link href="/settings/members" class="text-sm font-medium text-blue-600 hover:text-blue-700">← Team members</Link>

                <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <AppAvatar :name="member.name" :email="member.email" size="lg" />
                        <div>
                            <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ member.name }}</h1>
                            <p class="mt-1 text-sm text-slate-600">{{ member.email }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-blue-700">{{ roleName }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Joined {{ new Date(member.created_at).toLocaleDateString() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-white/60 bg-white/90 px-5 py-4 text-center shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Performance</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums" :class="scoreClass(performance.score)">{{ Number(performance.score).toFixed(1) }}</p>
                        <Link :href="`/settings/performance/${member.id}`" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700">Full history →</Link>
                    </div>
                </div>
            </div>

            <div class="relative grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-white/60 bg-white/90 p-5 shadow-sm ring-1 ring-blue-100 backdrop-blur">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Assigned tickets</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ ticketStats.assigned.open }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ ticketStats.assigned.closed }} closed · {{ ticketStats.assigned.total }} total</p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/90 p-5 shadow-sm ring-1 ring-violet-100 backdrop-blur">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Team queue</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ ticketStats.team.open }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ ticketStats.team.total }} tickets across teams</p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/90 p-5 shadow-sm ring-1 ring-cyan-100 backdrop-blur">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Department queue</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ ticketStats.department.open }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ ticketStats.department.total }} tickets in departments</p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/90 p-5 shadow-sm ring-1 ring-amber-100 backdrop-blur">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Watching</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ ticketStats.watching }}</p>
                    <p class="mt-1 text-sm text-slate-500">Tickets followed</p>
                </div>
            </div>

            <div class="relative mt-6 grid gap-6 xl:grid-cols-12">
                <div class="space-y-6 xl:col-span-5">
                    <section v-if="allSkills.length" class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <h2 class="text-base font-semibold text-slate-900">Skills</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Used for skills-based auto-assignment rules.</p>
                        <form class="mt-4" @submit.prevent="saveSkills">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="skill in allSkills"
                                    :key="skill.id"
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium transition"
                                    :class="skillsForm.skill_ids.includes(skill.id) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    @click="toggleSkill(skill.id)"
                                >
                                    {{ skill.name }}
                                </button>
                            </div>
                            <button type="submit" class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" :disabled="skillsForm.processing">Save skills</button>
                        </form>
                    </section>

                    <section v-if="teams.length" class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <h2 class="text-base font-semibold text-slate-900">Team membership</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Assign this member to one or more teams.</p>
                        <form class="mt-4" @submit.prevent="saveTeams">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="team in teams"
                                    :key="team.id"
                                    type="button"
                                    class="rounded-full px-3 py-1 text-xs font-medium transition"
                                    :class="teamsForm.team_ids.includes(team.id) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    @click="toggleTeam(team.id)"
                                >
                                    {{ team.name }}
                                </button>
                            </div>
                            <button type="submit" class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" :disabled="teamsForm.processing">Save teams</button>
                        </form>
                    </section>

                    <section class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <h2 class="text-base font-semibold text-slate-900">Organization</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Departments, teams, and responsibilities</p>

                        <div v-if="!departments.length" class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                            Not assigned to any department or team.
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <div v-for="department in departments" :key="department.id" class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ department.name }}</p>
                                        <p v-if="department.description" class="mt-1 text-sm text-slate-500">{{ department.description }}</p>
                                    </div>
                                    <span v-if="department.is_head" class="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700">Head</span>
                                </div>
                                <ul class="mt-3 space-y-2">
                                    <li v-for="team in department.teams" :key="team.id" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm">
                                        <span class="font-medium text-slate-800">{{ team.name }}</span>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ team.org_role.replace('_', ' ') }}</span>
                                            <span v-if="team.is_lead" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Lead</span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section v-if="customFieldDefinitions.length && Object.keys(member.custom_fields ?? {}).length" class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <h2 class="text-base font-semibold text-slate-900">Custom fields</h2>
                        <dl class="mt-4 space-y-3">
                            <div v-for="(value, key) in member.custom_fields" :key="key" class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                <dt class="text-sm text-slate-500">{{ customFieldLabel(key) }}</dt>
                                <dd class="text-right text-sm font-medium text-slate-900">{{ value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <h2 class="text-base font-semibold text-slate-900">Performance (30 days)</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-xs text-slate-500">Points</dt>
                                <dd class="mt-1 text-xl font-bold text-slate-900">{{ performance.total_points }}</dd>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-3">
                                <dt class="text-xs text-emerald-700">Positive</dt>
                                <dd class="mt-1 text-xl font-bold text-emerald-700">{{ performance.positive_events }}</dd>
                            </div>
                            <div class="rounded-xl bg-red-50 p-3">
                                <dt class="text-xs text-red-700">SLA violations</dt>
                                <dd class="mt-1 text-xl font-bold text-red-600">{{ performance.violations }}</dd>
                            </div>
                            <div class="rounded-xl bg-amber-50 p-3">
                                <dt class="text-xs text-amber-700">Negative events</dt>
                                <dd class="mt-1 text-xl font-bold text-amber-700">{{ performance.negative_events }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <div class="space-y-6 xl:col-span-7">
                    <section class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Assigned workload</h2>
                                <p class="mt-0.5 text-sm text-slate-500">Breakdown of tickets assigned to this member</p>
                            </div>
                            <Link :href="`/tickets?assigned_to=${member.id}`" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View all →</Link>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">By status</p>
                                <div class="space-y-2">
                                    <div v-for="item in assignedByStatus" :key="item.slug" class="flex items-center justify-between text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(item.label)">{{ item.label }}</span>
                                        <span class="font-semibold tabular-nums text-slate-900">{{ item.count }}</span>
                                    </div>
                                    <p v-if="!assignedByStatus.length" class="text-sm text-slate-400">No assigned tickets.</p>
                                </div>
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Open by priority</p>
                                <div class="space-y-2">
                                    <div v-for="item in assignedByPriority" :key="item.slug" class="flex items-center justify-between text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass(item.label)">{{ item.label }}</span>
                                        <span class="font-semibold tabular-nums text-slate-900">{{ item.count }}</span>
                                    </div>
                                    <p v-if="!assignedByPriority.length" class="text-sm text-slate-400">No open assigned tickets.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <MemberTicketTable title="Recent assigned tickets" :tickets="recentAssignedTickets" empty="No assigned tickets yet." />
                    <MemberTicketTable title="Recent department tickets" :tickets="recentDepartmentTickets" empty="No tickets in this member's departments." />
                    <MemberTicketTable title="Recent team tickets" :tickets="recentTeamTickets" empty="No tickets in this member's teams." />

                    <section class="rounded-2xl border border-white/60 bg-white/90 p-6 shadow-sm ring-1 ring-slate-100 backdrop-blur">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Recent performance events</h2>
                                <p class="mt-0.5 text-sm text-slate-500">Latest score changes</p>
                            </div>
                            <Link :href="`/settings/performance/${member.id}`" class="text-xs font-semibold text-blue-600 hover:text-blue-700">View all →</Link>
                        </div>

                        <ul class="mt-4 divide-y divide-slate-100">
                            <li v-for="event in recentPerformanceEvents.data" :key="event.id" class="flex items-center justify-between gap-4 py-3 first:pt-0">
                                <div>
                                    <p class="text-sm font-medium capitalize text-slate-800">{{ event.event_type.replaceAll('_', ' ') }}</p>
                                    <p class="text-xs text-slate-500">{{ formatDate(event.created_at) }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Link v-if="event.ticket" :href="`/tickets/${event.ticket.id}`" class="text-xs font-medium text-blue-600 hover:text-blue-700">{{ event.ticket.number }}</Link>
                                    <span class="text-sm font-semibold tabular-nums" :class="event.points >= 0 ? 'text-emerald-700' : 'text-red-600'">{{ event.points >= 0 ? '+' : '' }}{{ event.points }}</span>
                                </div>
                            </li>
                            <li v-if="!recentPerformanceEvents.data?.length" class="py-6 text-center text-sm text-slate-400">No performance events yet.</li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
