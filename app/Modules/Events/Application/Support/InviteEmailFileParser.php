<?php

namespace App\Modules\Events\Application\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

final class InviteEmailFileParser
{
    /**
     * @return list<array{email: string, name?: string}>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if ($path === false) {
            return [];
        }

        $invitees = match ($extension) {
            'csv', 'txt' => $this->fromDelimited($path),
            'xlsx' => $this->fromXlsx($path),
            default => $this->fromDelimited($path),
        };

        return collect($invitees)
            ->map(function ($invitee): array {
                $email = is_array($invitee) ? ($invitee['email'] ?? '') : (string) $invitee;
                $name = is_array($invitee) ? ($invitee['name'] ?? null) : null;

                return [
                    'email' => strtolower(trim($email)),
                    'name' => $name !== null && trim($name) !== '' ? trim($name) : null,
                ];
            })
            ->filter(fn (array $invitee): bool => filter_var($invitee['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique('email')
            ->values()
            ->all();
    }

    /** @return list<array{name?: string, email: string}> */
    private function fromDelimited(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $invitees = [];
        $rowIndex = 0;
        $hasHeader = false;
        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));

            // Detect header row
            if ($rowIndex === 1 && (strcasecmp($col0, 'name') === 0 || strcasecmp($col0, 'email') === 0 || strcasecmp($col1, 'email') === 0)) {
                $hasHeader = true;
                continue;
            }

            // If we have 2 columns, assume: Name, Email
            if ($col1 !== '') {
                $invitees[] = ['name' => $col0, 'email' => $col1];
            } elseif ($col0 !== '') {
                // Single column: treat as email only
                $invitees[] = ['email' => $col0];
            }
        }

        fclose($handle);

        return $invitees;
    }

    /** @return list<array{name?: string, email: string}> */
    private function fromXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && $sharedXml !== '') {
            if (preg_match_all('/<si[^>]*>.*?<t[^>]*>(.*?)<\/t>/s', $sharedXml, $matches) > 0) {
                foreach ($matches[1] as $text) {
                    $shared[] = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        // Parse column A (Name) and column B (Email)
        $rows = [];
        if (preg_match_all('/<c r="([AB])(\d+)"[^>]*>(.*?)<\/c>/s', $sheetXml, $cells, PREG_SET_ORDER) > 0) {
            foreach ($cells as $cell) {
                $col = $cell[1];
                $row = (int) $cell[2];
                $inner = $cell[3];
                $text = '';

                if (preg_match('/t="inlineStr".*?<t[^>]*>(.*?)<\/t>/s', $inner, $inline) === 1) {
                    $text = html_entity_decode(strip_tags($inline[1]), ENT_QUOTES | ENT_XML1);
                } elseif (preg_match('/t="s".*?<v>(.*?)<\/v>/s', $inner, $sharedRef) === 1) {
                    $text = (string) ($shared[(int) $sharedRef[1]] ?? '');
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $value) === 1) {
                    $text = html_entity_decode((string) $value[1], ENT_QUOTES | ENT_XML1);
                }

                if (! isset($rows[$row])) {
                    $rows[$row] = [];
                }
                $rows[$row][$col] = trim($text);
            }
        }

        ksort($rows);
        $hasHeader = isset($rows[1]) && (
            (isset($rows[1]['A']) && strcasecmp($rows[1]['A'], 'name') === 0) ||
            (isset($rows[1]['B']) && strcasecmp($rows[1]['B'], 'email') === 0)
        );

        $invitees = [];
        foreach ($rows as $rowNum => $columns) {
            if ($rowNum === 1 && $hasHeader) {
                continue;
            }

            $colA = $columns['A'] ?? '';
            $colB = $columns['B'] ?? '';

            // If we have both columns: Name, Email
            if ($colB !== '') {
                $invitees[] = ['name' => $colA, 'email' => $colB];
            } elseif ($colA !== '') {
                // Single column: treat as email only
                $invitees[] = ['email' => $colA];
            }
        }

        return $invitees;
    }
}
