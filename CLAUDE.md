# CLAUDE.md

Specyfika projektu **motusy.top**. Zasady uniwersalne są w [docs/FOUNDATION.md](docs/FOUNDATION.md) i obowiązują zawsze — ten plik ich nie powtarza, tylko doprecyzowuje to, co dotyczy wyłącznie tego projektu.

W razie konfliktu: `FOUNDATION.md` wygrywa w sprawach współpracy (komunikacja, tryb pracy, Git), `CLAUDE.md` wygrywa w sprawach projektowych (stos, kontrakt API, decyzje produktowe).

---

## 1. Czym jest projekt

API pod domeną `motusy.top`. Docelowo produkt czysto API-owy; strona informacyjna możliwa w przyszłości, ale nie jest przedmiotem prac na tym etapie.

---

## 2. Stos i środowisko

| Element | Wartość |
|---|---|
| Framework | Laravel 13.25.0 (szkielet `laravel/laravel` v13.9) |
| PHP (web) | 8.5.7 |
| PHP (CLI, domyślny) | 8.3.31 — **niezgodny z projektem** |
| Baza | MariaDB 10.11.18, `host473413_motusy` |
| Auth | Laravel Sanctum (tokeny) |
| Katalog projektu | `/home/host473413/domains/motusy.top` |
| Docroot | `public_html` → symlink na `public/` |

### Pułapki środowiska

**PHP CLI ma 8.3, web ma 8.5.** Composer odpalony gołym `php` nie rozwiąże zależności — `symfony/console` v8 wymaga PHP >= 8.4.1 i wywala konflikt z `nunomaduro/collision`. Zawsze wywołuj jawnie:

```bash
/opt/alt/php85/usr/bin/php /usr/local/bin/composer <cmd>
```

Artisan odpala Composera przez `php` z `PATH`, więc przy komendach typu `install:*` samo uruchomienie artisana pod 8.5 nie wystarczy — trzeba podłożyć symlink `php` na `/opt/alt/php85/usr/bin/php` na początku `PATH`. Docelowo: zmiana wersji CLI w PHP Selectorze.

**Config jest cache'owany.** Po każdej zmianie `.env` trzeba wykonać `php artisan config:cache`, inaczej zmiana nie zadziała. To samo dotyczy `route:cache` po zmianach w routingu.

---

## 3. Kontrakt odpowiedzi API

Ustalenie nadrzędne: **jedna koperta dla wszystkiego**. Sukces i błąd mają ten sam kształt, różnią się wyłącznie zawartością. Natywne kształty Laravela (`{message, errors}` przy 422, `{message}` przy 401) **nie wychodzą na zewnątrz** — są opakowywane własnym handlerem wyjątków.

### Sukces

```json
{
  "success": true,
  "message": "Pobrano zasób.",
  "data": { },
  "pagination": { }
}
```

### Błąd

```json
{
  "success": false,
  "message": "Podane dane są nieprawidłowe.",
  "errors": { "email": ["Pole email jest wymagane."] }
}
```

### Reguły

- `data` tylko gdy jest ładunek. **Kolekcje zawsze zwracają `data: []`**, nigdy nie pomijają klucza — pominięcie zmuszałoby front do rozróżniania „brak klucza" i „pusta lista".
- `pagination` wyłącznie przy listingach stronicowanych.
- `errors` wyłącznie przy błędach walidacji.
- Kody HTTP zostają prawidłowe (422, 401, 403, 404, 500) — koperta ich nie zastępuje.
- **Handler wymusza JSON dla całego `/api/*` niezależnie od nagłówka `Accept`.** Realizują to dwa elementy w `bootstrap/app.php` i oba są potrzebne:
  - `redirectGuestsTo()` zwraca `null` dla `api/*`, dzięki czemu middleware `Authenticate` rzuca `AuthenticationException` zamiast budować przekierowanie na nieistniejącą trasę `login`. Bez tego żądanie bez nagłówka `Accept` kończyło się 500 (`RouteNotFoundException`), bo redirect powstawał **wewnątrz middleware**, zanim wyjątek dotarł do handlera.
  - `$exceptions->render()` mapuje wyjątki na kopertę.

  Regresję zamyka `test_protected_endpoint_returns_401_envelope_without_accept_header`.
- Zmiana kształtu zwrotki jest **addytywna** i pokryta testem zamykającym kontrakt.

### Kształt `pagination` (ustalony)

```json
{ "current_page": 1, "per_page": 25, "total": 137, "last_page": 6 }
```

### Implementacja

`App\Http\Responses\ApiResponse` jest **jedynym** źródłem koperty — kontrolery i handler wyjątków delegują do niego, żeby kształt nie mógł się rozjechać. Metody: `success()`, `paginated()`, `error()`, `fromException()`.

Zasada `data`: `null` pomija klucz, `[]` go zostawia. Kolekcje zawsze przekazują tablicę.

Komunikaty idą przez warstwę tłumaczeń (`lang/pl/api.php`).

---

## 4. Routing

- Prefiks wersji: **`/api/v1/`**. Wszystkie endpointy produktowe pod nim.
- **Create i update przez POST.** Update rozróżnia ID w URL: `POST /api/v1/res/{id}`. Powód: PHP nie parsuje multipartu dla PUT/PATCH, a spoofing `_method` działa tylko gdy żądanie jest POST-em. `PUT` i `PATCH` nie występują w kontrakcie.
- **Delete przez `DELETE /api/v1/res/{id}`.**
- Walidacja wyłącznie w Form Requestach, kontrolery cienkie, logika w serwisach.

---

## 4a. Gałęzie i wdrożenie

