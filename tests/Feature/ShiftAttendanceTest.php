<?php

use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedParking();
    $this->admin = userWithRole(User::ROLE_ADMIN);
    $this->staff = userWithRole(User::ROLE_STAFF);
});

function assignShift(User $admin, User $staff, string $shiftCode, string $date): int
{
    return test()->actingAs($admin)->postJson('/api/v1/admin/shift-assignments', [
        'shift_id' => Shift::where('code', $shiftCode)->value('id'), 'user_id' => $staff->id, 'shift_date' => $date,
    ])->assertCreated()->json('data.id');
}

it('assigns shifts only to active staff and rejects duplicates', function () {
    assignShift($this->admin, $this->staff, 'morning', '2026-11-02');

    $this->actingAs($this->admin)->postJson('/api/v1/admin/shift-assignments', [
        'shift_id' => Shift::where('code', 'morning')->value('id'), 'user_id' => $this->staff->id, 'shift_date' => '2026-11-02',
    ])->assertStatus(409);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/shift-assignments', [
        'shift_id' => Shift::where('code', 'morning')->value('id'), 'user_id' => userWithRole(User::ROLE_CUSTOMER)->id, 'shift_date' => '2026-11-02',
    ])->assertUnprocessable()->assertJsonPath('error_code', 'invalid_staff');
});

it('records present and late check-ins in local business time', function () {
    $id = assignShift($this->admin, $this->staff, 'morning', '2026-11-02');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 06:05', 'Asia/Colombo'));
    $this->actingAs($this->staff)->postJson("/api/v1/staff/shifts/{$id}/check-in")->assertCreated()->assertJsonPath('data.status', 'present');
    $this->actingAs($this->staff)->postJson("/api/v1/staff/shifts/{$id}/check-in")->assertStatus(409);

    $other = userWithRole(User::ROLE_STAFF);
    $id2 = assignShift($this->admin, $other, 'morning', '2026-11-02');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 06:30', 'Asia/Colombo'));
    $this->actingAs($other)->postJson("/api/v1/staff/shifts/{$id2}/check-in")->assertCreated()->assertJsonPath('data.status', 'late');

    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'Asia/Colombo'));
    $this->actingAs($other)->postJson("/api/v1/staff/shifts/{$id2}/check-out")->assertOk()->assertJsonPath('data.status', 'late');
    $this->actingAs($this->staff)->postJson("/api/v1/staff/shifts/{$id}/check-out")->assertOk()->assertJsonPath('data.status', 'left_early');
});

it('handles night shifts that cross midnight', function () {
    $id = assignShift($this->admin, $this->staff, 'night', '2026-11-02');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 02:00', 'Asia/Colombo'));

    $this->actingAs($this->staff)->postJson("/api/v1/staff/shifts/{$id}/check-in")->assertCreated()->assertJsonPath('data.status', 'late');
});

it('refuses check-in outside the shift window or for someone else', function () {
    $id = assignShift($this->admin, $this->staff, 'morning', '2026-11-02');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 20:00', 'Asia/Colombo'));
    $this->actingAs($this->staff)->postJson("/api/v1/staff/shifts/{$id}/check-in")->assertUnprocessable()->assertJsonPath('error_code', 'outside_shift_window');

    $this->travelTo(CarbonImmutable::parse('2026-11-02 06:00', 'Asia/Colombo'));
    $this->actingAs(userWithRole(User::ROLE_STAFF))->postJson("/api/v1/staff/shifts/{$id}/check-in")->assertForbidden();
});

it('approves leave with a replacement and reports coverage gaps', function () {
    $id = assignShift($this->admin, $this->staff, 'morning', '2026-11-02');
    $replacement = userWithRole(User::ROLE_STAFF);

    $gaps = $this->actingAs($this->admin)->getJson('/api/v1/admin/shift-coverage?date=2026-11-02')->json('gaps');
    expect(collect($gaps)->pluck('shift')->all())->toBe(['evening', 'night']);

    $this->actingAs($this->admin)->postJson("/api/v1/admin/shift-assignments/{$id}/leave", ['replacement_user_id' => $replacement->id])
        ->assertOk()->assertJsonPath('assignment.status', 'replaced')->assertJsonPath('replacement.user.id', $replacement->id);
});

it('reports staff performance', function () {
    recordEntry($this->staff);

    $row = collect($this->actingAs($this->admin)->getJson('/api/v1/reports/staff-performance')->assertOk()->json('data'))->firstWhere('user_id', $this->staff->id);
    expect($row['entries'])->toBe(1);
});
