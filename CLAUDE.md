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
| `docs/motusy-02.png` | logo „MOTUSY TWO WHEELS SOCIETY" | materiał graficzny; pochodne w `public/images` i ikony w `public/` |
| `docs/m5sticks3-*.jpg.webp` | fotografia produktowa M5StickS3 | materiał poglądowy — na stronie nie używamy jej wprost, patrz sekcja 3a |

Kolejność ważności przy rozbieżnościach: kontrakt telemetrii bije specyfikację urządzenia, bo opisuje kod, który już istnieje po drugiej stronie. Zmiana kształtu przesyłki wymaga wydania nowego firmware'u do urządzeń, które fizycznie siedzą na motocyklach.

**Kluczowe założenie architektoniczne, wprost od Rafała:** urządzeń będzie więcej niż jedno. Moto Box to **rodzaj**, nie cały produkt. **Konto użytkownika jest wspólne dla wszystkich rodzajów** — jedno logowanie, wiele pudełek, potencjalnie różnych typów. Model danych ma od początku rozróżniać egzemplarz urządzenia od jego typu.

Rafał podał `/api/v1/motobox` jako przykład ścieżki nazwanej rodzajem urządzenia — **a kontrakt telemetrii mówi `/api/v1/rides`**. Rozstrzygnięcie w sekcji 5.

---

## 2. Decyzje podjęte 31 sierpnia 2026

| Temat | Ustalenie |
|---|---|
| Transport danych | **Urządzenie wysyła samo, po WiFi.** ESP32-S3 ma WiFi, więc nie ma pośrednika w postaci telefonu |
| Uwierzytelnianie urządzenia | **Token konta**, przepisywany do urządzenia przy konfiguracji WiFi. Jeden na konto, nie per urządzenie — tak stanowi kontrakt telemetrii §2. Szczegóły postaci niżej w tej tabeli |
| Zakres portalu | **API + panel webowy** w jednym projekcie Laravel |
| Stos frontu | Livewire (starter kit Laravela) — interaktywność pisana w PHP, bez budowania SPA |
| Punkt wyjścia kodu | **Totalna wycinka.** Zero kodu ze starego projektu, łącznie z kopertą odpowiedzi, tłumaczeniami i raporterem Discord — wszystko do napisania od nowa, jeśli będzie potrzebne |
| Prędkość maksymalna | Urządzenie **jeszcze jej nie podaje** — `speed_kmh` przychodzi puste. GPS i MAX SPEED mają być gotowe, zanim narzędzie trafi do ludzi, więc schemat, API i ekrany obsługują to od początku: brak pomiaru pokazujemy jako `———`, nigdy jako `0` |
| Tożsamość użytkownika | **Nick** (`users.nickname`, unikalny) to jedyna nazwa pokazywana w portalu i kiedyś na froncie. Imię i nazwisko (`users.name`) są prywatne, nieobowiązkowe i uzupełniane w profilu — rejestracja o nie nie pyta |
| Hasło | **8 znaków, wielka litera, cyfra** (`Password::min(8)->mixedCase()->numbers()`), jednakowo we wszystkich środowiskach. Starter kit wymagał na produkcji 12 znaków ze znakiem specjalnym i sprawdzeniem wycieków, a poza produkcją nic — przez co testy sprawdzały inną regułę niż ta obowiązująca ludzi |
| Token konta | **Krótki, jawny, stale widoczny w panelu.** Postać `XFRS-34ST-YTS8` (3×4 znaki), alfabet bez 0, O, 1, I i L. Trzymany w `users.api_token` **otwartym tekstem** — świadomie, bo ma być do odczytania przy każdej rekonfiguracji WiFi; skrót w stylu Sanctuma pozwoliłby pokazać go tylko raz. Powstaje przy rejestracji, wymienialny przyciskiem. Przy sprawdzaniu normalizujemy wielkość liter i brak myślników |
| Passkeys i 2FA | **Wyłączone** — portal ma mieć zwykłe logowanie e-mailem i hasłem. Passkeys wracają odkomentowaniem pozycji w `config/fortify.php` (widoki są obłożone `Features::canManagePasskeys()`); 2FA wymaga dodatkowo przywrócenia ekranu z historii gita |
| Układ ustawień | **Jedna strona `/settings`** — nick, imię i nazwisko, e-mail, zmiana hasła, usunięcie konta. Bez zakładek: każda mieściła jeden formularz. Przełącznik jasny/ciemny siedzi w menu użytkownika, bo to jeden wybór, nie ustawienie konta |
| Urządzenia i przejazdy | Własne pozycje w menu (`/devices`, `/rides`), **nie** w ustawieniach konta — to treść produktu, a nie konfiguracja profilu |
| Historia gita | Skasowana lokalnie i **nadpisana na GitHubie** (`push --force`) |

---

## 3. Stos i środowisko

