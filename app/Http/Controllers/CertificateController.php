<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\UseCases\Certificate\DownloadAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function download(Certificate $certificate, DownloadAction $action): StreamedResponse
    {
        $this->authorize('download', $certificate);

        return $action($certificate);
    }
}
