<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;

class AttendancePdf
{
    /** @param array<string, mixed> $filters */
    public function generate(array $filters): string
    {
        $analytics = app(AttendanceAnalytics::class);
        $analytics->validate($filters);
        // Keep synchronous PDF rendering bounded; CSV remains streaming for larger reports.
        $report = $analytics->summarize($filters, includeDetails: true);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('framework/cache'));
        $options->set('chroot', resource_path('views/reports'));
        $pdf = new Dompdf($options);
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHtml(view('reports.attendance-pdf', [
            'report' => $report, 'filters' => $filters, 'generatedAt' => CarbonImmutable::now('UTC'),
            'employee' => empty($filters['employeeId']) ? null : User::find($filters['employeeId']),
        ])->render());
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans') ?? 'Helvetica';
        $pdf->getCanvas()->page_text(38, 570, 'Attendify | Confidential workforce report', $font, 8, [0.4, 0.4, 0.4]);
        $pdf->getCanvas()->page_text(740, 570, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);

        return $pdf->output();
    }
}
