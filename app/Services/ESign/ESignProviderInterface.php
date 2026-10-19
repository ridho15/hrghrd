<?php

namespace App\Services\ESign;

use App\Models\DocumentInstance;
use App\Models\SigningRequest;

interface ESignProviderInterface
{
    /**
     * Create a signing request for a document.
     */
    public function createSigningRequest(DocumentInstance $document, array $signerData): SigningRequest;

    /**
     * Complete and verify signature with audit trail.
     */
    public function executeSignature(SigningRequest $request, array $signatureData): array;

    /**
     * Generate or fetch immutable evidence pack / audit certificate.
     */
    public function generateEvidencePack(SigningRequest $request): string;
}
