<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Validation et rendu PDF communs aux devis et factures. */
trait HandlesDocuments
{
    protected function validateDocument(Request $request, array $extra = []): array
    {
        $data = $request->validate($extra + [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'issued_at' => ['required', 'date'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'delivery_fee' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.designation' => ['nullable', 'required_without:lines.*.product_id', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ]);

        $products = Product::whereIn('id', array_filter(array_column($data['lines'], 'product_id')))->get()->keyBy('id');
        $data['lines'] = array_values(array_map(function ($line) use ($products) {
            $product = isset($line['product_id']) ? $products[$line['product_id']] ?? null : null;

            return [
                'product_id' => $product?->id,
                'designation' => ($line['designation'] ?? null) ?: $product?->name,
                'reference' => $product?->reference,
                'unit_price' => isset($line['unit_price']) && $line['unit_price'] !== null ? (int) $line['unit_price'] : (int) $product?->price,
                'quantity' => (int) $line['quantity'],
            ];
        }, $data['lines']));
        $data['discount'] = (int) ($data['discount'] ?? 0);
        $data['delivery_fee'] = (int) ($data['delivery_fee'] ?? 0);
        $data['store_id'] = $this->storeIdForCreation($request);

        return $data;
    }

    protected function formData(): array
    {
        return [
            'stores' => $this->user()->accessibleStores(),
            'products' => Product::where('status', '!=', 'discontinued')->orderBy('name')->get(['id', 'name', 'reference', 'price']),
        ];
    }

    protected function customerFromRequest(Request $request): ?Customer
    {
        return $request->integer('customer_id') ? Customer::find($request->integer('customer_id')) : null;
    }

    protected function renderPdf(string $view, Model $document, string $filename)
    {
        $document->loadMissing(['lines', 'customer', 'store', 'user']);
        $pdf = Pdf::loadView($view, ['document' => $document])->setOption(['isPhpEnabled' => true, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4');

        return request()->boolean('inline') ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
