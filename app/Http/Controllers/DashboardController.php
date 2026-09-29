<?php

namespace App\Http\Controllers;

use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\Screenshot;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'overview' => $team ? $this->overview($team) : null,
        ]);
    }

    /**
     * Summarize the team's recent screenshot scans for the home page.
     *
     * @return array{
     *     stats: array{scansThisMonth: int, screenshotsThisMonth: int, findingsThisMonth: int, runningScans: int},
     *     recentScans: list<array<string, mixed>>,
     *     employeesToReview: list<array{name: string, findings: int, scans: int}>,
     * }
     */
    private function overview(Team $team): array
    {
        $monthStart = now()->startOfMonth();
        $scansThisMonth = $team->scans()->where('created_at', '>=', $monthStart);

        return [
            'stats' => [
                'scansThisMonth' => (clone $scansThisMonth)->count(),
                'screenshotsThisMonth' => Screenshot::whereIn('scan_id', (clone $scansThisMonth)->select('id'))->count(),
                'findingsThisMonth' => ScanFinding::whereIn('scan_id', (clone $scansThisMonth)->select('id'))
                    ->where('type', '!=', FindingType::TimeGap)
                    ->count(),
                'runningScans' => $team->scans()->whereIn('status', [ScanStatus::Queued, ScanStatus::Processing])->count(),
            ],
            'recentScans' => array_values($team->scans()
                ->with('employee:id,name')
                ->withCount(['screenshots', 'findings'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Scan $scan) => [
                    'id' => $scan->id,
                    'employee' => $scan->employee->name,
                    'workDate' => $scan->work_date?->toDateString() ?? $scan->created_at?->toDateString(),
                    'status' => $scan->status->value,
                    'statusLabel' => $scan->status->label(),
                    'screenshotsCount' => $scan->screenshots_count,
                    'findingsCount' => $scan->findings_count,
                ])
                ->all()),
            'employeesToReview' => array_values(Employee::query()
                ->where('team_id', $team->id)
                ->withCount([
                    'scans' => fn ($query) => $query->where('created_at', '>=', now()->subDays(30)),
                ])
                ->addSelect(['findings' => ScanFinding::query()
                    ->selectRaw('count(*)')
                    ->join('scans', 'scans.id', '=', 'scan_findings.scan_id')
                    ->whereColumn('scans.employee_id', 'employees.id')
                    ->where('scans.created_at', '>=', now()->subDays(30))
                    ->where('scan_findings.type', '!=', FindingType::TimeGap->value),
                ])
                ->orderByDesc('findings')
                ->limit(5)
                ->get()
                ->filter(fn (Employee $employee) => (int) $employee->getAttribute('findings') > 0)
                ->map(fn (Employee $employee) => [
                    'name' => $employee->name,
                    'findings' => (int) $employee->getAttribute('findings'),
                    'scans' => (int) $employee->getAttribute('scans_count'),
                ])
                ->all()),
        ];
    }
}
