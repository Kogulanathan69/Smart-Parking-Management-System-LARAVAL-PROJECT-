<?php

namespace App\Support\Frontend;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar navigation per role. Roles here only decide what is SHOWN; every page route and API
 * call is still authorised server-side by the `role:` middleware. Items whose route is not
 * registered are dropped, so the menu can never contain a dead link.
 */
final class Navigation
{
    private const OPS = [User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN];

    private const MANAGE = [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN];

    private const REPORTS = [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR];

    private const AUDIT = [User::ROLE_SUPER_ADMIN, User::ROLE_AUDITOR];

    /** @return list<array{label: string, items: list<array{label: string, route: string, icon: string}>}> */
    public static function for(User $user): array
    {
        $groups = [
            ['label' => 'Dashboard', 'items' => [
                ['Business Overview', self::dashboardRoute($user), 'home', User::ROLES],
            ]],
            ['label' => 'Parking Operations', 'items' => [
                ['Check-In', 'ops.check-in', 'arrow-in', self::OPS],
                ['Check-Out', 'ops.check-out', 'arrow-out', self::OPS],
                ['ANPR review queue', 'ops.anpr.queue', 'camera', self::OPS],
                ['Parked vehicles', 'ops.sessions', 'car', self::OPS],
                ['Expiring & overstay', 'ops.expiring', 'clock', self::OPS],
                ['Availability', 'ops.availability', 'grid', self::OPS],
                ['Vehicle register', 'ops.vehicles', 'list', self::OPS],
                ['Reservations', 'ops.reservations', 'bookmark', self::OPS],
            ]],
            ['label' => 'Safety & Support', 'items' => [
                ['Safety tasks', 'ops.inspections', 'shield', self::OPS],
                ['Report incident', 'ops.incidents.create', 'alert', self::OPS],
                ['Incidents', 'manage.incidents', 'shield', self::MANAGE],
                ['Customer support', 'manage.complaints', 'chat', self::MANAGE],
                ['My shifts', 'ops.my-shifts', 'calendar', [User::ROLE_STAFF]],
            ]],
            ['label' => 'Management', 'items' => [
                ['Users', 'manage.users', 'users', self::MANAGE],
                ['Zones & slots', 'manage.zones', 'grid', self::MANAGE],
                ['Vehicle categories', 'manage.categories', 'tag', self::MANAGE],
                ['Packages & rates', 'manage.packages', 'cash', self::MANAGE],
                ['Shifts & roster', 'manage.shifts', 'calendar', self::MANAGE],
                ['Cameras', 'manage.cameras', 'camera', self::MANAGE],
            ]],
            ['label' => 'Finance', 'items' => [
                ['Price calculator', 'manage.pricing', 'calculator', self::MANAGE],
                ['Payment methods', 'manage.payment-methods', 'card', self::MANAGE],
                ['Penalty types', 'manage.penalties', 'alert', self::MANAGE],
                ['Refunds', 'manage.refunds', 'refund', self::MANAGE],
                ['Financial reports', 'reports.financial', 'cash', self::REPORTS],
                ['Payments ledger', 'reports.payments', 'list', self::REPORTS],
            ]],
            ['label' => 'Reports', 'items' => [
                ['Reports overview', 'reports.overview', 'chart', self::REPORTS],
                ['Session ledger', 'reports.sessions', 'car', self::REPORTS],
                ['Calendar', 'reports.calendar', 'calendar', self::REPORTS],
                ['Occupancy', 'reports.occupancy', 'grid', self::REPORTS],
                ['Safety inspections', 'reports.inspections', 'shield', self::REPORTS],
                ['Staff performance', 'reports.staff', 'users', self::REPORTS],
                ['ANPR & cameras', 'reports.anpr', 'camera', self::REPORTS],
            ]],
            ['label' => 'Audit & System', 'items' => [
                ['Audit trail', 'audit.logs', 'key', self::AUDIT],
                ['Audit findings', 'audit.findings', 'flag', self::AUDIT],
                ['ANPR corrections', 'audit.corrections', 'camera', self::AUDIT],
                ['Recognition history', 'audit.anpr-events', 'list', [User::ROLE_AUDITOR]],
                ['Roles & permissions', 'manage.roles', 'key', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_AUDITOR]],
                ['Settings', 'manage.settings', 'cog', self::MANAGE],
                ['System status', 'manage.system', 'pulse', self::MANAGE],
            ]],
            ['label' => 'My parking', 'items' => [
                ['Active parking', 'my.active', 'car', [User::ROLE_CUSTOMER]],
                ['My vehicles', 'my.vehicles', 'list', [User::ROLE_CUSTOMER]],
                ['Parking info & prices', 'my.parking-info', 'grid', [User::ROLE_CUSTOMER]],
                ['History', 'my.history', 'clock', [User::ROLE_CUSTOMER]],
                ['Receipts', 'my.receipts', 'receipt', [User::ROLE_CUSTOMER]],
                ['Credit balance', 'my.credits', 'cash', [User::ROLE_CUSTOMER]],
                ['Support', 'my.support', 'chat', [User::ROLE_CUSTOMER]],
            ]],
            ['label' => 'Account', 'items' => [
                ['Notifications', 'notifications', 'bell', User::ROLES],
                ['My account', 'account', 'user', User::ROLES],
            ]],
        ];

        $out = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] as [$label, $route, $icon, $roles]) {
                if ($route && in_array($user->role, $roles, true) && Route::has($route)) {
                    $items[] = ['label' => $label, 'route' => $route, 'icon' => $icon];
                }
            }
            if ($items) {
                $out[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $out;
    }

    public static function dashboardRoute(User $user): string
    {
        return match ($user->role) {
            User::ROLE_SUPER_ADMIN => 'dashboard.super-admin',
            User::ROLE_ADMIN => 'dashboard.admin',
            User::ROLE_STAFF => 'dashboard.staff',
            User::ROLE_AUDITOR => 'dashboard.auditor',
            default => 'dashboard.customer',
        };
    }
}
