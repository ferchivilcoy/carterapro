<?php

namespace App\Services;

use App\Models\Client;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PrintService
{
    public function stream(string $view, array $data, string $filename): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename.'.pdf'
            ),
        ]);
    }

    /**
     * Estado de cuenta del cliente, con el mismo armado de datos para el
     * panel (print.clients.statement) y para el portal (portal.statement):
     * la carga de los préstamos y pagos es parte del documento, no de la ruta.
     */
    public function clientStatement(Client $client): Response
    {
        $client->load([
            'loans' => fn ($query) => $query->withCount([
                'installments',
                'installments as paid_installments_count' => fn ($q) => $q->where('status', 'paid'),
                'installments as overdue_installments_count' => fn ($q) => $q->where('status', 'overdue'),
            ])->orderBy('created_at'),
            'payments' => fn ($query) => $query->active()->with(['loan', 'installment'])->latest('payment_date')->orderByDesc('id'),
        ]);

        return $this->stream(
            'prints.statement',
            ['client' => $client],
            'statement-'.$client->id,
        );
    }

    /**
     * Renderiza una vista Blade a PDF y lo almacena en el disco privado.
     * Devuelve la ruta relativa guardada (p. 'contracts/contract_123.pdf').
     */
    public function storePdf(string $html, string $path): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        Storage::disk('local')->put($path, $dompdf->output());

        return $path;
    }
}
