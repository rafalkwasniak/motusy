<?php

namespace App\Services;

use RuntimeException;

/**
 * Ślad, którego nie da się odczytać.
 *
 * Kontroler zamienia to na **422**, a ten kod kasuje plik z urządzenia
 * bezpowrotnie (docs/api-slad-trasy.md §2). Rzucamy więc wyłącznie wtedy,
 * gdy ponowienie faktycznie nic nie da: zła wersja formatu, uszkodzony
 * nagłówek, linia, której nie da się rozłożyć na liczby. Awaria bazy czy
 * dysku to nie jest ten wyjątek — tam ma polecieć 500 i pudełko ponowi.
 */
class TrackFormatException extends RuntimeException {}
