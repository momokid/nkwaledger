<?php

namespace App\Enums;

enum BarcodeType: string
{
    // a traditional 1D barcode/GTIN, scanned or typed as digits
    case Barcode = 'barcode';

    // a QR code in GS1 Digital Link format - the product number was parsed out of it
    case Gs1Qr = 'gs1_qr';

    // any other scanned or typed text, stored and shown as-is, never parsed as a link
    case Other = 'other';
}
