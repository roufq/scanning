<?php

namespace App\Http\Controllers\Scans;

use App\Actions\Scans\BuildScanReport;
use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Exports\ScanExport;
use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\Screenshot;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ScanExportController extends Controller
{
    /**
     * How many findings get screenshot evidence in the PDF.
     */
    protected const PDF_EVIDENCE_LIMIT = 30;

    /**
     * Download the scan report as Excel or PDF.
     */
    public function __invoke(Team $current_team, Scan $scan, string $format, BuildScanReport $buildReport): Response
    {
        abort_unless($scan->status === ScanStatus::Completed, 404);

        $report = $buildReport->handle($scan);
        $filename = sprintf(
            'scan-%s-%s',
            Str::slug($scan->employee->name),
            ($scan->work_date ?? $scan->created_at)?->format('Y-m-d'),
        );

        if ($format === 'xlsx') {
            return Excel::download(new ScanExport($report), "{$filename}.xlsx");
        }

        return Pdf::loadView('reports.scan', [
            ...$report,
            'evidence' => $this->evidence($report['findings']),
        ])->download("{$filename}.pdf");
    }

    /**
     * Thumbnails (as data URIs) for the first findings that have visual evidence.
     *
     * @param  list<array{finding: ScanFinding, screenshot: Screenshot|null, row: list<string>}>  $findings
     * @return list<array{time: string, label: string, description: string, current: string, related: string|null}>
     */
    protected function evidence(array $findings): array
    {
        return array_values(collect($findings)
            ->reject(fn (array $item) => $item['finding']->type === FindingType::TimeGap || $item['screenshot'] === null)
            ->take(self::PDF_EVIDENCE_LIMIT)
            ->map(fn (array $item) => [
                'time' => $item['row'][0],
                'label' => $item['row'][1],
                'description' => $item['row'][2],
                'current' => (string) $this->thumbnail($item['screenshot']),
                'related' => $this->thumbnail($item['finding']->relatedScreenshot),
            ])
            ->filter(fn (array $item) => $item['current'] !== '')
            ->all());
    }

    protected function thumbnail(?Screenshot $screenshot): ?string
    {
        $path = $screenshot?->thumbnail_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) Storage::disk('local')->get($path));
    }
}
