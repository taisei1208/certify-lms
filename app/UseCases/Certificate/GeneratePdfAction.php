<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * 修了証PDFを生成し、プライベートストレージへ保存するAction。
 *
 * Certificateレコードの作成は行わず、既に作成されたCertificateを受け取り、
 * `pdf_path`に指定されたパスへPDFファイルを保存する。
 */
final class GeneratePdfAction
{
    public function __invoke(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $temporaryDirectory = storage_path('app/mpdf');
        File::ensureDirectoryExists($temporaryDirectory);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'tempDir' => $temporaryDirectory,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font_size' => 11,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetTitle('修了証');
        $mpdf->SetAuthor('Certify LMS');
        $mpdf->SetSubject($certificate->certification->name.' 修了証');

        $html = view('certificates.pdf', compact('certificate'))->render();
        $mpdf->WriteHTML($html);

        $pdf = $mpdf->Output('', Destination::STRING_RETURN);

        $stored = Storage::disk('private')->put($certificate->pdf_path, $pdf);

        if (! $stored) {
            throw new RuntimeException('修了証PDFの保存に失敗しました。');
        }
    }
}
