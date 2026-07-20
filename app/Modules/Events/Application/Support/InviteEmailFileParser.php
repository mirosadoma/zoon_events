<?php

namespace App\Modules\Events\Application\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

final class InviteEmailFileParser
{
    /**
     * @return list<string>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if ($path === false) {
            return [];
        }

        $emails = match ($extension) {
            'csv', 'txt' => $this->fromDelimited($path),
            'xlsx' => $this->fromXlsx($path),
            default => $this->fromDelimited($path),
        };

        return collect($emails)
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function fromDelimited(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $emails = [];
        $rowIndex = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            $value = trim((string) ($row[0] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($rowIndex === 1 && strcasecmp($value, 'email') === 0) {
                continue;
            }

            $emails[] = $value;
        }

        fclose($handle);

        return $emails;
    }

    /** @return list<string> */
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

        $emails = [];

        if (preg_match_all('/<c r="A(\d+)"[^>]*>(.*?)<\/c>/s', $sheetXml, $cells, PREG_SET_ORDER) > 0) {
            foreach ($cells as $cell) {
                $row = (int) $cell[1];
                $inner = $cell[2];
                $text = '';

                if (preg_match('/t="inlineStr".*?<t[^>]*>(.*?)<\/t>/s', $inner, $inline) === 1) {
                    $text = html_entity_decode(strip_tags($inline[1]), ENT_QUOTES | ENT_XML1);
                } elseif (preg_match('/t="s".*?<v>(.*?)<\/v>/s', $inner, $sharedRef) === 1) {
                    $text = (string) ($shared[(int) $sharedRef[1]] ?? '');
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $value) === 1) {
                    $text = html_entity_decode((string) $value[1], ENT_QUOTES | ENT_XML1);
                }

                $text = trim($text);
                if ($text === '' || ($row === 1 && strcasecmp($text, 'email') === 0)) {
                    continue;
                }

                $emails[] = $text;
            }
        }

        return $emails;
    }
}
