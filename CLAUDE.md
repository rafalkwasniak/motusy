# CLAUDE.md

Specyfika projektu **motusy.top**. Zasady uniwersalne są w [docs/FOUNDATION.md](docs/FOUNDATION.md) i obowiązują zawsze — ten plik ich nie powtarza, tylko doprecyzowuje to, co dotyczy wyłącznie tego projektu.

W razie konfliktu: `FOUNDATION.md` wygrywa w sprawach współpracy (komunikacja, tryb pracy, Git), `CLAUDE.md` wygrywa w sprawach projektowych (stos, kontrakt API, decyzje produktowe).

---

## 1. Czym jest projekt

API pod domeną `motusy.top`, zasilające **aplikację mobilną dla motocyklistów** budowaną we FlutterFlow.

Produkt w skrócie: aplikacja działa w tle, wykrywa innych motocyklistów w pobliżu i zapisuje spotkania. Drugi filar to zgłaszanie awarii wraz z lokalizacją. Do tego relacje społeczne (obserwowanie, znajomi), statusy i tryb incognito.

**Wykrywanie bliskości dzieje się na telefonie, po BLE. API jest magazynem danych, nie silnikiem wykrywania.** Serwer nie zna trasy użytkownika.

### Specyfikacja produktowa

[docs/motusy-api.md](docs/motusy-api.md) — 115 sekcji ze schematami tabel i listą endpointów.

**Uwaga o jej statusie:** została wygenerowana przez AI jako punkt wyjścia i **nie jest zabetonowana**. Nie znała zasad z `FOUNDATION.md`, więc miejscami im przeczy. W razie rozbieżności **nasze ustalenia mają pierwszeństwo przed specyfikacją**, a rozstrzygnięcia zapisujemy tutaj.

Rozbieżności rozstrzygnięte do tej pory:

| Specyfikacja | Ustalenie | Powód |
|---|---|---|
| `meta`, `error: {code, message, fields}` (§97–98) | nasza koperta + `code` | `FOUNDATION.md` §5 narzuca `pagination`; `code` przejęty ze specyfikacji, bo jest dobry |
| `PATCH /profile`, `PATCH /motorcycle` (§88–89) | `POST` | `FOUNDATION.md` §5; dodatkowo PHP nie parsuje multipartu dla PATCH, a te endpointy przyjmują zdjęcia |
| klucze główne `UUID` | `bigint` | decyzja Rafała. Konsekwencja do pilnowania: sekwencyjne ID pozwalają enumerować konta, więc endpointy profilu muszą tego zabraniać autoryzacją |
| brak push w MVP (§110), aplikacja odpytuje API | push z API przez FCM, docelowo | odpytywanie przy 10 000 użytkowników generuje ruch niezależny od tego, czy coś się dzieje |

### Powiadomienia — podział odpowiedzialności

- **Telefon generuje lokalnie** to, co sam wykrył przez BLE: spotkanie, awaria w pobliżu.
- **API wypycha przez FCM** to, o czym telefon nie ma skąd wiedzieć: status znajomego, awaria znajomego, zaproszenie do znajomych.

Firebase jest odłożony, ale **nie projektujemy pod odpytywanie**. `devices` dostaje kolumnę `push_token` od razu, żeby uniknąć późniejszej migracji na żywej bazie połączonej z ponownym zbieraniem tokenów ze wszystkich urządzeń.

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

**Scache'owany config unieważniał wpisy `<env>` z `phpunit.xml`.** Wartości są zapiekane w pliku cache, więc testy czytały ustawienia produkcyjne. Raz kosztowało to wysłanie prawdziwej wiadomości na kanał Discord — ale skutek dużo gorszy wyszedł dopiero teraz: **cała suita chodziła po produkcyjnej bazie**, a `RefreshDatabase` zaczyna od `migrate:fresh`, czyli skasowania wszystkich tabel. Uratowało nas wyłącznie to, że baza była jeszcze pusta.

