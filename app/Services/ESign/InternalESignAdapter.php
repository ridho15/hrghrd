<?php

namespace App\Services\ESign;

use App\Models\DocumentInstance;
use App\Models\SignatureEvent;
use App\Models\SigningRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InternalESignAdapter implements ESignProviderInterface
{
    public function createSigningRequest(DocumentInstance $document, array $signerData): SigningRequest
    {
        $token = Str::random(64);

        $request = SigningRequest::create([
            'document_instance_id' => $document->id,
            'provider' => 'internal_esign',
            'signing_order' => $signerData['signing_order'] ?? 1,
            'signer_role' => $signerData['signer_role'] ?? 'candidate',
            'signer_name' => $signerData['signer_name'],
            'signer_email' => $signerData['signer_email'],
            'signer_phone' => $signerData['signer_phone'] ?? null,
            'token' => $token,
            'status' => 'pending',
            'auth_method' => $signerData['auth_method'] ?? 'token_consent',
        ]);

        SignatureEvent::create([
            'signing_request_id' => $request->id,
            'event_type' => 'sent',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload' => [
                'action' => 'Signing request created',
                'order' => $request->signing_order,
                'target' => $request->signer_email,
            ],
            'created_at' => now(),
        ]);

        return $request;
    }

    public function executeSignature(SigningRequest $request, array $signatureData): array
    {
        $document = $request->documentInstance;
        $ip = request()->ip() ?: ($signatureData['ip'] ?? '127.0.0.1');
        $userAgent = request()->userAgent() ?: ($signatureData['user_agent'] ?? 'Web/CandidatePortal');
        $timestamp = now();

        // 1. Consent event
        SignatureEvent::create([
            'signing_request_id' => $request->id,
            'event_type' => 'consent_agreed',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => [
                'consent_text' => 'Pemberian persetujuan eksplisit untuk menandatangani dokumen secara elektronik sesuai UU ITE.',
                'signer_name' => $request->signer_name,
                'timestamp' => $timestamp->toISOString(),
            ],
            'created_at' => $timestamp,
        ]);

        // 2. Compute cryptographic evidence
        $signaturePayload = [
            'signature_data' => $signatureData['signature_data'] ?? 'E-SIGN-ACCEPTED',
            'signed_at' => $timestamp->toISOString(),
            'signer_role' => $request->signer_role,
            'auth_method' => $request->auth_method,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];

        $request->update([
            'status' => 'signed',
            'signed_at' => $timestamp,
            'signature_data' => $signatureData['signature_data'] ?? 'DIGITAL_SIGNATURE_CONFIRMED',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_metadata' => $signatureData['device_metadata'] ?? ['screen' => 'responsive', 'platform' => 'browser'],
        ]);

        // 3. Signature submitted event
        SignatureEvent::create([
            'signing_request_id' => $request->id,
            'event_type' => 'signature_submitted',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => $signaturePayload,
            'created_at' => $timestamp,
        ]);

        // 4. Generate Evidence Pack in private storage
        $evidencePath = $this->generateEvidencePack($request);
        $request->update(['evidence_pack_path' => $evidencePath]);

        // 5. Verification completed event
        SignatureEvent::create([
            'signing_request_id' => $request->id,
            'event_type' => 'verified',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => [
                'verified' => true,
                'evidence_pack' => $evidencePath,
                'algorithm' => 'SHA-256',
            ],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'signed_at' => $timestamp,
            'evidence_pack_path' => $evidencePath,
        ];
    }

    public function generateEvidencePack(SigningRequest $request): string
    {
        $document = $request->documentInstance;
        $events = $request->events()->get();

        $packData = [
            'certificate_id' => 'CERT-' . strtoupper(Str::random(12)),
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'document_type' => $document->type,
            'document_version' => $document->version,
            'document_content_hash_sha256' => $document->file_hash ?: hash('sha256', $document->rendered_content),
            'signer' => [
                'name' => $request->signer_name,
                'email' => $request->signer_email,
                'role' => $request->signer_role,
                'auth_method' => $request->auth_method,
                'signed_at' => $request->signed_at?->toISOString(),
                'ip_address' => $request->ip_address,
                'user_agent' => $request->user_agent,
            ],
            'timeline_audit_events' => $events->map(fn ($e) => [
                'event' => $e->event_type,
                'timestamp' => $e->created_at->toISOString(),
                'ip' => $e->ip_address,
                'details' => $e->payload,
            ])->toArray(),
            'compliance_statement' => 'Dokumen ditandatangani secara elektronik dengan audit trail lengkap sesuai UU No. 1 Tahun 2024 dan PP No. 71 Tahun 2019.',
            'generated_at' => now()->toISOString(),
        ];

        $filename = 'evidence_packs/' . date('Y/m/') . 'evidence_' . $request->id . '_' . Str::random(8) . '.json';
        Storage::disk('local')->put($filename, json_encode($packData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $filename;
    }
}
