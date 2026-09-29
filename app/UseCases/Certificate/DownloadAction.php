<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証PDFをprivateストレージからダウンロードする。
 */
final class DownloadAction
{
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($certificate->pdf_path)) {
            abort(404);
        }

        return $disk->download(
            $certificate->pdf_path,
            "certificate-{$certificate->id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