Zamknięte u źródła: `phpunit.xml` ustawia `APP_CONFIG_CACHE` na nieistniejący plik, więc w testach framework wczytuje konfigurację z plików i wszystkie wpisy `<env>` znów obowiązują — łącznie z bazą SQLite w pamięci. Pilnują tego dwa testy: `tests/Feature/TestDatabaseIsIsolatedTest.php` (baza) i `tests/Feature/NoOutboundCallsInTestsTest.php` (ruch na zewnątrz). Wyciszenia w `tests/TestCase::setUp()` i `Http::preventStrayRequests()` zostają jako druga warstwa.

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
  "code": "VALIDATION_ERROR",
  "message": "Podane dane są nieprawidłowe.",
  "errors": { "email": ["Pole email jest wymagane."] }
}
```

`code` jest **stabilnym identyfikatorem maszynowym** — aplikacja rozgałęzia logikę na nim, nigdy na `message`, który jest tłumaczony i może się zmienić bez uprzedzenia. Obecne kody: `VALIDATION_ERROR`, `UNAUTHENTICATED`, `INVALID_CREDENTIALS`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CONFLICT`, `PROFILE_REQUIRED`, `MOTORCYCLE_REQUIRED`, `TOO_MANY_REQUESTS`, `BAD_REQUEST`, `SERVER_ERROR`.

**`UNAUTHENTICATED` i `INVALID_CREDENTIALS` to celowo różne kody.** Pierwszy znaczy „brak lub wygasły token” — aplikacja ma wylogować i pokazać ekran logowania. Drugi znaczy „złe hasło” — aplikacja zostawia użytkownika na formularzu. Zlanie ich w jedno zmusiłoby FlutterFlow do zgadywania. Nowe kody dopisujemy do tej listy przy dodawaniu endpointów.

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
- **Discord podpięty.** `App\Services\DiscordErrorReporter`, webhook w `.env` pod `DISCORD_WEBHOOK_URL` — nazwa ujednolicona z `kramio.pl`, żeby konwencja była wspólna dla projektów Rafała.

  Wzorzec przeniesiony z `kramio.pl`: natywny embed Discorda, czerwień dla błędów, bursztyn dla `alert()`, pola Type / Location / Source, sześć ramek stosu.

  **Czego reporter nie robi i to jest celowe:**
  - nie wypuszcza błędu dostarczenia — poleciałby z powrotem do handlera wyjątków i zapętliłby się,
  - nie wysyła nic, gdy webhook jest pusty,
  - nie publikuje ścieżek bezwzględnych; są skracane do względnych, żeby nie wystawiać układu serwera.

  **Odstępstwo od wersji z `kramio.pl`:** ten sam błąd jest raportowany **raz na 15 minut** (`services.discord.repeat_minutes`), odcisk palca liczony z klasy, pliku i linii. Powód: zepsuty deploy zamienia każde żądanie w ten sam wyjątek — bez wyciszania kanał tonie w kopiach, Discord zaczyna odrzucać wiadomości i ginie ten alert, który miał znaczenie.

  **`InvalidCredentialsException` jest na liście `dontReport`.** Laravel domyślnie nie raportuje walidacji, autoryzacji ani 404, ale ten wyjątek jest nasz — bez wpisu każde pomylone hasło dzwoniłoby na kanał.
- `LOG_LEVEL` jest teraz `debug` — przy pierwszym realnym ruchu produkcyjnym rozważyć `info` lub `error`.

---

## 6. Dokumentacja

Trzy dokumenty wymagane przez `FOUNDATION.md` §4:

1. **OpenAPI** — `dedoc/scramble` v0.13.x (zweryfikowane: rozwiązuje się czysto pod Laravel 13). Publikuje pod `/docs/api`.
2. **`api-guide.html`** — narracyjny przewodnik, pisany ręcznie. **Jest.** Pod `/docs/api-guide.html`.
3. **`code-map.html`** — mapa kodu, ta sama skórka co przewodnik. **Jest.** Pod `/docs/code-map.html`.

Katalog `docs/` leży poza webrootem, serwowany jedną dynamiczną trasą `docs/{slug}.html` ze slugiem `[A-Za-z0-9_-]+` i dosłownym sufiksem `.html`. Trasa nie koliduje ze Scramble, bo `/docs/api` nie ma sufiksu `.html`.

**Stan:** komplet z §4 gotowy — trasa działa (`App\Http\Controllers\DocsController`), `api-guide.html` i `code-map.html` napisane.

Dorzucenie nowej strony to wrzucenie pliku `.html` do `docs/` — bez dopisywania trasy. Zabezpieczenia: wzorzec sluga odcina ukośniki i kropki, a `realpath` weryfikuje, że plik faktycznie leży w `docs/`. Pokrywa to `tests/Feature/DocsRouteTest.php` — traversal, null byte, ścieżka zagnieżdżona, próba pobrania `.md`.

