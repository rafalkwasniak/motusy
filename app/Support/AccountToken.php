<?php

namespace App\Support;

use Random\Randomizer;

/**
 * Token konta — jeden na konto, wspólny dla wszystkich urządzeń właściciela.
 * Tak nazywa go kontrakt telemetrii §2 i tak nazywamy go w panelu.
 *
 * Właściciel przepisuje go z panelu do konfiguracji WiFi pudełka.
 *
 * Świadomie krótki i jawny. To poświadczenie do wysyłania własnych przejazdów,
 * a nie klucz do niczego wrażliwego — dlatego stawiamy na czytelność przy
 * przepisywaniu, a nie na maksymalną entropię. Jest widoczny w panelu przez
 * cały czas, więc trzymamy go w bazie otwartym tekstem (inaczej dałoby się
 * pokazać tylko raz).
 */
final class AccountToken
{
    /**
     * Alfabet bez znaków, które mylą się przy przepisywaniu:
     * brak 0 i O, brak 1, I oraz L.
     */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** Liczba grup i długość grupy — postać XXXX-XXXX-XXXX. */
    private const GROUPS = 3;

    private const GROUP_LENGTH = 4;

    /**
     * Nowy token w postaci `XFRS-34ST-YTS8`.
     */
    public static function generate(): string
    {
        $randomizer = new Randomizer;
        $max = strlen(self::ALPHABET) - 1;

        $groups = [];

        for ($group = 0; $group < self::GROUPS; $group++) {
            $chars = '';

            for ($i = 0; $i < self::GROUP_LENGTH; $i++) {
                $chars .= self::ALPHABET[$randomizer->getInt(0, $max)];
            }

            $groups[] = $chars;
        }

        return implode('-', $groups);
    }

    /**
     * Sprowadza to, co przyszło z urządzenia, do postaci kanonicznej.
     *
     * Wybacza małe litery, spacje i brakujące myślniki — ktoś przepisujący
     * token ręcznie nie powinien dostać 401 za to, że pominął kreski.
     * Zwraca null, gdy w ciągu jest cokolwiek spoza alfabetu albo gdy
     * długość się nie zgadza.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw) ?? '');

        if (strlen($clean) !== self::GROUPS * self::GROUP_LENGTH) {
            return null;
        }

        if (strspn($clean, self::ALPHABET) !== strlen($clean)) {
            return null;
        }

        return implode('-', str_split($clean, self::GROUP_LENGTH));
    }
}
