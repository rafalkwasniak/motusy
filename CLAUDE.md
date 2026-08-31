# CLAUDE.md

Projekt **motusy.top** — start od zera, 31 sierpnia 2026.

Poprzednia zawartość repozytorium (API pod aplikację BLE + FlutterFlow, potem zwrot na PWA) została skasowana razem z historią gita. Nic z tamtego kodu nie obowiązuje. Jedyny ocalały plik to [docs/motusy-moto-box.md](docs/motusy-moto-box.md).

---

## 1. Czym jest projekt

Portal i API przyjmujące dane z **urządzeń Motusy**. Pierwszym i na razie jedynym urządzeniem jest **Motusy Moto Box** — autonomiczne pudełko montowane na motocyklu, rejestrujące dynamikę jazdy z czujnika IMU (BMI270 na M5StickS3) plus prosty alarm ruchu po wyłączeniu stacyjki.

### Dokumenty źródłowe

| Plik | Co opisuje | Status |
|---|---|---|
| [docs/motusy-moto-box.md](docs/motusy-moto-box.md) | MVP samego urządzenia: pomiary, alarm, ekran, przycisk | specyfikacja funkcjonalna; §29 wyklucza z MVP WiFi, API i panel WWW, więc portal jest etapem po niej |
| [docs/api-telemetria.md](docs/api-telemetria.md) | **kontrakt API telemetrii, wersja 1** | **wiążący** — format jest zaimplementowany w firmware (`lib/telemetry/TelemetryJson.cpp`) i obłożony testami sprawdzającymi dosłowną treść JSON-a |
| `docs/motusy-02.png` | logo „MOTUSY TWO WHEELS SOCIETY" | materiał graficzny |

Kolejność ważności przy rozbieżnościach: kontrakt telemetrii bije specyfikację urządzenia, bo opisuje kod, który już istnieje po drugiej stronie. Zmiana kształtu przesyłki wymaga wydania nowego firmware'u do urządzeń, które fizycznie siedzą na motocyklach.

**Kluczowe założenie architektoniczne, wprost od Rafała:** urządzeń będzie więcej niż jedno. Moto Box to **rodzaj**, nie cały produkt. **Konto użytkownika jest wspólne dla wszystkich rodzajów** — jedno logowanie, wiele pudełek, potencjalnie różnych typów. Model danych ma od początku rozróżniać egzemplarz urządzenia od jego typu.

Rafał podał `/api/v1/motobox` jako przykład ścieżki nazwanej rodzajem urządzenia — **a kontrakt telemetrii mówi `/api/v1/rides`**. Rozstrzygnięcie w sekcji 5.

---

## 2. Decyzje podjęte 31 sierpnia 2026

| Temat | Ustalenie |
|---|---|
| Transport danych | **Urządzenie wysyła samo, po WiFi.** ESP32-S3 ma WiFi, więc nie ma pośrednika w postaci telefonu |
| Uwierzytelnianie urządzenia | **Token konta** (Sanctum), przepisywany do urządzenia raz, przy konfiguracji WiFi. Nie token per urządzenie — tak stanowi kontrakt telemetrii §2 |
| Zakres portalu | **API + panel webowy** w jednym projekcie Laravel |
| Stos frontu | Livewire (starter kit Laravela) — interaktywność pisana w PHP, bez budowania SPA |
| Punkt wyjścia kodu | **Totalna wycinka.** Zero kodu ze starego projektu, łącznie z kopertą odpowiedzi, tłumaczeniami i raporterem Discord — wszystko do napisania od nowa, jeśli będzie potrzebne |
| Historia gita | Skasowana lokalnie i **nadpisana na GitHubie** (`push --force`) |

---

## 3. Stos i środowisko

