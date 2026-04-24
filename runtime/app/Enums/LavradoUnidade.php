<?php

namespace App\Enums;

enum LavradoUnidade: string
{
    case Ddm = 'DDM';
    case OutrasUnidades = 'OUTRAS_UNIDADES';

    public static function fromLegacy(?string $value): self
    {
        $normalized = mb_strtoupper(trim((string) $value));

        if ($normalized === '') {
            return self::OutrasUnidades;
        }

        if (str_contains($normalized, 'DDM') || str_contains($normalized, 'ELETR') || str_contains($normalized, 'ONLINE')) {
            return self::Ddm;
        }

        return self::OutrasUnidades;
    }

    public function label(): string
    {
        return match ($this) {
            self::Ddm => 'DDM',
            self::OutrasUnidades => 'Outras Unidades',
        };
    }
}
