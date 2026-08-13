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
  "message": "Resource retrieved.",
  "data": { },
  "pagination": { }
}
```

### Błąd

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

### Reguły

- `data` tylko gdy jest ładunek. **Kolekcje zawsze zwracają `data: []`**, nigdy nie pomijają klucza — pominięcie zmuszałoby front do rozróżniania „brak klucza" i „pusta lista".
- `pagination` wyłącznie przy listingach stronicowanych.
- `errors` wyłącznie przy błędach walidacji.
- Kody HTTP zostają prawidłowe (422, 401, 403, 404, 500) — koperta ich nie zastępuje.
- **Handler musi wymuszać JSON dla całego `/api/*` niezależnie od nagłówka `Accept`.** Znany defekt szkieletu: bez `Accept: application/json` żądanie do chronionego endpointu zwraca 500 (`Route [login] not defined`) zamiast 401, bo `AuthenticationException` próbuje przekierować na nieistniejącą trasę `login`. Sam `shouldRenderJsonWhen` w `bootstrap/app.php` tego nie łapie. Do naprawienia razem z kopertą.
- Zmiana kształtu zwrotki jest **addytywna** i pokryta testem zamykającym kontrakt.

### Kształt `pagination` — do potwierdzenia

`FOUNDATION.md` narzuca nazwę klucza, nie jego zawartość. Propozycja:

```json
{ "current_page": 1, "per_page": 25, "total": 137, "last_page": 6 }
```

---

## 4. Routing

- Prefiks wersji: **`/api/v1/`**. Wszystkie endpointy produktowe pod nim.
- **Create i update przez POST.** Update rozróżnia ID w URL: `POST /api/v1/res/{id}`. Powód: PHP nie parsuje multipartu dla PUT/PATCH, a spoofing `_method` działa tylko gdy żądanie jest POST-em. `PUT` i `PATCH` nie występują w kontrakcie.
- **Delete przez `DELETE /api/v1/res/{id}`.**
- Walidacja wyłącznie w Form Requestach, kontrolery cienkie, logika w serwisach.

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

## 7. Do ustalenia

- Model uwierzytelniania: czy tokeny Sanctuma osobowe, czy dostęp maszynowy (client credentials). Wpływa na kształt `/api/v1/auth/*`.
- Rate limiting: limity per token i per IP.
- Zakres domeny produktowej — jakie encje, jakie pola karty (`card()`).
- Które dwa locale trzymamy w synchronizacji (obecnie `APP_LOCALE=en`).

---

## 8. Stan na 2026-08-13

Zainstalowany czysty szkielet Laravel 13 z warstwą API (Sanctum, `routes/api.php`, `HasApiTokens` na modelu `User`). Migracje bazowe wykonane. Zweryfikowane na żywo: `/` 200, `/up` 200, `/api/user` 401 JSON, `.env` niedostępny z zewnątrz.

Logi przestawione na dzienne z retencją 14 dni. Webhook Discorda wpisany do `.env`, kanał niepodpięty.

Nie ma jeszcze: repozytorium Git, kontraktu odpowiedzi, prefiksu `/api/v1`, Scramble, trasy docs, kanału Discord, testów. Kod produktowy nie został napisany.
