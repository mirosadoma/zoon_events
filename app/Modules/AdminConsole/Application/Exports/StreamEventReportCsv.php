<?php

namespace App\Modules\AdminConsole\Application\Exports;

use App\Modules\AdminConsole\ViewModels\Reports\EventReportViewModel;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamEventReportCsv
{
    public function __construct(private EventReportViewModel $viewModel) {}

    public function execute(Event $event, string $tenantId, string $locale = 'en'): StreamedResponse
    {
        $payload = $this->viewModel->make($event, $tenantId);
        $report = $payload['report'];
        $filename = 'event-report-'.$event->id.'.csv';

        return new StreamedResponse(
            function () use ($report, $locale): void {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");

                $this->section($handle, $locale === 'ar' ? 'الملخص' : 'Summary');
                fputcsv($handle, [$locale === 'ar' ? 'المقياس' : 'Metric', $locale === 'ar' ? 'القيمة' : 'Value']);
                foreach ((array) ($report['summary'] ?? []) as $key => $metric) {
                    if (! is_array($metric) || ! array_key_exists('value', $metric)) {
                        if ($key === 'currency') {
                            fputcsv($handle, [$this->escapeFormula((string) $key), $this->escapeFormula((string) $metric)]);
                        }
                        continue;
                    }
                    $value = ($metric['available'] ?? false) ? (string) ($metric['value'] ?? '') : '';
                    fputcsv($handle, [$this->escapeFormula((string) $key), $this->escapeFormula($value)]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'المسار' : 'Funnel');
                fputcsv($handle, ['key', 'count', 'conversion_from_previous']);
                foreach ((array) ($report['funnel'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['key'] ?? '')),
                        (int) ($row['count'] ?? 0),
                        $row['conversion_from_previous'] ?? '',
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'الطلبات حسب الحالة' : 'Orders by status');
                fputcsv($handle, ['status', 'count', 'revenue_minor']);
                foreach ((array) ($report['orders_by_status'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['status'] ?? '')),
                        (int) ($row['count'] ?? 0),
                        (int) ($row['revenue_minor'] ?? 0),
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'الفئات' : 'Categories');
                fputcsv($handle, ['name', 'attendees', 'checked_in', 'checkin_rate', 'revenue_minor']);
                foreach ((array) ($report['categories'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['name'] ?? '')),
                        (int) ($row['attendees'] ?? 0),
                        (int) ($row['checked_in'] ?? 0),
                        $row['checkin_rate'] ?? '',
                        (int) ($row['revenue_minor'] ?? 0),
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types');
                fputcsv($handle, ['name', 'attendees', 'checked_in', 'checkin_rate', 'revenue_minor']);
                foreach ((array) ($report['ticket_types'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['name'] ?? '')),
                        (int) ($row['attendees'] ?? 0),
                        (int) ($row['checked_in'] ?? 0),
                        $row['checkin_rate'] ?? '',
                        (int) ($row['revenue_minor'] ?? 0),
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'الحضور حسب اليوم' : 'Check-ins by day');
                fputcsv($handle, ['date', 'accepted_scans', 'unique_attendees']);
                foreach ((array) ($report['checkins_by_day'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['date'] ?? '')),
                        (int) ($row['accepted_scans'] ?? 0),
                        (int) ($row['unique_attendees'] ?? 0),
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'الحضور حسب الساعة' : 'Check-ins by hour');
                fputcsv($handle, ['hour', 'accepted_scans', 'unique_attendees']);
                foreach ((array) ($report['checkins_by_hour'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['hour'] ?? '')),
                        (int) ($row['accepted_scans'] ?? 0),
                        (int) ($row['unique_attendees'] ?? 0),
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'أسباب الرفض' : 'Reject reasons');
                fputcsv($handle, ['reason', 'count', 'percent']);
                foreach ((array) ($report['top_reject_reasons'] ?? []) as $row) {
                    fputcsv($handle, [
                        $this->escapeFormula((string) ($row['reason'] ?? '')),
                        (int) ($row['count'] ?? 0),
                        $row['percent'] ?? '',
                    ]);
                }

                $this->blank($handle);
                $this->section($handle, $locale === 'ar' ? 'الأماكن' : 'Venues');
                fputcsv($handle, ['venue', 'registered', 'checked_in', 'checkin_rate', 'latitude', 'longitude']);
                foreach ((array) ($report['venue_markers'] ?? []) as $row) {
                    $name = is_array($row['venue_name'] ?? null)
                        ? (string) (($row['venue_name']['en'] ?? '') ?: ($row['venue_name']['ar'] ?? ''))
                        : '';
                    fputcsv($handle, [
                        $this->escapeFormula($name),
                        (int) ($row['registered'] ?? 0),
                        (int) ($row['checked_in'] ?? 0),
                        $row['checkin_rate'] ?? '',
                        $row['latitude'] ?? '',
                        $row['longitude'] ?? '',
                    ]);
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /** @param resource $handle */
    private function section($handle, string $title): void
    {
        fputcsv($handle, [$title]);
    }

    /** @param resource $handle */
    private function blank($handle): void
    {
        fputcsv($handle, []);
    }

    private function escapeFormula(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'{$value}";
        }

        return $value;
    }
}
