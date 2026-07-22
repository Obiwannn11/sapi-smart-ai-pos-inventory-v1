<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman jejak audit — read-only, digerbang `platform.can:audit_logs`.
 *
 * Log yang hanya bisa dibaca lewat query database tidak berfungsi sebagai alat
 * pertanggungjawaban ke klien. Halaman ini yang membuat janji "setiap akses
 * tercatat" bisa benar-benar ditunjukkan.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', Rule::in([PlatformAuditLog::SEVERITY_ROUTINE, PlatformAuditLog::SEVERITY_SENSITIVE])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = PlatformAuditLog::query()
            ->with('platformUser:id,name')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'severity' => $log->severity,
                // Akun bisa sudah dihapus (FK nullOnDelete) — jejaknya sengaja
                // tetap ada, dan justru itu gunanya.
                'actor' => $log->platformUser?->name ?? 'Akun telah dihapus',
                'subject' => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null,
                'meta' => $log->meta,
                'ip' => $log->ip,
                'at' => $log->created_at?->format('Y-m-d H:i'),
            ]);

        // Membuka jejak audit adalah akses yang ikut dicatat — tapi rutin, jadi
        // dideduplikasi agar halaman ini tidak memenuhi dirinya sendiri.
        PlatformAuditLog::recordRoutine('audit_logs.index');

        return Inertia::render('Platform/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            // Daftar aksi diambil dari data yang benar-benar ada, bukan katalog
            // statis: aksi baru muncul di filter tanpa perlu didaftarkan.
            'actions' => PlatformAuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'retention' => config('platform-audit.retention_days'),
        ]);
    }
}