| Element | Wartość |
|---|---|
| Framework | Laravel 13.29.0 |
| Starter kit | `laravel/livewire-starter-kit`, gałąź `main` |
| Auth (web) | Laravel Fortify — rejestracja, logowanie, weryfikacja e-mail, reset hasła. 2FA i passkeys wyłączone (sekcja 2) |
| Auth (API urządzeń) | **Własny token konta** (`users.api_token`), nie Sanctum — Sanctum trzyma skrót, a token ma być stale czytelny w panelu. Sanctum zostaje zainstalowany i obsługuje `/api/user`, ale telemetria go nie używa |
| Front | Livewire 4 + Flux 2 + Tailwind 4, build przez zwykłe `vite` (nie `vite-plus` — patrz pułapki) |
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

**Serwer ma limit ~250 wątków na konto, a same procesy VS Code zjadają ich ponad 180.** Objaw: `vite build` wywala się panice Rusta — `failed to spawn thread: Resource temporarily unavailable`, raz z rolldown, raz z rayon, raz z tokio, zależnie od tego, która pula trafi na ścianę pierwsza. Przy pełnym obłożeniu ginie nawet sam proces `claude` (`Failed to start HTTP Client thread`). To **nie jest błąd konfiguracji projektu**.

Zmierzone 31 sierpnia 2026, przy bezczynnej sesji: **222 wątki w 34 procesach**, czyli ~89% limitu, zanim build w ogóle wystartuje. Rozkład:

| Proces | Wątki |
|---|---|
| `code ... agent host` i `command-shell` (4 sztuki po 33) | 132 |
| `bootstrap-fork` (extensionHost, fileWatcher, ptyHost) i `server-main.js` | ~51 |
| `claude` (rozszerzenie w VS Code) | 15 |
| `lsphp`/`lsphps` i workery kolejek innych projektów | ~10 |

Sedno: **procesy `agent host` zostają po rozłączeniu i kumulują się** — w pomiarze jeden z nich działał od 29 godzin, obok trzech świeższych. Dlatego build czasem przechodzi po zamknięciu VS Code, a czasem nie: zależy, ile martwych sesji zostało.

Diagnostyka i sprzątanie:

```bash
ps -u $(whoami) -o nlwp= | awk '{s+=$1} END {print s" wątków"}'
pkill -f "cli/servers/.*agent host"     # tylko gdy VS Code jest zamknięty
```

Zmienne `RAYON_NUM_THREADS`, `TOKIO_WORKER_THREADS`, `GOMAXPROCS` i `taskset` przesuwają problem do kolejnej puli, ale go nie usuwają — jedyne, co działa, to zwolnienie wątków.

> ### ZAKAZ: agent nie uruchamia builda. Nigdy.
>
> `npm run build`, `npm run dev`, `vite`, `npx vite`, `vp` — **żadnego z tych poleceń agent nie odpala**, ani na próbę, ani „żeby sprawdzić", ani z ogranicznikami wątków. Build frontu wykonuje **wyłącznie Rafał, z gołego terminala**.
>
> Powód nie jest kosmetyczny: limit wątków jest **wspólny dla całego konta**, więc nieudana próba builda w motusy.top dławi także `kramio.pl` i pozostałe strony. 31 sierpnia 2026 agent zignorował tę zasadę i powtórzył build kilkanaście razy, doprowadzając serwer do zadławienia. Drugi raz to się nie ma wydarzyć.
>
> Gdy do weryfikacji potrzebny jest wyrenderowany widok, agent używa `Livewire::test()` albo `artisan test` — one nie wymagają zbudowanego frontu (`withoutVite()` w `Tests\TestCase`).

Sam projekt jest już odchudzony pod ten limit: zamiast nakładki `vite-plus` (własny runtime tokio startujący przed wczytaniem configu) używamy zwykłego `vite build`, a kroje są self-hostowane zamiast pobierane pluginem `laravel-vite-plugin/fonts`. Zostały więc tylko dwie natywne pule — rolldown i oxide.

**`migrate:fresh` jest zablokowane**, bo `APP_ENV=production` i Laravel domyślnie zabrania destrukcyjnych komend na produkcji (`DB::prohibitDestructiveCommands`). Flaga `--force` tego nie omija. Gdy trzeba przebudować schemat od zera, tabele kasuje się ręcznie w MySQL, a potem leci zwykłe `migrate --force`.

**Config bywa cache'owany.** Po zmianie `.env` wykonaj `php artisan config:cache`. Uwaga: scache'owany config unieważnia wpisy `<env>` z `phpunit.xml`, przez co testy potrafią pójść po **produkcyjnej** bazie — a `RefreshDatabase` kasuje wtedy prawdziwe dane. `Tests\TestCase::setUp()` pilnuje tego i wywala się z jasnym komunikatem, gdy połączenie nie jest sqlite.

---

## 3a. Język wizualny

Portal ma **nie wyglądać jak `kramio.pl`**, bo pierwsza wersja wyszła niemal identyczna — oba stały na tym samym starter kicie. Rozjazd jest świadomy i wygląda tak:

