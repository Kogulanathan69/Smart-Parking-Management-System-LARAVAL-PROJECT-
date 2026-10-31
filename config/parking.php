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
    ],
];
