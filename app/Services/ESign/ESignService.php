<?php

namespace App\Services\ESign;

use App\Models\DocumentInstance;
use App\Models\SigningRequest;
use App\Support\Audit;
use Illuminate\Support\Facades\Storage;

class ESignService
{
    public function __construct(
        protected InternalESignAdapter $internalAdapter
    ) {}

    public function getAdapter(string $provider = 'internal_esign'): ESignProviderInterface
    {
        return match ($provider) {
            'internal_esign' => $this->internalAdapter,
            default => $this->internalAdapter,
        };
    }

    /**
     * Initiate signing workflow for a document.
     */
    public function initiateDocumentSigning(DocumentInstance $document, array $signers): array
    {
        $requests = [];
        $order = 1;

        foreach ($signers as $signer) {
            $signer['signing_order'] = $order++;
            $adapter = $this->getAdapter($signer['provider'] ?? 'internal_esign');
            $request = $adapter->createSigningRequest($document, $signer);
            $requests[] = $request;
        }

        $document->update([
            'status' => 'menunggu_tanda_tangan',
            'expires_at' => now()->addDays(7),
        ]);

        Audit::record('document_instance', $document->id, 'initiate_signing', 'Dokumen dikirim untuk proses tanda tangan elektronik.');

        return $requests;
    }

    /**
     * Execute a signing step.
     */
    public function processSignature(SigningRequest $request, array $signatureData): array
    {
        abort_if($request->status === 'signed', 422, 'Permintaan tanda tangan ini sudah selesai diproses sebelumnya.');

        $adapter = $this->getAdapter($request->provider);
        $result = $adapter->executeSignature($request, $signatureData);

        $document = $request->documentInstance;

        // Check if all signers of this document have signed
        $pendingCount = $document->signingRequests()->where('status', '!=', 'signed')->count();

        if ($pendingCount === 0) {
            // Update rendered content with signed timestamps
            $finalSnapshot = $document->data_snapshot;
            $finalSnapshot['candidate_signed_at'] = now()->format('d/m/Y H:i:s T');
            $finalSnapshot['company_signed_at'] = now()->format('d/m/Y H:i:s T');
            $finalSnapshot['document_hash'] = hash('sha256', $document->rendered_content);

            $document->update([
                'status' => 'ditandatangani',
                'signed_at' => now(),
                'file_hash' => $finalSnapshot['document_hash'],
                'data_snapshot' => $finalSnapshot,
            ]);

            Audit::record('document_instance', $document->id, 'signed', 'Dokumen telah selesai ditandatangani oleh seluruh pihak.');
        }

        return $result;
    }
}
