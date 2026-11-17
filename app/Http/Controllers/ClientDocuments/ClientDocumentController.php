<?php

namespace App\Http\Controllers\ClientDocuments;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientDocuments\StoreClientDocumentRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientDocumentController extends Controller
{
    public function index(): View
    {
        $documents = ClientDocument::query()
            ->with(['client', 'user'])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'clients' => Client::query()->orderBy('name')->get(),
            'types' => DocumentType::cases(),
        ]);
    }

    public function store(StoreClientDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        // Documentos de identidad y recibos: siempre en el disco privado.
        $path = $file->store('documents', 'local');

        ClientDocument::create([
            'client_id' => $data['client_id'],
            'type' => $data['type'],
            'file_name' => $file->getClientOriginalName(),
            'path' => $path,
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()
            ->route('client-documents.index')
            ->with('document_success', 'Document uploaded successfully.');
    }

    public function destroy(ClientDocument $document): RedirectResponse
    {
        $disk = Storage::disk('local');
        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }

        $document->delete();

        return redirect()
            ->route('client-documents.index')
            ->with('document_success', 'Document deleted successfully.');
    }

    public function download(ClientDocument $document): BinaryFileResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($document->path), 404);

        return response()->download($disk->path($document->path), $document->file_name);
    }
}
