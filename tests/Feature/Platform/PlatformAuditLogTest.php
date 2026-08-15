<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

// ── Gerbang halaman ──────────────────────────────────────────────────────────
test('audit log page needs its own module', function () {
    actingAs(PlatformUser::factory()->withModules(['tenants'])->create(), 'platform');
    get('/platform/audit-logs')->assertStatus(403);

    actingAs(PlatformUser::factory()->withModules(['audit_logs'])->create(), 'platform');
    get('/platform/audit-logs')->assertStatus(200);
});

// ── Deduplikasi kejadian rutin ───────────────────────────────────────────────
test('opening a list repeatedly produces a single routine entry', function () {
    actingAs(PlatformUser::factory()->withModules(['tenants'])->create(), 'platform');

    foreach (range(1, 5) as $ignored) {
        get('/platform/tenants');
    }

    expect(PlatformAuditLog::where('action', 'tenants.index')->count())->toBe(1);
});

test('a routine entry is written again once the dedupe window passes', function () {
    actingAs(PlatformUser::factory()->withModules(['tenants'])->create(), 'platform');

    get('/platform/tenants');

    // Majukan waktu melewati jendela deduplikasi.
    $this->travel(config('platform-audit.routine_dedupe_minutes') + 1)->minutes();
    get('/platform/tenants');

    expect(PlatformAuditLog::where('action', 'tenants.index')->count())->toBe(2);
});

test('routine dedupe is per actor, not global', function () {
    $first = PlatformUser::factory()->withModules(['tenants'])->create();
    $second = PlatformUser::factory()->withModules(['tenants'])->create();

    actingAs($first, 'platform');
    get('/platform/tenants');

    // Akun lain yang membuka daftar yang sama harus tetap tercatat — kalau
    // tidak, jejaknya justru menyembunyikan siapa yang mengakses.
    actingAs($second, 'platform');
    get('/platform/tenants');

    expect(PlatformAuditLog::where('action', 'tenants.index')->count())->toBe(2);
});

// ── Kejadian sensitif tak pernah dideduplikasi ───────────────────────────────
test('sensitive events are never deduplicated', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    foreach (range(1, 3) as $i) {
        post('/platform/users', [
            'name' => "Staf {$i}",
            'email' => "staf{$i}@sapi.test",
            'password' => 'rahasia123',
            'modules' => ['tenants'],
        ]);
    }

    expect(PlatformAuditLog::where('action', 'platform_users.create')->count())->toBe(3);
});

test('failed logins are recorded as sensitive', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test', 'password' => 'rahasia123']);

    post('/platform/login', ['email' => 'pemilik@sapi.test', 'password' => 'salah']);

    $log = PlatformAuditLog::where('action', 'login.failed')->first();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
});

// ── Filter ───────────────────────────────────────────────────────────────────
test('logs can be filtered by severity', function () {
    $account = PlatformUser::factory()->withModules(['tenants', 'audit_logs'])->create();
    actingAs($account, 'platform');

    get('/platform/tenants'); // routine
    PlatformAuditLog::record('pricing_rules.update'); // sensitive

    // Jejaknya ditunda ([BL-037]) — penyaringnya eager, barisnya menyusul.
    get('/platform/audit-logs?severity=sensitive')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('logs')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('logs.data', fn ($logs) => collect($logs)->every(fn ($log) => $log['severity'] === 'sensitive'))));
});

test('logs can be filtered by action', function () {
    actingAs(PlatformUser::factory()->withModules(['tenants', 'audit_logs'])->create(), 'platform');

    get('/platform/tenants');
    PlatformAuditLog::record('pricing_rules.update');

    get('/platform/audit-logs?action=pricing_rules.update')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('logs.data', fn ($logs) => count($logs) === 1
                    && $logs[0]['action'] === 'pricing_rules.update')));
});

// ── Jejak bertahan meski akunnya dihapus ─────────────────────────────────────
test('an entry survives deletion of the account that made it', function () {
    $owner = PlatformUser::factory()->owner()->create();
    $staff = PlatformUser::factory()->create();

    actingAs($staff, 'platform');
    PlatformAuditLog::record('pricing_rules.update');

    $staff->delete();

    actingAs($owner, 'platform');
    get('/platform/audit-logs')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('logs.data', fn ($logs) => collect($logs)->contains(
                    fn ($log) => $log['action'] === 'pricing_rules.update' && $log['actor'] === 'Akun telah dihapus'
                ))));
});

// ── Pemangkasan ──────────────────────────────────────────────────────────────

/**
 * `created_at` sengaja tidak fillable (produksi tak pernah menyetelnya manual),
 * jadi di test ia ditulis setelah baris dibuat.
 */
function auditRowAged(string $action, string $severity, int $daysAgo): void
{
    PlatformAuditLog::create(['action' => $action, 'severity' => $severity])
        ->forceFill(['created_at' => now()->subDays($daysAgo)])
        ->save();
}

test('pruning respects a different retention per severity', function () {
    $routineDays = config('platform-audit.retention_days.routine');
    $sensitiveDays = config('platform-audit.retention_days.sensitive');

    auditRowAged('a', 'routine', $routineDays - 1);   // masih dalam retensi
    auditRowAged('b', 'routine', $routineDays + 1);   // lewat → dibuang
    // Umurnya melewati retensi rutin, tapi ia sensitif — harus tetap ada.
    auditRowAged('c', 'sensitive', $routineDays + 1);
    auditRowAged('d', 'sensitive', $sensitiveDays - 1);

    artisan('platform:prune-audit-logs')->assertSuccessful();

    expect(PlatformAuditLog::pluck('action')->sort()->values()->all())->toBe(['a', 'c', 'd']);
});

test('sensitive entries are dropped only after their much longer retention', function () {
    auditRowAged('sangat-lama', 'sensitive', config('platform-audit.retention_days.sensitive') + 1);

    artisan('platform:prune-audit-logs')->assertSuccessful();

    expect(PlatformAuditLog::where('action', 'sangat-lama')->exists())->toBeFalse();
});

test('dry run deletes nothing', function () {
    auditRowAged('lama', 'routine', config('platform-audit.retention_days.routine') + 1);

    artisan('platform:prune-audit-logs --dry-run')->assertSuccessful();

    expect(PlatformAuditLog::where('action', 'lama')->exists())->toBeTrue();
});