### Dostęp

Na tym etapie dokumenty są **ogólnodostępne, ale nieindeksowalne**: nagłówek `X-Robots-Tag: noindex, nofollow` na trasie docs plus wpis w `robots.txt`. Blokada indeksowania to nie kontrola dostępu — po zamknięciu prac dokumenty trafiają **za token**.

---

## 6a. Język aplikacji

**Aplikacja jest jednojęzyczna — polski.** `APP_LOCALE=pl`, `APP_FALLBACK_LOCALE=pl`, `APP_FAKER_LOCALE=pl_PL`.

To **świadome odstępstwo od `FOUNDATION.md` §5**, które każe trzymać dwa locale w synchronizacji. Tu drugiego języka nie ma i nie utrzymujemy go — zgodnie z zasadą, że specyfika projektu z `CLAUDE.md` ma pierwszeństwo w sprawach projektowych.

Konsekwencja praktyczna: skoro `APP_FALLBACK_LOCALE` też jest `pl`, **brak klucza nie ma na co się zdegradować** i walidator zwraca klientowi surowy klucz (`validation.email`). Dlatego `lang/pl/` musi zawierać komplet plików frameworka — `validation.php`, `auth.php`, `passwords.php`, `pagination.php` — a nie tylko `api.php`. Pilnuje tego test `test_validation_errors_are_translated_not_raw_keys`.

Przy dodawaniu reguł walidacji spoza standardu Laravela dopisujemy klucz do `lang/pl/validation.php` w tym samym kroku.

---

## 6b. Plan etapów

Kolejność podyktowana tym, czego potrzebuje aplikacja we FlutterFlow, a nie kolejnością rozdziałów specyfikacji.

| Etap | Zakres | Stan |
|---|---|---|
| 0 | Fundament: migracje `users`, `user_profiles`, `motorcycles`, `code` w kopercie, Scramble | **zrobione** |
| 1 | Auth: `register`, `login`, `logout`, `me` | **zrobione** |
| 2 | Profil i motocykl (bez uploadu zdjęć) | **zrobione** |
| 2b | Upload avatara i zdjęcia motocykla | **zrobione** |
| 3 | Tożsamość BLE, urządzenia, `push_token` | **zrobione** |
| 4 | Spotkania: zapis, karencja, historia | **zrobione** |
| 4a | UUID-y BLE w API, przełącznik incognito, `should_scan` | **zrobione** |
| 4b | Przebudowa spotkań pod ograniczenia BLE (`docs/API_CHANGES_BLE_RESPONSE_3.md`) | **zrobione** |
| 5 | Relacje: obserwowanie, zaproszenia, znajomi, liczniki | następny |
| 6 | Statusy i awarie | |
| 7 | Dashboard | |

**Po Etapie 1 przerwa na FlutterFlow.** Rafał buduje logowanie w aplikacji i potwierdza, że komunikacja działa, zanim powstanie więcej endpointów. Sens: wychwycić problemy przy czterech endpointach, nie przy trzydziestu.

Etapy 5 i 6 są niezależne — kolejność do zamiany, jeśli po spotkaniach ważniejsze okażą się awarie.

Upload zdjęć celowo wydzielony do 2b: multipart we FlutterFlow to osobny temat i nie mieszamy go z nauką podstaw.

---

## 6c. Model danych — decyzje

- **Klucze główne: `bigint` auto-increment.** Nie UUID, wbrew specyfikacji.
- **Dane logowania oddzielone od danych osobowych.** `users` trzyma e-mail, hasło i `incognito`; wszystko, co osobowe, jest w `user_profiles`. To nie jest kosmetyka — pozwala usunąć konto, wyczyścić dane osobowe i **zostawić historię spotkań u drugiej strony** w formie zanonimizowanej.
- **Usunięcie konta = soft delete plus anonimizacja**, nie kaskadowe kasowanie. Spotkanie należy do dwóch osób i historia drugiej strony nie może zniknąć.
- **Widoczność pól per odbiorca** przez flagi `*_visible` w `user_profiles`. `card()` **zawsze zwraca ten sam zestaw kluczy**; pola ukryte przychodzą jako `null` lub pusty string. Kształt nigdy się nie zmienia, zmieniają się wartości.

---

## 6d. Pliki

Dysk `public` (`storage/app/public`, symlink `public/storage`), adresy absolutne przez `APP_URL`.