**Pracujemy wyłącznie na `main`.** `FOUNDATION.md` §3 dopuszcza to wprost — `develop` jest tam wartością domyślną, a nie wymogiem.

Powód jest techniczny, nie estetyczny: **docroot serwuje katalog roboczy repozytorium** (`public_html` → `public` wewnątrz drzewa gita). Produkcja pokazuje więc tę gałąź, która jest aktualnie wyczekowana. Druga gałąź niczego by nie izolowała — byłaby drugim wskaźnikiem, który z czasem zaczyna kłamać o tym, co jest na żywo.

Dwie gałęzie wprowadzamy dopiero, gdy każda dostanie własne środowisko (np. `dev.motusy.top` z osobnym checkoutem). Gdy potrzebna jest recenzja pojedynczej zmiany: krótkożyjąca gałąź funkcyjna → PR → `main` → kasujemy.

**Ryzyko do świadomego zaadresowania:** skoro produkcja to katalog roboczy, każda niezacommitowana zmiana jest natychmiast na żywo, także w połowie refaktoru. Git tego nie chroni. Do rozstrzygnięcia przed pierwszym realnym ruchem — osobny checkout produkcyjny albo świadoma zgoda na ten model.

---

## 5. Logowanie i alerty

- Kanał `stack` → `daily`, retencja **14 dni** (`LOG_STACK=daily`, `LOG_DAILY_DAYS=14`).
- **Discord webhook: URL jest już w `.env` pod `LOG_DISCORD_WEBHOOK_URL`.** Kanał logujący nie jest jeszcze podpięty — do zrobienia. Przy podpinaniu: poziom `error` i wyżej, bez payloadów z danymi osobowymi, wysyłka nieblokująca (kolejka lub `Http::async`), żeby padnięty Discord nie wywracał żądania API.
- `LOG_LEVEL` jest teraz `debug` — przy pierwszym realnym ruchu produkcyjnym rozważyć `info` lub `error`.

---

## 6. Dokumentacja

Trzy dokumenty wymagane przez `FOUNDATION.md` §4:

1. **OpenAPI** — `dedoc/scramble` v0.13.x (zweryfikowane: rozwiązuje się czysto pod Laravel 13). Publikuje pod `/docs/api`.
2. **`api-guide.html`** — narracyjny przewodnik, pisany ręcznie.
3. **`code-map.html`** — mapa kodu, ta sama skórka co przewodnik.

Katalog `docs/` leży poza webrootem, serwowany jedną dynamiczną trasą `docs/{slug}.html` ze slugiem `[A-Za-z0-9_-]+` i dosłownym sufiksem `.html`. Trasa nie koliduje ze Scramble, bo `/docs/api` nie ma sufiksu `.html`.

### Dostęp

Na tym etapie dokumenty są **ogólnodostępne, ale nieindeksowalne**: nagłówek `X-Robots-Tag: noindex, nofollow` na trasie docs plus wpis w `robots.txt`. Blokada indeksowania to nie kontrola dostępu — po zamknięciu prac dokumenty trafiają **za token**.

---

## 6a. Język aplikacji

**Aplikacja jest jednojęzyczna — polski.** `APP_LOCALE=pl`, `APP_FALLBACK_LOCALE=pl`, `APP_FAKER_LOCALE=pl_PL`.

To **świadome odstępstwo od `FOUNDATION.md` §5**, które każe trzymać dwa locale w synchronizacji. Tu drugiego języka nie ma i nie utrzymujemy go — zgodnie z zasadą, że specyfika projektu z `CLAUDE.md` ma pierwszeństwo w sprawach projektowych.

Konsekwencja praktyczna: skoro `APP_FALLBACK_LOCALE` też jest `pl`, **brak klucza nie ma na co się zdegradować** i walidator zwraca klientowi surowy klucz (`validation.email`). Dlatego `lang/pl/` musi zawierać komplet plików frameworka — `validation.php`, `auth.php`, `passwords.php`, `pagination.php` — a nie tylko `api.php`. Pilnuje tego test `test_validation_errors_are_translated_not_raw_keys`.

Przy dodawaniu reguł walidacji spoza standardu Laravela dopisujemy klucz do `lang/pl/validation.php` w tym samym kroku.

---

## 7. Do ustalenia

- Model uwierzytelniania: czy tokeny Sanctuma osobowe, czy dostęp maszynowy (client credentials). Wpływa na kształt `/api/v1/auth/*`.
- Rate limiting: limity per token i per IP.
- Zakres domeny produktowej — jakie encje, jakie pola karty (`card()`).

---

## 8. Stan na 2026-08-13

Zainstalowany czysty szkielet Laravel 13 z warstwą API (Sanctum, `routes/api.php`, `HasApiTokens` na modelu `User`). Migracje bazowe wykonane. Zweryfikowane na żywo: `/` 200, `/up` 200, `/api/v1/user` 401 JSON, `.env` niedostępny z zewnątrz.

Logi przestawione na dzienne z retencją 14 dni. Webhook Discorda wpisany do `.env`, kanał niepodpięty.

Repozytorium: `git@github.com:rafalkwasniak/motusy.git`, deploy key `~/.ssh/id_ed25519_motusy` z aliasem `github.com-motusy`.

Koperta odpowiedzi wdrożona i pokryta testami (14 testów, 31 asercji). Aplikacja przestawiona na polski, komplet tłumaczeń frameworka w `lang/pl/`. Prefiks `/api/v1` aktywny. Placeholdery ze skeletonu usunięte.

Nie ma jeszcze: Scramble, trasy docs, `api-guide.html`, `code-map.html`, kanału Discord. Kod produktowy nie został napisany — brak encji domenowych.
