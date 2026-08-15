<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminStoreOnboardingController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query()
            ->with(['verification', 'owner:id,username,email,phone', 'category:id,name'])
            ->whereHas('verification')
            ->orderByDesc('created_at');

        if ($status = trim((string) $request->query('status', ''))) {
            $query->whereHas('verification', fn ($builder) => $builder->where('status', $status));
        }

        return response()->json(['success' => true, 'onboardings' => $query->get()->map(fn ($organization) => [
            'id' => $organization->id,
            'name' => $organization->name,
            'email' => $organization->email ?: $organization->owner?->email,
            'phone' => $organization->phone ?: $organization->owner?->phone,
            'category' => $organization->category?->name,
            'owner_full_name' => $organization->verification?->owner_full_name,
            'status' => $organization->verification?->status,
            'submitted_at' => $organization->verification?->created_at,
        ])]);
    }

    public function show(Organization $organization)
    {
        $organization->load(['verification', 'documents']);
        abort_unless($organization->verification, 404, 'Onboarding submission not found.');

        return response()->json([
            'success' => true,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'verification' => $organization->verification,
                'documents' => $organization->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'view_url' => "/api/admin/store-onboarding-documents/{$document->id}",
                ]),
            ],
        ]);
    }

    public function document(OrganizationDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404, 'Document not found.');
        return Storage::disk($document->disk)->response(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream']
        );
    }

    public function approve(Request $request, Organization $organization)
    {
        abort_unless($organization->verification, 404, 'Onboarding submission not found.');
        $requiredTypes = ['nid_front', 'nid_back', 'trade_license'];
        abort_unless(
            $organization->documents()->whereIn('document_type', $requiredTypes)->distinct()->count('document_type') === count($requiredTypes),
            422,
            'All required documents must be uploaded before approval.'
        );

        DB::transaction(function () use ($request, $organization) {
            $organization->verification->update([
                'status' => 'approved', 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'rejection_reason' => null,
            ]);
            $organization->update(['status' => 'active', 'verification_status' => 'approved', 'is_verified' => true]);
        });

        return response()->json(['success' => true, 'message' => 'Store onboarding approved.']);
    }

    public function reject(Request $request, Organization $organization)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_unless($organization->verification, 404, 'Onboarding submission not found.');

        DB::transaction(function () use ($request, $organization, $data) {
            $organization->verification->update([
                'status' => 'rejected', 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'rejection_reason' => trim($data['reason']),
            ]);
            $organization->update(['status' => 'rejected', 'verification_status' => 'rejected', 'is_verified' => false]);
        });

        return response()->json(['success' => true, 'message' => 'Store onboarding rejected.']);
    }
}