| Element | Wartość |
|---|---|
| Framework | Laravel 13.29.0 |
| Starter kit | `laravel/livewire-starter-kit`, gałąź `main` |
| Auth (web) | Laravel Fortify — rejestracja, weryfikacja e-mail, 2FA, passkeys, potwierdzanie hasła |
| Auth (API) | Laravel Sanctum (`install:api`), `HasApiTokens` na modelu `User` |
| Front | Livewire 4 + Flux 2 + Tailwind 4, build przez `vite-plus` |
| PHP (web) | 8.5.9 |
| PHP (CLI, domyślny) | 8.3.33 — **niezgodny z projektem** (`composer.json` wymaga `^8.3`, ale framework i tak celuje wyżej) |
| Baza | MariaDB, `host473413_motusy` |
| Katalog projektu | `/home/host473413/domains/motusy.top` |
| Docroot | `public_html` → symlink na `public/` |

### Dlaczego gałąź `main` startera, a nie tag

Tagowana wersja `v1.0.1` startera stoi na Laravelu 12 i Livewire 3. Próba podniesienia jej do Laravela 13 kończy się dwoma rozjazdami naraz: `composer` dociąga Livewire 4 (bo Flux go dopuszcza), a widoki są pisane pod konwencje Livewire 3 — layout `layouts::app` przestaje się rozwiązywać. Gałąź `main` jest już przepisana pod Laravel `^13.17` i Livewire `^4.1`, i jej pliki szkieletu zgadzają się z czystym `laravel/laravel` v13. Starter jest szablonem projektu, nie zależnością, więc „niestabilność" gałęzi dotyczy wyłącznie jednorazowego skopiowania plików.

### Pułapki środowiska

**PHP CLI ma 8.3, web ma 8.5.** Composer i artisan wywołuj jawnie:

```bash
/opt/alt/php85/usr/bin/php /usr/local/bin/composer <cmd>
/opt/alt/php85/usr/bin/php artisan <cmd>
```

Artisan odpala Composera przez `php` z `PATH` (dotyczy komend `install:*`), więc tam trzeba dodatkowo podłożyć symlink `php` → `/opt/alt/php85/usr/bin/php` na początku `PATH`.

**Serwer ma limit ~250 wątków na konto i build frontu się w nim nie mieści**, gdy działa sesja VS Code. Objaw: `vp build` / `vite build` wywala się panice Rusta — `failed to spawn thread: Resource temporarily unavailable`, raz z rolldown, raz z rayon, raz z tokio, zależnie od tego, która pula trafi na ścianę pierwsza. To **nie jest błąd konfiguracji projektu** — te same polecenia przechodzą natychmiast po zamknięciu VS Code i odpaleniu z gołego terminala. Zmienne `RAYON_NUM_THREADS`, `TOKIO_WORKER_THREADS`, `GOMAXPROCS` i `taskset` przesuwają problem do kolejnej puli, ale go nie usuwają.

Praktycznie: **`npm run build` uruchamia Rafał w terminalu, nie agent w sesji VS Code.**

**`migrate:fresh` jest zablokowane**, bo `APP_ENV=production` i Laravel domyślnie zabrania destrukcyjnych komend na produkcji (`DB::prohibitDestructiveCommands`). Flaga `--force` tego nie omija. Gdy trzeba przebudować schemat od zera, tabele kasuje się ręcznie w MySQL, a potem leci zwykłe `migrate --force`.

**Config bywa cache'owany.** Po zmianie `.env` wykonaj `php artisan config:cache`. Uwaga historyczna z poprzedniego projektu, warta zapamiętania: scache'owany config unieważnia wpisy `<env>` z `phpunit.xml`, przez co testy potrafią pójść po produkcyjnej bazie. Gdy dojdą testy, trzeba to zamknąć u źródła.

---

## 4. Gałęzie i wdrożenie

**Pracujemy wyłącznie na `main`.**

Powód jest techniczny: **docroot serwuje katalog roboczy repozytorium** (`public_html` → `public` wewnątrz drzewa gita). Produkcja pokazuje tę gałąź, która jest aktualnie wyczekowana, więc druga gałąź niczego nie izoluje.