**W bazie trzymamy ścieżkę względną, klientowi zwracamy pełny adres** — zmiana domeny albo dysku nie wymaga wtedy poprawiania danych. Konwersję robi `User::fileUrl()`.

Bezpieczeństwo, bo te pliki lądują pod webrootem:

- nazwa pliku jest generowana (UUID), rozszerzenie bierze się z rozpoznanego typu obrazu, **nigdy z nazwy przesłanego pliku**,
- reguła `image` sprawdza zawartość pliku, nie deklarowany typ ani nazwę — PHP przebrany za `.jpg` dostaje 422 (zweryfikowane na produkcji),
- limit i dozwolone typy w `config/motusy.php`.

**Przetwarzanie obrazu** przez `Illuminate\Image` (Laravel 13) na sterowniku `intervention/image` — ta paczka jest w `suggest`, nie w `require`, więc trzeba ją mieć jawnie w `composer.json`. Serwer ma `gd`, `imagick` i `exif`; domyślny sterownik to `gd`.

Kolejność operacji ma znaczenie: `orient()` **przed** skalowaniem, bo flaga obrotu siedzi w EXIF, który ginie przy przekodowaniu.

`scale()` mapuje się na `scaleDown` — proporcje zostają, obrazy mniejsze od limitu nie są powiększane. Wymiary, jakość i format w `config/motusy.php`.

Przekodowanie **usuwa EXIF, w tym współrzędne GPS** miejsca wykonania zdjęcia. To nie jest efekt uboczny, tylko wymóg — bez tego zdjęcie motocykla zrobione przed domem zdradzałoby adres użytkownika.

Podmiana zdjęcia kasuje poprzedni plik, ale **dopiero po udanym zapisie nowego** — nieudany zapis nie zostawia rekordu wskazującego na nieistniejący plik.

Upload doczepia się do istniejącego profilu lub motocykla. Gdy ich nie ma, zwracamy `409` z kodem `PROFILE_REQUIRED` albo `MOTORCYCLE_REQUIRED`, zamiast cicho tworzyć niepełny rekord.

---

## 6e. BLE i urządzenia

**Tokeny BLE rotują.** Decyzja Rafała, wbrew prostszemu wariantowi ze stałym tokenem. Powód: stały identyfikator to dożywotni beacon — ktoś ze skanerem BLE mógłby bez aplikacji i bez zgody logować przejazdy konkretnej osoby. Schemat dopuszcza wiele wierszy na użytkownika z flagą `active`, więc częstotliwość rotacji to jedna liczba w `config/motusy.php`, a nie migracja.

Retirowany token **pozostaje rozpoznawalny przez okno karencji** (`resolvable_after_rotation_hours`), bo specyfikacja §47 dopuszcza spotkania zapisane offline i wysłane później.

Token ma 128 bitów zapisanych szesnastkowo. Długość nie jest niczym ograniczona od góry — token **nie leci w ramce rozgłoszeniowej**, tylko w charakterystyce GATT, odczytywanej po nawiązaniu połączenia. (Wcześniej stało tu, że 31-bajtowa ramka BLE jest limitem; przestało to obowiązywać wraz z przeniesieniem tokena do GATT-a.)

**Rozgłaszany jest stały `service_uuid`, wspólny dla całej aplikacji.** Mówi wyłącznie „ktoś z Motusy jest w pobliżu", nigdy kto. Powód jest wymuszony przez iOS: w tle nie nadaje on ani danych producenta, ani danych serwisu, czyli pól, w których pierwotnie miał lecieć token. Zostaje UUID serwisu i tylko dla urządzeń, które z góry wiedzą, czego szukać.

Uboczny skutek działa na naszą korzyść: **zdobycie tokena wymaga fizycznej bliskości**, bo trzeba się połączyć. Poprzedni model pozwalał zbierać tokeny biernie, dowolnym skanerem z kilkudziesięciu metrów.

`service_uuid` i `characteristic_uuid` siedzą w `config/motusy.php` i wracają z `GET /ble/identity`, żeby dało się je zmienić bez wydania nowej wersji aplikacji. Aplikacja ma je buforować i mieć zaszyte jako awaryjne — skanowanie musi działać bez zasięgu. Zmiana rozjeżdża stare wersje z nowymi, więc to wentyl awaryjny, nie pokrętło.

