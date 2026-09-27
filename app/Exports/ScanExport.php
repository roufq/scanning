<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ScanExport implements Export, WithMultipleSheets
{
    /**
     * @param  array{summary: list<array{0: string, 1: string|int}>, findings: list<array{row: list<string>}>, screenshots: list<list<string|int|float|null>>}  $report
     */
    public function __construct(protected array $report) {}

    /**
     * @return list<ReportSheet>
     */
    public function sheets(): array
    {
        return [
            new ReportSheet('Ringkasan', ['Keterangan', 'Nilai'], $this->report['summary']),
            new ReportSheet(
                'Temuan',
                ['Waktu', 'Jenis', 'Keterangan', 'File', 'Pembanding'],
                array_column($this->report['findings'], 'row'),
            ),
            new ReportSheet(
                'Semua screenshot',
                ['Waktu', 'Sumber waktu', 'File', 'Jam di layar', 'Status', 'Perubahan layar (%)', 'Grup mirip', 'Error'],
                $this->report['screenshots'],
            ),
        ];
    }
}
