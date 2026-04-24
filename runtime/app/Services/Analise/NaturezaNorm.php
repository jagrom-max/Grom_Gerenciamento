<?php

namespace App\Services\Analise;

/**
 * Normaliza rótulos de natureza criminal para agrupamento estatístico.
 *
 * Portado de main/analise_dados/hub/natureza_norm.py — mesmas regras
 * priorizadas na mesma ordem para manter consistência com o Python.
 */
final class NaturezaNorm
{
    /** Retorna o rótulo canônico de uma natureza. */
    public static function label(string $raw): string
    {
        if (trim($raw) === '' || in_array($raw, ['-', 'N/A'], true)) {
            return '';
        }

        $key = self::normKey($raw);

        // ─────────────────────────────────────────────────────
        // Regras na mesma ordem do Python (qt_stats_panel.py)
        // ─────────────────────────────────────────────────────

        if (str_contains($key, 'descumpr') || str_contains($key, 'medida protetiva')
            || str_contains($key, '24 a') || str_contains($key, '24a')) {
            return 'Descumprimento de MPU';
        }

        if ((str_contains($key, 'vias') && str_contains($key, 'fato'))
            || str_contains($key, 'vias de fato')) {
            return 'Vias de Fato';
        }

        if (str_contains($key, 'eca') || str_contains($key, 'crianca')
            || str_contains($key, '216b') || str_contains($key, '216 b')
            || (str_contains($key, 'divulga') && (str_contains($key, 'cena') || str_contains($key, 'imagem') || str_contains($key, 'foto')))
            || (str_contains($key, 'manipul') && (str_contains($key, 'foto') || str_contains($key, 'imagem')))) {
            return 'ECA';
        }

        if ($key === 'outros' || str_starts_with($key, 'outros ')
            || str_contains($key, 'nao criminal') || str_contains($key, 'no criminal')) {
            return 'Não Criminal';
        }

        if (preg_match('/\blesao?\s+corporal\b/', $key) || str_contains($key, 'leso corporal')) {
            return 'Lesão Corporal';
        }

        if ((str_contains($key, 'viol') && str_contains($key, 'dom'))
            || str_contains($key, 'violencia domestica')) {
            return 'Violência Doméstica';
        }

        if ((str_contains($key, 'psicolog') && str_contains($key, 'viol'))
            || str_contains($key, 'violencia psicologica')) {
            return 'Violência Psicológica';
        }

        if ((str_contains($key, 'importun') || str_contains($key, 'impotun'))
            && str_contains($key, 'sexual')) {
            return 'Importunação Sexual';
        }

        if (str_contains($key, 'estupro') || str_contains($key, 'conjuncao carnal')) {
            return 'Estupro';
        }

        if (str_contains($key, 'ameac') || str_contains($key, 'ameaca')) {
            return 'Ameaça';
        }

        if (str_contains($key, 'injuria') || str_contains($key, 'difamacao')
            || str_contains($key, 'calun')) {
            return 'Injúria/Difamação/Calúnia';
        }

        if ((str_contains($key, 'dano') && ! str_contains($key, 'lesao'))
            || str_contains($key, 'estrago')) {
            return 'Dano';
        }

        if (str_contains($key, 'furto')) {
            return 'Furto';
        }

        if (str_contains($key, 'roubo')) {
            return 'Roubo';
        }

        if (str_contains($key, 'estelionato') || str_contains($key, 'fraude')) {
            return 'Estelionato/Fraude';
        }

        if (str_contains($key, 'trafico') || str_contains($key, 'drogas')
            || str_contains($key, 'entorpecente')) {
            return 'Tráfico/Drogas';
        }

        if (str_contains($key, 'homicidio')) {
            return 'Homicídio';
        }

        if (str_contains($key, 'sequestro') || str_contains($key, 'carcere privado')
            || (str_contains($key, 'privacao') && str_contains($key, 'liberdade'))) {
            return 'Sequestro/Cárcere Privado';
        }

        if (str_contains($key, 'porte') || str_contains($key, 'posse')
            || str_contains($key, 'arma')) {
            return 'Arma de Fogo';
        }

        if (str_contains($key, 'perseguic') || str_contains($key, 'stalking')) {
            return 'Perseguição/Stalking';
        }

        if (str_contains($key, 'resistenc') || str_contains($key, 'resistnc')
            || $key === 'resistencia' || str_starts_with($key, 'resistencia ')
            || str_starts_with($key, 'resistnc')) {
            return 'Resistência';
        }

        if (str_contains($key, 'abandono') && str_contains($key, 'incapaz')) {
            return 'Abandono de Incapaz';
        }

        // Título-cased a partir do original (primeira letra maiúscula de cada palavra)
        return mb_convert_case(mb_strtolower($raw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function normKey(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(
            ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç','ñ'],
            ['a','a','a','a','e','e','i','o','o','o','u','c','n'],
            $s
        );
        $s = preg_replace('/[_.\-\/,:;()\[\]{}\'"]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }
}
