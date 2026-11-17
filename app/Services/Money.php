<?php

namespace App\Services;

/**
 * Único punto donde se formatean importes para mostrarlos en pantalla,
 * mensajes de validación, bitácora y exportaciones.
 *
 * El formato es el argentino estándar: símbolo $, separador de miles con punto
 * y decimales con coma ($1.234,56). Centralizarlo acá evita que cada vista o
 * controlador declare su propia variante y que alguna pantalla se desalinee.
 */
final class Money
{
    private const DECIMALS = 2;

    /**
     * Importe listo para pantalla: "$1.234,56".
     */
    public static function format(float|int|string|null $amount): string
    {
        return '$'.self::plain($amount);
    }

    /**
     * Importe sin símbolo: "1.234,56". Para CSV, mensajes que ya escriben el
     * símbolo aparte y cualquier lugar que necesite sólo el número.
     */
    public static function plain(float|int|string|null $amount): string
    {
        return number_format((float) $amount, self::DECIMALS, ',', '.');
    }
}
