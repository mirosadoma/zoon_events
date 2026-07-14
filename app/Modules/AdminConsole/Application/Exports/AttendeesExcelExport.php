<?php

namespace App\Modules\AdminConsole\Application\Exports;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

final readonly class AttendeesExcelExport
{
    public function __construct(private PersonalDataReader $personalData) {}

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  array<string, string>  $credentialStatuses
     */
    public function download(Collection $attendees, array $credentialStatuses, string $filename): StreamedResponse
    {
        $headers = ['Name', 'Email', 'Phone', 'Check-in status', 'Credential status', 'Locale', 'Registered at'];
        $rows = $attendees->map(function (Attendee $attendee) use ($credentialStatuses): array {
            return [
                $this->personalData->attendeeDisplayName($attendee) ?? '',
                $this->personalData->attendeeEmail($attendee) ?? '',
                $this->personalData->attendeePhone($attendee) ?? '',
                (string) ($attendee->checkin_status ?? 'not_checked_in'),
                $credentialStatuses[$attendee->id] ?? '',
                (string) $attendee->preferred_locale,
                $attendee->registered_at?->toIso8601String() ?? '',
            ];
        })->all();

        $binary = $this->buildXlsx($headers, $rows);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function buildXlsx(array $headers, array $rows): string
    {
        $sheetRows = array_merge([$headers], $rows);
        $sheetXml = $this->sheetXml($sheetRows);

        $tmp = tempnam(sys_get_temp_dir(), 'attendees-xlsx-');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create temporary export file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to open ZIP archive for Excel export.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Attendees" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        if ($binary === false) {
            throw new \RuntimeException('Unable to read Excel export file.');
        }

        return $binary;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $columns) {
            $r = $rowIndex + 1;
            $xml .= '<row r="'.$r.'">';
            foreach ($columns as $columnIndex => $value) {
                $cell = $this->columnLetter($columnIndex).$r;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.$this->escapeXml((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
