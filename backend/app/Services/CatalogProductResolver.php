<?php

namespace App\Services;

use App\Enums\BarcodeType;
use App\Models\CatalogProduct;
use App\Models\User;
use App\Support\Gs1DigitalLink;
use App\Support\ResolvedCatalogProduct;

class CatalogProductResolver
{
    // the first supplier to register a code creates the shared record; every
    // supplier after that reuses it, whatever name or category they typed
    public function resolve(array $input, ?User $creator): ResolvedCatalogProduct
    {
        $code = $this->parseCode($input['raw_code'] ?? null);

        if ($code['barcode'] !== null) {
            $existing = CatalogProduct::where('barcode', $code['barcode'])->first();

            if ($existing !== null) {
                return new ResolvedCatalogProduct($existing, $code['batch'], $code['expiry']);
            }
        }

        $catalogProduct = CatalogProduct::create([
            'barcode' => $code['barcode'],
            'barcode_type' => $code['barcode_type'],
            'name' => $input['name'],
            'category_id' => $input['category_id'] ?? null,
            'unit_id' => $input['unit_id'] ?? null,
            'pack_quantity' => $input['pack_quantity'] ?? null,
            'created_by' => $creator?->id,
        ]);

        return new ResolvedCatalogProduct($catalogProduct, $code['batch'], $code['expiry']);
    }

    /** @return array{barcode: ?string, barcode_type: ?BarcodeType, batch: ?string, expiry: ?\Illuminate\Support\Carbon} */
    private function parseCode(?string $raw): array
    {
        $raw = $raw !== null ? trim($raw) : null;

        if ($raw === null || $raw === '') {
            return ['barcode' => null, 'barcode_type' => null, 'batch' => null, 'expiry' => null];
        }

        $gs1 = Gs1DigitalLink::parse($raw);

        if ($gs1 !== null) {
            return [
                'barcode' => $gs1['gtin'],
                'barcode_type' => BarcodeType::Gs1Qr,
                'batch' => $gs1['batch'],
                'expiry' => $gs1['expiry'],
            ];
        }

        // a run of 8-14 digits is a plausible EAN/UPC/GTIN typed or scanned as a plain barcode
        if (preg_match('/^\d{8,14}$/', $raw)) {
            return ['barcode' => $raw, 'barcode_type' => BarcodeType::Barcode, 'batch' => null, 'expiry' => null];
        }

        // whatever else it is, it is stored and shown exactly as scanned/typed - never
        // parsed as a link, never fetched
        return ['barcode' => $raw, 'barcode_type' => BarcodeType::Other, 'batch' => null, 'expiry' => null];
    }
}