Rotacja dzieje się **po stronie serwera i leniwie**: `GET /ble/identity` sam podmienia token, gdy poprzedni się zestarzeje. Telefon bez zasięgu o nic nie prosi, więc jego token pozostaje aktywny, zamiast wygasnąć bez jego wiedzy. Aplikacja tylko pyta, więc zmiana polityki nie wymaga wydania nowej wersji aplikacji.

**Karencja tokena nie może być krótsza niż `meetings.max_report_age_hours`.** Inaczej powstaje martwa strefa: aplikacja uznaje zaległą detekcję za wciąż wysyłalną, wysyła ją i dostaje „nieznany token". Pilnuje tego test `test_token_grace_period_outlasts_the_window_for_late_reports`.

**Urządzenie jest wiązane z tokenem Sanctuma** (`devices.personal_access_token_id`). Bez tego nie da się sensownie ani wylogować pojedynczego urządzenia, ani zaadresować push do konkretnego telefonu.

`device_id` jest unikalny **w obrębie konta**, nie globalnie — to samo urządzenie może obsługiwać dwa konta.

### Incognito

**Tryb ma działać tak, jakby aplikacji nie było** — decyzja Rafała, zgodna ze specyfikacją §45. Nie widzisz i nie jesteś widziany: `should_broadcast: false`, `should_scan: false`, a zgłoszenia spotkań z udziałem takiej osoby są odrzucane po obu stronach. Historia sprzed włączenia trybu zostaje nietknięta.

**Wyjątek: awarie przebijają incognito w obie strony.** Specyfikacja §71 opisuje tylko kierunek wychodzący (własna awaria idzie dalej mimo trybu); kierunek przychodzący — że w incognito nadal odbierasz cudze awarie — to decyzja Rafała ponad specyfikację. Uzasadnienie: tryb prywatności nie może kosztować nikogo pomocy na drodze. Do zbudowania w Etapie 6.

Z tego wyjątku wynika reguła implementacyjna: **`should_broadcast` i `should_scan` liczy serwer i zwraca gotowe.** Gdy dojdą awarie, użytkownik z aktywną awarią będzie musiał rozgłaszać mimo trybu — przy fladze wyliczanej w aplikacji ta zmiana wymagałaby wydania nowej wersji do sklepów.

Nazwa „incognito" sugeruje „ja widzę, mnie nie widać", czyli coś innego, niż ten tryb robi. Etykieta w interfejsie do przemyślenia po stronie produktu.

---

## 6f. Identyfikacja a powiadomienia

Specyfikacja §112 przypisuje identyfikację użytkownika do API: telefon widzi tylko token, nie wie, kto to.

**Decyzja Rafała: powiadomienie ma zawierać nick.** Wynika z tego, że rozpoznanie tokena musi wrócić **od razu przy zgłoszeniu spotkania**. Aplikacja dostaje kartę spotkanej osoby w odpowiedzi i nie musi dobijać drugim żądaniem.

**Rozpoznanie tokena ujawnia tożsamość każdemu, kto ten token ma.** Wcześniej osłaniało to obustronne potwierdzenie; po przebudowie z §6g tej osłony nie ma. Co ją zastępuje:

- **token trzeba odczytać przez połączenie GATT**, więc trzeba było fizycznie stanąć obok. Bierne zbieranie z kilkudziesięciu metrów, możliwe przy tokenie w ramce rozgłoszeniowej, przestało być wykonalne,
- **rotacja** zamyka okno dla tokenów zebranych wcześniej,
- **sfabrykowane spotkanie widzi ofiara**, bo wpis trafia do historii obu stron. Nadużycie przestaje być ciche,
- **limit 30 żądań na minutę** ogranicza tempo.

Konsekwencja, którą trzeba dowieźć: **blokowanie i zgłaszanie użytkownika awansuje z „kiedyś" na „przy pierwszym realnym ruchu"**. Bez tego ofiara widzi nadużycie i nic nie może zrobić.

Wymagania wydajnościowe, wprost od Rafała:

- endpoint spotkań musi przyjmować **wiele tokenów naraz**, nie jedno żądanie na każdego mijanego motocyklistę,
- karencja egzekwowana **po stronie serwera**, żeby naiwny klient nie zalał API przy jeździe w grupie,
- aplikacja dodatkowo pilnuje lokalnie, żeby nie zgłaszać w kółko tego samego tokena.

---

## 6g. Spotkania

**Jedno zgłoszenie zapisuje spotkanie obu stronom.**

