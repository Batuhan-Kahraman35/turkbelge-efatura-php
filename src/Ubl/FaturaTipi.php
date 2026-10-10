<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

/**
 * Fatura tipi (cbc:InvoiceTypeCode).
 */
enum FaturaTipi: string
{
    case SATIS = 'SATIS';
    /** İade; iade edilen fatura BillingReference ile belirtilmelidir */
    case IADE = 'IADE';
    /** KDV istisnası; %0 KDV'li kalemlerde istisna kodu zorunludur */
    case ISTISNA = 'ISTISNA';
}
