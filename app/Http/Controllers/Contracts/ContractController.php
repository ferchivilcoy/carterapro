<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\GenerateContractRequest;
use App\Models\Contract;
use App\Models\Loan;
use App\Services\PrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ContractController extends Controller
{
    public function index(): View
    {
        $contracts = Contract::query()
            ->with(['loan.client', 'user'])
            ->latest('generated_at')
            ->paginate(15)
            ->withQueryString();

        // Solo se pueden generar contratos de préstamos que todavía no tengan
        // uno: la pantalla ofrece exactamente esas opciones.
        $loans = Loan::query()
            ->with('client')
            ->whereNotIn('id', Contract::query()->select('loan_id'))
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        return view('contracts.index', [
            'contracts' => $contracts,
            'loans' => $loans,
        ]);
    }

    public function generate(GenerateContractRequest $request, PrintService $print): RedirectResponse
    {
        $loan = Loan::query()->with('client')->findOrFail($request->integer('loan_id'));

        $existing = Contract::query()->where('loan_id', $loan->id)->first();
        if ($existing) {
            return back()->with('contract_error', 'A contract already exists for this loan.');
        }

        $html = view('contracts.pdf', [
            'loan' => $loan,
            'client' => $loan->client,
            'number' => 'CTR-'.str_pad((int) now()->timestamp, 8, '0', STR_PAD_LEFT),
        ])->render();

        $path = $print->storePdf($html, 'contracts/contract_'.$loan->id.'_'.now()->timestamp.'.pdf');

        Contract::create([
            'loan_id' => $loan->id,
            'contract_number' => 'CTR-'.str_pad((int) now()->timestamp, 8, '0', STR_PAD_LEFT),
            'pdf_path' => $path,
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('contracts.index')
            ->with('contract_success', 'Contract generated and stored successfully.');
    }

    public function show(Contract $contract): View
    {
        $contract->load(['loan.client', 'user']);

        return view('contracts.show', ['contract' => $contract]);
    }

    public function download(Contract $contract): BinaryFileResponse
    {
        // El contrato se guarda en el disco privado y sólo se sirve por este
        // controlador, después de autenticar y aplicar el scope de cobrador.
        $disk = Storage::disk('local');

        abort_unless($disk->exists($contract->pdf_path), 404);

        // Los PDF generados con Dompdf se muestran en una pestaña nueva en
        // lugar de descargarse, igual que el resto de las impresiones.
        return response()->file($disk->path($contract->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                basename($contract->pdf_path),
            ),
        ]);
    }
}