Poprzednia reguła — spotkanie istnieje dopiero, gdy zgłoszą je oba telefony — **upadła po testach BLE na dwóch telefonach**. Powód jest twardy: iPhone nadający w tle jest niewidoczny dla Androida, więc w każdej parze iOS–Android wykrycie idzie wyłącznie w jedną stronę. Przy wymogu obustronności **żadne takie spotkanie nigdy by nie powstało** — nie część, wszystkie. Cała korespondencja: `docs/API_CHANGES_BLE*.md`.

Argument, który przesądził niezależnie od macierzy wykrywania: obustronne potwierdzenie sypie się przy **każdej** asymetrii, także tej wynikającej ze zwykłego pecha w oknie skanowania.

Cena i to, co ją równoważy — w §6f.

### Model danych

**Dwie tabele.** `meetings` to jeden wiersz na spotkanie, wspólny dla obojga, z parą znormalizowaną (`user_a_id < user_b_id`) — dzięki temu wyszukanie po parze to jeden warunek, a nie dwa. `meeting_reports` to co faktycznie wysłał pojedynczy telefon.

Podział nie jest kosmetyką, obsługuje trzy rzeczy naraz:

- **własny GPS i czas każdej ze stron** (spec §34–35) — przy zgłoszeniu z obu telefonów są dwa zestawy i szkoda je gubić,
- **idempotencja per zgłaszający** — `event_id` generuje każdy telefon osobno, więc dla jednego spotkania istnieją dwa różne i wspólny wiersz nie miałby gdzie pomieścić drugiego. Unikalność siedzi na `(reporter_id, event_id)`,
- **kolumna `platform`** — pozwala sprawdzić na prawdziwym ruchu założenie, na którym stoi cała ta przebudowa. FF przyznał wprost, że macierz wykrywania jest wnioskiem z dokumentacji Apple, a nie ich pomiarem.

### Ustalenia liczbowe (wszystkie w `config/motusy.php`)

| Ustawienie | Wartość | Dlaczego |
|---|---|---|
| `cooldown_hours` | 6 | jedno okno obsługuje wspólną jazdę i powtórne minięcie tego samego dnia |
| `max_report_age_hours` | 24 | zgłoszenia offline; starsze to raczej śmieci niż jazda |
| `clock_tolerance_minutes` | 5 | zegary telefonów się rozjeżdżają |
| `max_batch_size` | 20 | mijając grupę wykrywasz kilku naraz |
| `throttle` | 30/min per użytkownik | jednostronny zapis wpisuje się komuś do historii |

**Dlaczego 6 godzin, a nie „przerwa kończąca spotkanie".** Proponowałem zmianę znaczenia parametru na odstęp między wykryciami; Rafał to odrzucił swoim dniem pracy: mijasz tego samego motocyklistę o 8:00 jadąc do pracy i o 10:00 jadąc do urzędu — to jedno zdarzenie, nie dwa. Blokada liczona od momentu spotkania obsługuje jednocześnie wspólną trasę i powtórne minięcie; wariant z przerwą tylko pierwsze.

**Czas trwania kontaktu świadomie odrzucony.** Aplikacja zwija powtarzające się wykrycia lokalnie przed wysyłką, więc serwer z założenia nie widzi pełnego strumienia. Liczony z tego czas byłby wskaźnikiem bez pokrycia w danych.

### Pułapki rozwiązane

- **Wyścig przy równoczesnym zgłoszeniu obu stron.** Sprawdzenie „czy para ma spotkanie" i wstawienie to dwa kroki; obie kolejki opróżniają się po powrocie zasięgu, oba żądania nie znajdują nic i oba tworzą wpis. Zamknięte **nazwaną blokadą na znormalizowanej parze** (`Cache::lock`, cache stoi na bazie). `SELECT ... FOR UPDATE` odrzucone: gdy dla pary nie ma jeszcze żadnego wiersza, nie ma czego blokować.
- **Karencja liczona względem `detected_at`, nie chwili zapisu.** Inaczej dwadzieścia zaległych zgłoszeń wysłanych po powrocie w zasięg dałoby dwadzieścia wpisów. Okno działa w obie strony, bo zaległości przychodzą w losowej kolejności.
- **Idempotencja przez `event_id`** generowany przez aplikację (§48). Ponowione żądanie zwraca oryginalny wynik razem z kartą.
- **Incognito sprawdzane po obu stronach** (§45).
- **`unknown_token` oddzielony od `expired_token`.** Dla aplikacji reakcja ta sama, dla nas to różnica między „ktoś podrzuca śmieci" a „zaległość przyszła po czasie".
- **Tolerancja rozjazdu zegara.** Bez niej telefon spieszący się o dwie minuty tracił każde zgłoszenie.
- **Błędy strukturalne to 422, wyniki biznesowe to 200 ze `status`** — jedno odrzucone wykrycie nie topi całej paczki. Statusy: `created`, `cooldown`, `duplicate`, `unknown_token`, `expired_token`, `self`, `incognito`, `too_old`, `invalid_time`. Wszystkie znaczą „skasuj z kolejki"; ponawiać wolno wyłącznie przy błędzie sieci, 5xx i 429.
- **429 niesie `Retry-After`.** Koperta błędu przepuszcza nagłówki wyjątków HTTP — bez tego aplikacja musiałaby zgadywać odstęp.

