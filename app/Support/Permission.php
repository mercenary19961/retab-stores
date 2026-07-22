<?php

namespace App\Support;

/**
 * The admin-panel permission catalogue. A permission is "section.action"
 * (e.g. "orders.export"). Admins bypass all checks; editors are granted a
 * subset per user (stored in users.permissions, defaulting to DEFAULTS).
 *
 * Drives three things: the RequirePermission route middleware, the sidebar
 * visibility (a section is shown when the user has "<section>.view"), and the
 * admin Authorization grid.
 */
class Permission
{
    /** Sections → the actions that can be granted. */
    public const SCHEMA = [
        'orders' => ['view', 'manage', 'export'],
        'products' => ['view', 'create', 'edit', 'delete'],
        'product_requests' => ['view', 'manage'],
        'inventory' => ['view', 'import'],
        'returns' => ['view', 'resolve'],
        'customers' => ['view'],
        'marketing' => ['view', 'send'],
        'coupons' => ['view', 'create', 'edit', 'delete'],
        'discounts' => ['view', 'manage'],
        'reviews' => ['view', 'manage'],
        'content_pages' => ['view', 'edit'],
        'settings' => ['view', 'edit'],
        'change_log' => ['view', 'revert'],
    ];

    /**
     * Default permissions for a new editor: day-to-day operational access, but
     * NOT the sensitive / irreversible actions (order export, product delete,
     * marketing send, settings edit, change-log revert) — the admin grants those.
     */
    public const DEFAULTS = [
        'orders' => ['view' => true, 'manage' => true, 'export' => false],
        'products' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
        'product_requests' => ['view' => true, 'manage' => true],
        'inventory' => ['view' => true, 'import' => true],
        'returns' => ['view' => true, 'resolve' => true],
        'customers' => ['view' => true],
        'marketing' => ['view' => true, 'send' => false],
        'coupons' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
        'discounts' => ['view' => true, 'manage' => false],
        'reviews' => ['view' => true, 'manage' => true],
        'content_pages' => ['view' => true, 'edit' => true],
        'settings' => ['view' => false, 'edit' => false],
        'change_log' => ['view' => true, 'revert' => false],
    ];
}
