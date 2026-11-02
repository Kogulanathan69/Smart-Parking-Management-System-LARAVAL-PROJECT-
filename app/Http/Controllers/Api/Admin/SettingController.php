<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\PaymentMethod;
use App\Models\SettingHistory;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Value validation per setting key.
     *
     * @var array<string, array<int, string>>
     */
    private const RULES = [
        'pricing.grace_minutes' => ['integer', 'min:0', 'max:1440'],
        'pricing.billing_block_minutes' => ['integer', 'min:1', 'max:1440'],
        'pricing.overtime_starts_from' => ['in:package_end,grace_end'],
        'pricing.night_end_time' => ['date_format:H:i'],
        'pricing.early_exit_method' => ['in:none,prorate_daily'],
        'pricing.early_exit_minimum_days' => ['integer', 'min:0', 'max:30'],
        'pricing.tax_basis_points' => ['integer', 'min:0', 'max:10000'],
        'refund.default_resolution' => ['in:credit,refund'],
        'refund.auto_approve_limit_minor' => ['integer', 'min:0'],
        'refund.credit_expiry_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        'safety.inspections_per_day' => ['integer', 'min:1', 'max:24'],
        'safety.require_exit_inspection' => ['boolean'],
        'exit.release_slot_on' => ['in:settlement,gate'],
        'notifications.notify_admins_on_damage' => ['boolean'],
        'attendance.late_after_minutes' => ['integer', 'min:0', 'max:240'],
        'reminders.enabled' => ['boolean'],
        'reminders.before_expiry_minutes' => ['array', 'min:1', 'max:5'],
        'reminders.notify_on_expiry' => ['boolean'],
    ];

    /**
     * Rules for each element of array-valued settings.
     *
     * @var array<string, array<int, string>>
     */
    private const ITEM_RULES = [
        'reminders.before_expiry_minutes' => ['integer', 'min:1', 'max:10080'],
    ];

    public function index(Request $request, SettingsService $settings): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => collect($settings->all())->map(fn ($value, $key) => [
            'key' => $key,
            'value' => $value,
            'editable' => $settings->canEdit($user, $key),
        ])->values()]);
    }

    public function update(UpdateSettingRequest $request, string $key, SettingsService $settings): JsonResponse
    {
        abort_unless(isset(self::RULES[$key]), 404, 'Unknown setting.');
        $value = $request->input('value');
        $rules = ['value' => self::RULES[$key]];
        if (isset(self::ITEM_RULES[$key])) {
            $rules['value.*'] = self::ITEM_RULES[$key];
        }
        $validator = validator(['value' => $value], $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        if (isset(self::ITEM_RULES[$key])) {
            $value = collect($value)->map(fn ($v) => (int) $v)->unique()->sortDesc()->values()->all();
        }
        if (in_array('integer', self::RULES[$key], true) && $value !== null) {
            $value = (int) $value;
        }
        if (in_array('boolean', self::RULES[$key], true)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $settings->set($key, $value, $request->user());

        return response()->json(['key' => $key, 'value' => $settings->get($key)]);
    }

    public function history(): JsonResponse
    {
        return response()->json(['data' => SettingHistory::with('setting:id,key')->latest('created_at')->limit(200)->get()]);
    }

    public function paymentMethods(): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::orderBy('id')->get()]);
    }

    public function upsertPaymentMethod(PaymentMethodRequest $request, AuditLogger $audit): JsonResponse
    {
        $method = PaymentMethod::updateOrCreate(['code' => $request->validated('code')], $request->validated());
        $audit->log('payment_method.saved', $method, [], $method->toArray());

        return response()->json(['data' => $method], $method->wasRecentlyCreated ? 201 : 200);
    }
}