### Sprzątanie

`meetings:prune` **zniknęło** — nie ma już niepotwierdzonych zgłoszeń. Zastąpiła je `ble:prune-identities` (raz na dobę): kasuje wycofane tokeny starsze niż `ble.prune_retired_after_days`. Wcześniej **nic ich nie kasowało**, a rotacja zostawia wiersz za każdym razem.

### Rozbieżność ze specyfikacją

§39 pokazuje żądanie z `met_user_id`, ale §112 mówi, że identyfikacja należy do API, a telefon zna tylko token. Przyjęliśmy `ble_token` w żądaniu: jedno wywołanie zamiast dwóch i **brak otwartego endpointu zamieniającego tokeny na tożsamości**, który byłby narzędziem do masowego zbierania danych.

---
## 7. Do ustalenia

- **Odzyskiwanie konta po usunięciu.** Pomysł Rafała: gdy ktoś rejestruje się na adres należący do usuniętego konta, system pyta, czy podnieść stare konto, czy założyć nowe. Obecny schemat już to umożliwia — `users` ma soft delete, więc wiersz zostaje. **Ale jest tu haczyk:** unikalny indeks na `email` obejmuje też wiersze usunięte, a reguła `unique:users,email` ich nie wyklucza. Dopóki nie ma endpointu usuwania konta, nic się nie psuje. Zanim powstanie, trzeba rozstrzygnąć: albo indeks unikalny liczy tylko żywe konta, albo rejestracja na zajęty-ale-usunięty adres celowo wpada w ścieżkę „podnieś konto".

- Limity długości pól, karencja spotkań, czasy statusów i awarii — specyfikacja §115 ma pełną listę piętnastu pozycji. Rozstrzygamy je etapami, przy dochodzeniu do konkretnego etapu, nie wszystkie naraz.
- Rate limiting poza auth: awarie. Dla zapisu spotkań ustalone — **30/min per użytkownik**, nie per IP (zlot za jednym NAT-em operatora blokowałby się nawzajem); wchodzi z Etapem 4b. Auth ma limit 10/min per IP.
- Czy cron na serwerze jest skonfigurowany (`schedule:run` co minutę). Bez niego `ble:prune-identities` nie zadziała i wycofane tokeny będą się odkładać bez końca.
- Nick **nie jest unikalny** (decyzja Rafała); unikalny jest wyłącznie e-mail.
- HEIC z iPhone'a: `imagick` go obsługuje, `gd` nie, a reguła walidacji `image` i tak go nie przepuszcza. Większość pickerów we Flutterze konwertuje HEIC do JPEG przed wysyłką — do sprawdzenia na prawdziwym iPhonie, zanim uznamy temat za zamknięty.
- Marka i model motocykla: słownik czy wolny tekst. Różnica między danymi filtrowalnymi a bałaganem literówek.
- Blokowanie i zgłaszanie użytkownika — nie ma tego w specyfikacji, a przy aplikacji kojarzącej nieznajomych w terenie zwykle okazuje się potrzebne szybciej, niż się zakłada.

---

## 7a. Endpointy

Baza dla FlutterFlow: `https://motusy.top/api/v1`

| Metoda | Ścieżka | Auth | Zwraca |
|---|---|---|---|
| POST | `/auth/register` | nie | `201`, `data.token`, `data.user` |
| POST | `/auth/login` | nie | `200`, `data.token`, `data.user` |
| POST | `/auth/logout` | tak | `200`, unieważnia **tylko bieżący** token |
| GET | `/auth/me` | tak | `200`, `data` = konto użytkownika |

