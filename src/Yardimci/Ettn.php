<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Yardimci;

/**
 * Evrensel tekil tanımlama numarası (RFC 4122 v4 UUID).
 */
final class Ettn
{
    /** Kriptografik rastgele v4 UUID, küçük harf (servis listeleri küçük harf döndürür). */
    public static function uret(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function gecerliMi(string $ettn): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ettn);
    }
}