**Ryzyko do zaadresowania przed pierwszym realnym ruchem:** skoro produkcja to katalog roboczy, każda niezacommitowana zmiana jest natychmiast na żywo, także w połowie refaktoru.

Repozytorium: `git@github.com-motusy:rafalkwasniak/motusy.git`, deploy key `~/.ssh/id_ed25519_motusy`.

---

## 5. Do ustalenia przed pisaniem kodu

Kontrakt telemetrii zamknął większość pytań: znamy kształt przesyłki, endpointy (`POST /api/v1/rides`, `GET /api/v1/ping`), semantykę `accepted_through`, schemat tabeli `rides` i reguły walidacji. Otwarte zostaje to:

1. **`/api/v1/rides` czy `/api/v1/motobox/rides`.** Rafał chce ścieżek nazwanych rodzajem urządzenia, kontrakt mówi `/api/v1/rides`. Firmware ma tę ścieżkę zaszytą, więc zmiana kosztuje wydanie nowej wersji do urządzeń w terenie. Możliwe wyjścia: zostawić `/rides` i różnicować rodzaje w danych, albo wystawić obie ścieżki na ten sam kontroler. Do decyzji **zanim** pierwsze urządzenie trafi do kogoś poza Rafałem.
2. **Token konta w urządzeniu.** Kontrakt §2 świadomie wybiera token konta zamiast tokena per urządzenie. Konsekwencja: kto zdejmie pudełko z motocykla i wyciągnie z niego pamięć, dostaje **pełny token konta**, a nie poświadczenie jednego urządzenia. Zakres tokena Sanctuma da się zawęzić abilities do samego wysyłania przejazdów — warto to zrobić przy wydawaniu tokena w panelu, bo nie wymaga zmiany po stronie firmware'u.
3. **Co z alarmem.** Wykrycie ruchu po wyłączeniu motocykla to zdarzenie, o którym właściciel chciałby wiedzieć od razu — ale urządzenie w garażu bez WiFi nie ma jak powiadomić, a specyfikacja nie przewiduje GSM. Kontrakt telemetrii alarmu nie obejmuje. Do rozstrzygnięcia, czy alarm w ogóle wchodzi do portalu.
4. **Jak wygląda panel.** Kontrakt określa tylko, że historia sortuje się po `seq` malejąco, kasowanie jest miękkie, a urządzenia w koncie trzeba dać się rozróżnić. Reszta — układ ekranów, nazywanie urządzeń, wykresy — nieustalona.
5. **Czy `FOUNDATION.md` wraca.** Poprzedni `CLAUDE.md` powoływał się na `docs/FOUNDATION.md`, którego w repozytorium nie było. Kopie leżą w `kramio.pl`, `pensec.top` i `curvia.kwasniak.org` — **i różnią się między sobą**, więc nie zgaduję, która wersja jest aktualna.

---

## 6. Stan na 31 sierpnia 2026

Repozytorium wyczyszczone, historia gita założona od nowa. Postawiony starter kit Livewire na Laravelu 13.29.0 z Fortify (rejestracja, weryfikacja e-mail, 2FA, passkeys) i Sanctum dla API. Baza przebudowana od zera — sześć migracji szkieletowych, zero tabel produktowych.

Zweryfikowane na żywo przed podmianą szkieletu: `/` 200, `/up` 200, `/login` 200, `/api/user` 401 JSON.

**Kod produktowy nie istnieje.** Nie ma tabeli `rides`, endpointu przyjmującego przejazdy ani żadnego ekranu poza tym, co daje starter kit.

Front nie jest zbudowany — `public/build` nie istnieje, więc każda strona renderowana przez Blade zwraca 500 (`ViteManifestNotFoundException`), a 10 z 33 testów startera przewraca się z tego samego powodu. Pozostałe 20 przechodzi. **Wystarczy `npm run build` z gołego terminala** (patrz pułapki w sekcji 3).