| | kramio.pl | motusy.top |
|---|---|---|
| Krój nagłówków | Instrument Sans / Instrument Serif, `font-semibold`, `tracking-tight` | **Barlow Condensed**, wersaliki, `tracking-wide` |
| Krój treści | Instrument Sans | **Barlow** |
| Liczby | zwykły sans | **JetBrains Mono**, `tabular-nums` |
| Akcent | bursztyn | **czerwień** `--color-brand-600` |
| Szarości | `stone` | `zinc` + `--color-ink` (#2B2B2A z plakietki logo) |
| Krawędzie | `rounded-2xl`, `rounded-3xl`, `rounded-full` | **ostre** — bez zaokrągleń, siatki budowane na `gap-px` i włosowych obwódkach |
| Nastrój | butik / editorial | warsztat / rysunek techniczny |

Kroje są **self-hostowane w `public/fonts`** z własnymi `@font-face` w `resources/css/app.css`, subsety `latin` + `latin-ext`. Pobieranie fontów w trakcie builda (`laravel-vite-plugin/fonts`) wywala build na tym hoście — kramio przerobiło to wcześniej tak samo.

Rysunek urządzenia (`<x-moto-box-drawing />`) jest **wektorowy, pisany ręcznie**, nie przerobioną fotografią: biała kartka, czarne obrysy, odnośniki z opisami. Filtr krawędziowy na zdjęciu produktowym daje brudną plamę, bo obudowa jest gładka i szara na białym tle. Kolory bierze z `currentColor`, więc chodzi za motywem.

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
2. **Jeden token na konto, nie na urządzenie.** Kontrakt §2 wybiera token konta. Konsekwencja: kto zdejmie pudełko z motocykla i wyciągnie z niego pamięć, dostaje poświadczenie **wszystkich** urządzeń w koncie, a unieważnienie tokena zatrzymuje je wszystkie naraz. Osobny token dla każdego urządzenia rozwiązałby jedno i drugie i **nie wymaga zmiany firmware'u** — urządzenie i tak wysyła zwykły `Authorization: Bearer`. Do rozważenia, gdy pudełek będzie więcej niż jedno.
3. **Co z alarmem.** Wykrycie ruchu po wyłączeniu motocykla to zdarzenie, o którym właściciel chciałby wiedzieć od razu — ale urządzenie w garażu bez WiFi nie ma jak powiadomić, a specyfikacja nie przewiduje GSM. Kontrakt telemetrii alarmu nie obejmuje. Do rozstrzygnięcia, czy alarm w ogóle wchodzi do portalu.
4. **Wykresy w panelu.** Kontrakt określa tylko, że historia sortuje się po `seq` malejąco i że kasowanie jest miękkie. Układ ekranów stoi; otwarte zostaje, czy i jak pokazywać przebieg w czasie — dziś portal ma same liczby.
5. **Czy `FOUNDATION.md` wraca.** Poprzedni `CLAUDE.md` powoływał się na `docs/FOUNDATION.md`, którego w repozytorium nie było. Kopie leżą w `kramio.pl`, `pensec.top` i `curvia.kwasniak.org` — **i różnią się między sobą**, więc nie zgaduję, która wersja jest aktualna.

---

## 6. Stan na 31 sierpnia 2026

Repozytorium wyczyszczone, historia gita założona od nowa. Postawiony starter kit Livewire na Laravelu 13.29.0 z Fortify (rejestracja, weryfikacja e-mail, 2FA, passkeys) i Sanctum dla API. Baza przebudowana od zera — sześć migracji szkieletowych, zero tabel produktowych.

Zweryfikowane na żywo przed podmianą szkieletu: `/` 200, `/up` 200, `/login` 200, `/api/user` 401 JSON.

Stoi strona główna, komplet ekranów logowania po polsku, e-maile z logo i polską treścią (SMTP na `info@motusy.top`), oraz panel: pulpit z rekordami i pięcioma ostatnimi przejazdami, pełna historia z filtrem po urządzeniu i miękkim kasowaniem, wykaz urządzeń z nadawaniem nazw. Tabele `devices` i `rides` są zgodne z kontraktem telemetrii.

**API jeszcze nie istnieje.** Nie ma `POST /api/v1/rides` ani `GET /api/v1/ping`, więc do bazy nie ma czym wpisać przejazdów inaczej niż ręcznie. Panel obsługuje stany puste i to jest na razie jego normalny widok.

Front nie jest zbudowany — `public/build` nie istnieje, więc każda strona renderowana przez Blade zwraca 500 (`ViteManifestNotFoundException`). **Wystarczy `npm run build` z gołego terminala** (patrz pułapki w sekcji 3). Testy tego nie dotyczą: `Tests\TestCase::setUp()` wywołuje `withoutVite()`, więc suite (45 testów) przechodzi bez builda.
