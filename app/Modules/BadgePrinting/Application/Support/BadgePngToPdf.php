<?php

namespace App\Modules\BadgePrinting\Application\Support;

/**
 * Minimal single-page PDF that embeds a JPEG raster of the badge PNG (no external deps).
 */
final class BadgePngToPdf
{
    public function convert(string $pngBinary): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $image = @imagecreatefromstring($pngBinary);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }

        ob_start();
        imagejpeg($image, null, 92);
        imagedestroy($image);
        $jpeg = ob_get_clean();

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        return $this->buildPdf($jpeg, $width, $height);
    }

    private function buildPdf(string $jpeg, int $width, int $height): string
    {
        $jpegLen = strlen($jpeg);

        // PDF page in points (1px ≈ 1pt for badge-sized canvases).
        $pageW = $width;
        $pageH = $height;

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] /Contents 4 0 R /Resources << /XObject << /Im0 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length ".(strlen("q\n{$pageW} 0 0 {$pageH} 0 0 cm\n/Im0 Do\nQ\n"))." >>\nstream\nq\n{$pageW} 0 0 {$pageH} 0 0 cm\n/Im0 Do\nQ\nendstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$jpegLen} >>\nstream\n{$jpeg}\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPos = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
