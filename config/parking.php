<?php

/*
| Smart Parking business configuration.
|
| Values under "defaults" are the fallback for runtime settings that authorised
| administrators can override through the settings API (table: system_settings).
| Items marked PENDING are open BRD decisions (§19) — the defaults are placeholders,
| not approved business rules.
*/

return [

    'business_timezone' => env('PARKING_TIMEZONE', 'Asia/Colombo'),

    'currency' => 'LKR',

    /** BRD §3 account limits. */
    'account_limits' => [
        'super_admin' => 2,
        'admin' => 2,
    ],

    'qr' => [
        /** Printed QR payload prefix; versioned so the format can change safely. */
        'prefix' => 'SPT1-',
    ],

    'evidence' => [
        'disk' => env('PARKING_EVIDENCE_DISK', 'local'),
        'max_kilobytes' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    /** Hardware / external integrations. "null" drivers are safe simulations. */
    'drivers' => [
        'plate_recognizer' => env('PARKING_PLATE_DRIVER', 'manual'),
        'ticket_printer' => env('PARKING_PRINTER_DRIVER', 'browser'),
        'gate_controller' => env('PARKING_GATE_DRIVER', 'log'),
        'payment_gateway' => env('PARKING_PAYMENT_GATEWAY', 'none'),
        /** disabled | http_local (local Python ANPR service) | fake (automated tests only) */
        'anpr_engine' => env('PARKING_ANPR_ENGINE', 'disabled'),
    ],

    'defaults' => [
        /** BRD §6: 15-minute grace after package expiry. */
        'pricing.grace_minutes' => 15,
        /** Billing block for hourly and overtime charges, in minutes. PENDING: rounding rule. */
        'pricing.billing_block_minutes' => 60,
        /** "package_end" or "grace_end": where chargeable overtime starts once grace is exceeded. PENDING. */
        'pricing.overtime_starts_from' => 'package_end',
        /** Night package ends at this local time (HH:MM). PENDING. */
        'pricing.night_end_time' => '06:00',
        /** "none" | "prorate_daily": early-exit charge for packages that allow it. PENDING: exact method. */
        'pricing.early_exit_method' => 'prorate_daily',
        /** Minimum days charged on early exit. PENDING. */
        'pricing.early_exit_minimum_days' => 1,
        /** Tax rate in basis points (100 = 1%). PENDING: taxes not confirmed, so 0. */
        'pricing.tax_basis_points' => 0,

        /** "credit" | "refund": default handling of unused prepaid amounts. */
        'refund.default_resolution' => 'credit',
        /** Refunds above this amount (minor units) need admin approval. PENDING. */
        'refund.auto_approve_limit_minor' => 0,
        /** Credit validity in days; null = never expires. PENDING. */
        'refund.credit_expiry_days' => null,

        /** BRD §9: admin-chosen inspection frequency. */
        'safety.inspections_per_day' => 2,
        'safety.require_exit_inspection' => true,

        /** "settlement" releases the slot at payment; "gate" waits for gate/staff exit confirmation. */
        'exit.release_slot_on' => 'settlement',

        'notifications.notify_admins_on_damage' => true,

        /** Package expiry reminders (customers with an account; in-app notifications only). */
        'reminders.enabled' => true,
        /** Minutes before package end at which a reminder is sent. PENDING: thresholds to confirm. */
        'reminders.before_expiry_minutes' => [60],
        'reminders.notify_on_expiry' => true,

        /** ANPR. Confidence is in percent (whole numbers); results below it are "low_confidence". PENDING tuning. */
        'anpr.min_confidence_percent' => 90,
        /** always = staff must verify every reading; below_threshold = high-confidence readings are pre-filled but still need staff confirmation. */
        'anpr.verification_mode' => 'always',
        /** Same camera + direction + plate inside this window is recorded as a duplicate. */
        'anpr.duplicate_window_seconds' => 30,
        'anpr.recognition_timeout_ms' => 5000,
        'anpr.camera_retry_attempts' => 2,
        /** Snapshot retention. PENDING client/privacy approval. */
        'anpr.snapshot_retention_days' => 30,
        /** Store the full scene frame in addition to the plate crop. Off by default (data minimisation). PENDING. */
        'anpr.store_full_frame' => false,
        'anpr.health_check_interval_seconds' => 60,
        'anpr.camera_offline_after_seconds' => 180,
        /** Roles allowed to create manual identifications / overrides (subset of staff, admin, super_admin only). */
        'anpr.manual_override_roles' => ['staff', 'admin', 'super_admin'],

        /** Minutes after scheduled shift start before check-in counts as late. PENDING. */
        'attendance.late_after_minutes' => 10,
    ],

    /** Setting groups and the roles that may change them (BRD §14). */
    'setting_groups' => [
        'pricing' => ['super_admin', 'admin'],
        'refund' => ['super_admin', 'admin'],
        'notifications' => ['super_admin', 'admin'],
        'safety' => ['super_admin', 'admin'],
        'attendance' => ['super_admin', 'admin'],
        'exit' => ['super_admin'],
        'reminders' => ['super_admin', 'admin'],
        'anpr' => ['super_admin', 'admin'],
    ],

    /*
    | ANPR infrastructure (not runtime-editable). Everything runs on the local machine / LAN.
    */
    'anpr' => [
        /** Local Python recognition service (loopback or LAN only). */
        'service_url' => env('PARKING_ANPR_SERVICE_URL', 'http://127.0.0.1:8765'),
        'service_token' => env('PARKING_ANPR_SERVICE_TOKEN'),
        /** Camera stream hosts must be inside these networks (IPv4 CIDR). Loopback/link-local are never allowed. */
        'allowed_camera_networks' => array_filter(explode(',', env('PARKING_ANPR_CAMERA_NETWORKS', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))),
        /** Plain hostnames (no IP) allowed for camera streams, e.g. "cam-entry-1.local". */
        'allowed_camera_hostnames' => array_filter(explode(',', env('PARKING_ANPR_CAMERA_HOSTNAMES', ''))),
        'image_disk' => env('PARKING_ANPR_IMAGE_DISK', 'local'),
        'image_max_kilobytes' => 4096,
        'image_mimes' => ['jpg', 'jpeg', 'png'],
        'ingest_rate_per_minute' => 120,
    ],

    /*
    | Parking record filters. "primary" is the PROPOSED interpretation of the stakeholder's
    | "four filter functions" and only drives which filters the UI shows first; every filter in
    | "available" is always accepted by the API. PENDING stakeholder confirmation.
    */
    'session_filters' => [
        'primary' => ['vehicle_category', 'fuel_type', 'status', 'date_range'],
        'available' => ['vehicle_category', 'fuel_type', 'status', 'date_range', 'plate', 'zone', 'package', 'payment_status'],
    ],
];