Token przekazujemy nagłówkiem `Authorization: Bearer <token>`.

`register` i `login` przyjmują opcjonalne `device_name` — nazwa tokena. Dzięki temu wylogowanie na telefonie nie wyrzuca użytkownika z tabletu. Domyślnie `mobile`.

`data.user.profile_complete` mówi aplikacji, czy pokazać onboarding. Po samej rejestracji jest `false`, bo profil i motocykl powstają dopiero w Etapie 2.

| POST | `/profile` | tak | `200`, `data` = konto po zapisie |
| POST | `/profile/incognito` | tak | `200`, jedno pole `incognito`, zwraca konto |
| POST | `/motorcycle` | tak | `200`, `data` = konto po zapisie |
| POST | `/profile/avatar` | tak | `200`, multipart, pole `avatar` |
| DELETE | `/profile/avatar` | tak | `200` |
| POST | `/motorcycle/photo` | tak | `200`, multipart, pole `photo` |
| GET | `/ble/identity` | tak | `200`, token, UUID-y BLE, `should_broadcast`, `should_scan` |
| POST | `/ble/identity/rotate` | tak | `200`, wymuszona zmiana tożsamości |
| POST | `/devices` | tak | `200`, upsert po `device_id` |
| POST | `/meetings` | tak | `200`, tablica wykryć, `status` per element; limit 30/min |
| GET | `/meetings` | tak | `200`, historia stronicowana, obie strony spotkania |
| GET | `/meetings/{id}` | tak | `200`, szczegóły dla obu uczestników; cudze `404` |
| DELETE | `/motorcycle/photo` | tak | `200` |

`/profile` i `/motorcycle` są **upsertami** — aplikacja wysyła cały formularz i nie musi wiedzieć, czy to pierwszy zapis po rejestracji, czy późniejsza edycja. Oba zwracają pełne konto, więc po zapisie nie trzeba wołać `/auth/me`.

Płeć to stabilny kod: `male`, `female`, `other`. Lista w `config/motusy.php`, etykiety po stronie aplikacji.

Limity długości pól i rocznika też siedzą w `config/motusy.php`, zgodnie z `FOUNDATION.md` §5.

Kontrakt OpenAPI: `/docs/api` — dokumentuje też, które endpointy wymagają tokena.

---

## 7b. Log prac

[docs/worklog.md](docs/worklog.md) — chronologiczny zapis decyzji wykraczających poza specyfikację i stanu na koniec sesji, wymagany przez `FOUNDATION.md` §6. Nie powtarza ustaleń z tego pliku ani historii gita: zapisuje **dlaczego** i **czego jeszcze nie ma**.

Dopisujemy do niego na koniec każdej sesji.

---

## 8. Stan na 2026-08-13

Zainstalowany czysty szkielet Laravel 13 z warstwą API (Sanctum, `routes/api.php`, `HasApiTokens` na modelu `User`). Migracje bazowe wykonane. Zweryfikowane na żywo: `/` 200, `/up` 200, `/api/v1/user` 401 JSON, `.env` niedostępny z zewnątrz.

Logi dzienne z retencją 14 dni. Kanał Discord podpięty i zweryfikowany na żywym webhooku.

Repozytorium: `git@github.com:rafalkwasniak/motusy.git`, deploy key `~/.ssh/id_ed25519_motusy` z aliasem `github.com-motusy`.

Koperta odpowiedzi wdrożona i pokryta testami (14 testów, 32 asercje), z polem `code`. Aplikacja przestawiona na polski, komplet tłumaczeń frameworka w `lang/pl/`. Prefiks `/api/v1` aktywny.

**Etap 0 zamknięty.** Schemat bazy przebudowany od zera (`migrate:fresh`): `users` bez kolumny `name`, z `incognito` i `deleted_at`; nowe `user_profiles` i `motorcycles`. Modele `User`, `UserProfile`, `Motorcycle` z relacjami 1:1. Scramble wystawia kontrakt pod `/docs/api` z nagłówkiem `X-Robots-Tag: noindex, nofollow` i wpisem w `robots.txt`.

Komplet dokumentacji z `FOUNDATION.md` §4 gotowy. Żadnego endpointu produktowego — Etap 1 jest następny. Kod produktowy nie został napisany — brak encji domenowych.
