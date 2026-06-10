<?php

return [
    'types' => [
        'general' => 'General',
        'maintenance' => 'Maintenance',
        'offer' => 'Offer / promotion',
        'announcement' => 'Announcement',
    ],

    'audiences' => [
        'admins' => 'Workspace admins only',
        'all_agents' => 'All agents and admins',
    ],

    'priorities' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
    ],

    'target_scopes' => [
        'all' => 'All workspaces',
        'selected' => 'Selected workspaces',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'image' => [
        'disk' => 'local',
        'directory' => 'platform-notices',
        'max_kb' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    ],
];
