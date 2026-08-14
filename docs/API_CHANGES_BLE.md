# Motusy — zmiany w API pod wykrywanie BLE w tle

Notatka wykonawcza. Powstała po analizie ograniczeń CoreBluetooth na iOS.
Dotyczy `https://motusy.top/api`, wersja spec z 2026-08-14.

---

## 0. Najważniejsze rozróżnienie — przeczytaj zanim cokolwiek zmienisz

Są **dwa różne identyfikatory** i pomylenie ich rozwala cały system.

| | UUID serwisu | `ble_token` |
|---|---|---|
| Zakres | **jeden, wspólny dla całej aplikacji Motusy** | **jeden na użytkownika** |
| Zmienność | stały, praktycznie na zawsze | rotuje (już masz `refresh_after`) |
| Gdzie leci | w pakiecie rozgłoszeniowym BLE | w charakterystyce GATT, odczytywany po połączeniu |
| Kto go zna | wszyscy, także osoby postronne | tylko ten, kto się faktycznie zbliżył |
| Do czego służy | „w pobliżu jest jakiś użytkownik Motusy" | „to jest konkretnie ten motocyklista" |

UUID serwisu jest publiczny i to jest w porządku — mówi tylko tyle, że w okolicy
jest ktoś z aplikacją, bez ujawniania kto. Tożsamość siedzi w tokenie, który
trzeba dopiero odczytać przez połączenie, i który rotuje.

**Dlaczego tak:** iOS w tle nie nadaje danych producenta ani danych serwisu —
czyli pól, w których pierwotnie miał lecieć token. Zostaje wyłącznie UUID
serwisu, i to tylko dla urządzeń, które z góry wiedzą, jakiego UUID szukają.
Skoro token jest unikalny i rotuje, nikt nie ma jak go zgadnąć. Stałe UUID
rozwiązuje problem: każdy telefon wie, czego szukać, a tożsamość dociąga
połączeniem.

---

## 1. `GET /v1/ble/identity` — dodaj `service_uuid`

**Priorytet: zalecane, nie krytyczne.**

Aplikacja może mieć UUID zaszyty w kodzie, ale wtedy jego zmiana wymaga
wypuszczenia nowej wersji do sklepów. Zwracanie go z API daje możliwość zmiany
bez aktualizacji aplikacji.

Odpowiedź rośnie o jedno pole, reszta bez zmian:

```json
{
  "success": true,
  "message": "Pobrano dane.",
  "data": {
    "token": "a3f9c1e4b7d20856f1a4c9e3b6d80725",
    "service_uuid": "0000FEED-0000-1000-8000-00805F9B34FB",
    "refresh_after": "2026-08-15T10:00:00Z",
    "should_broadcast": true
  },
  "pagination": null
}
```

`service_uuid` to stała po stronie serwera — identyczna dla każdego
użytkownika, nie generowana per konto. Wygeneruj ją raz i zapisz w konfiguracji.

> Uwaga na przyszłość: zmiana tej wartości rozjeżdża stare wersje aplikacji z
> nowymi (przestaną się widzieć). Traktuj jako awaryjny wentyl, nie jako coś,
> co się rutynowo zmienia.

---

## 2. Historia tokenów — walidacja wsteczna

**Priorytet: KRYTYCZNE. Bez tego spotkania będą ginąć.**

To najłatwiejsza do przeoczenia zmiana w całej notatce.

Scenariusz: Anna wykrywa token Marka o 14:00. Telefon Anny nie ma zasięgu i
wysyła zgłoszenie dopiero o 15:30. W międzyczasie token Marka zrotował.
Jeśli serwer szuka tylko **aktualnego** tokenu, zgłoszenie Anny trafi w próżnię
i spotkanie przepadnie.

**Co zrobić:** przechowuj tokeny z okresem ważności zamiast nadpisywać.

```
ble_tokens
  id
  user_id
  token          (32 znaki hex, unikalny)
  valid_from     (timestamp)
  valid_until    (timestamp, NULL dla aktywnego)
```

Rozwiązywanie tokenu w `POST /meetings` szuka wtedy po tokenie **i** sprawdza,
czy `detected_at` mieści się w oknie `valid_from`–`valid_until`.

Przy rotacji (czy to automatycznej z `refresh_after`, czy ręcznej przez
`/rotate`) stary wpis dostaje `valid_until = now()`, a nowy powstaje obok.

Dodaj margines tolerancji — zegary telefonów się rozjeżdżają. Proponuję
±5 minut wokół okna ważności.

Stare tokeny kasuj po jakimś czasie (30–90 dni w zupełności wystarczy),
bo dłużej i tak nikt nie zgłosi spotkania.

**Wyjątek świadomy:** przy `POST /ble/identity/rotate` wywołanym ręcznie przez
użytkownika — czyli gdy ktoś chce przestać być rozpoznawalny — rozważ
natychmiastowe unieważnienie starego tokenu bez okresu karencji. To jest
funkcja prywatności i użytkownik oczekuje, że zadziała od razu. Kosztem będą
utracone zgłoszenia w locie, ale o to właśnie chodzi.

---

## 3. `POST /v1/meetings` — spotkanie zapisywane obu stronom

**Priorytet: KRYTYCZNE. Bez tego połowa par się nie zobaczy.**

**Dlaczego:** Android nie odczyta iPhone'a nadającego w tle (dane lądują w
zamkniętym obszarze pakietu, którego Android nie dekoduje). Ale iPhone widzi
Androida, iPhone widzi iPhone'a, Android widzi Androida. Czyli **każda para jest
pokryta przez co najmniej jeden kierunek wykrycia** — wystarczy, że serwer
rozpropaguje jednostronne zgłoszenie na obie strony.

Macierz, dla jasności:

| | wykrywa Android (tło) | wykrywa iOS (tło) |
|---|---|---|
| **nadaje Android** | tak | tak |
| **nadaje iOS** | **nie** | tak |

Żadna para nie ma obu pól na „nie", więc żadna nie przepada.

### Model danych

Zalecam **jeden wiersz na spotkanie z dwoma uczestnikami**, nie dwa lustrzane
wiersze:

```
meetings
  id
  user_a_id       (zawsze mniejsze id — normalizacja pary)
  user_b_id       (zawsze większe id)
  detected_at
  latitude
  longitude
  reported_by     (id użytkownika, którego telefon zgłosił — do diagnostyki)
```

Normalizacja `user_a_id < user_b_id` daje ci parę nieuporządkowaną, co ogromnie
upraszcza cooldown i deduplikację z punktu 4.

`GET /v1/meetings` i `GET /v1/meetings/{meeting}` wybierają wtedy wiersze, gdzie
zalogowany użytkownik jest którymkolwiek z uczestników, i zwracają **tego
drugiego**. Kontrakt odpowiedzi zostaje dokładnie taki jak teraz — aplikacja
nie widzi różnicy.

Alternatywa z dwoma lustrzanymi wierszami też zadziała, ale musisz je
utrzymywać w synchronizacji przy każdej operacji i cooldown robi się
upierdliwy. Odradzam.

---

## 4. Cooldown i deduplikacja — na parze, nie na zgłaszającym

**Priorytet: KRYTYCZNE. Bez tego dostaniesz duplikaty.**

Skoro **obie strony mogą zgłosić to samo spotkanie**, stara logika przestaje
działać.

**Problem z `event_id`:** generuje go telefon zgłaszający. Anna i Marek podczas
jednego minięcia wygenerują **dwa różne `event_id` dla tego samego zdarzenia**.
Deduplikacja po `event_id` ich nie połączy.

`event_id` zostaje i nadal jest potrzebny — chroni przed dublem, gdy ten sam
telefon ponowi wysyłkę po utracie sieci. Ale to za mało.

**Dołóż deduplikację po parze i oknie czasu.** Przed utworzeniem spotkania:

```
istnieje już wiersz dla pary {user_a, user_b}
gdzie detected_at mieści się w ±N minut od zgłaszanego?
  → nie twórz nowego, zwróć istniejący
```

To samo zapytanie obsługuje jednocześnie trzy przypadki:
- Anna i Marek zgłaszają to samo minięcie (dwa kierunki, jedno spotkanie)
- telefon Anny wykrywa Marka wielokrotnie podczas wspólnej jazdy
- ponowna wysyłka po awarii sieci

Wartość N do dostrojenia. Na start proponuję **30 minut** — dwie osobne jazdy
tego samego dnia zostaną rozdzielone, a jedna wspólna trasa nie wygeneruje
setki wpisów.

---

## 5. `POST /v1/meetings` — opisz odpowiedź `results`

**Priorytet: średni. Bez tego aplikacja nie wie, co się stało.**

Teraz `data.results[]` jest w spec pustą tablicą bez typu. Aplikacja nie ma jak
odróżnić sukcesu od odrzucenia. Skoro piszesz w opisie, że „każdy wpis jest
rozliczany osobno", to rozliczenie trzeba zwrócić.

Proponowany kształt — jeden wynik na każdą przesłaną detekcję, w tej samej
kolejności:

```json
{
  "success": true,
  "message": "Zgłoszenia zostały przetworzone.",
  "data": {
    "results": [
      { "event_id": "evt_001", "status": "created",       "meeting_id": 4821 },
      { "event_id": "evt_002", "status": "cooldown",      "meeting_id": 4700 },
      { "event_id": "evt_003", "status": "duplicate",     "meeting_id": 4821 },
      { "event_id": "evt_004", "status": "unknown_token", "meeting_id": null },
      { "event_id": "evt_005", "status": "expired_token", "meeting_id": null },
      { "event_id": "evt_006", "status": "self",          "meeting_id": null },
      { "event_id": "evt_007", "status": "incognito",     "meeting_id": null }
    ]
  },
  "pagination": null
}
```

Znaczenie statusów:

- `created` — nowe spotkanie
- `cooldown` — para już ma spotkanie w oknie czasowym
- `duplicate` — ten `event_id` już był przetworzony
- `unknown_token` — token nie istnieje w bazie
- `expired_token` — token istniał, ale nie był ważny w `detected_at`
- `self` — użytkownik wykrył sam siebie (zdarza się przy dwóch urządzeniach)
- `incognito` — druga strona ma wyłączoną widoczność

Dzięki temu aplikacja wie, czy usunąć detekcję z lokalnej kolejki, czy ponowić.
Wszystkie statusy poza chwilowymi błędami sieci oznaczają „możesz skasować".

---

## 6. `incognito` — brakuje sposobu, żeby to ustawić

**Priorytet: średni. Funkcja jest zadeklarowana, ale niedostępna.**

Pole `incognito` wraca w każdej odpowiedzi z danymi użytkownika, ale **żaden
endpoint go nie zmienia**. Dopóki tego nie naprawisz, jest to pole tylko do
odczytu, którego nikt nie może przestawić.

Najprościej: dorzuć `incognito` (boolean, opcjonalne) do `UpdateProfileRequest`
w `POST /v1/profile`. Zero nowych ścieżek.

Alternatywa, jeśli chcesz przełącznik dostępny bez wysyłania całego profilu:
osobny `POST /v1/profile/incognito` z jednym polem. Wygodniejsze dla
przełącznika w interfejsie, bo nie wymaga kompletu wymaganych pól profilu.

**Powiązanie z BLE:** `incognito = true` powinno pociągać
`should_broadcast = false` w `GET /v1/ble/identity`, a dodatkowo zgłoszenia
wykrycia takiego użytkownika mają wracać ze statusem `incognito` z punktu 5.
Czyli tryb niewidzialny wyłącza cię w obie strony.

---

## 7. Opcjonalne — do rozważenia, nie na teraz

**Siła sygnału.** Dodaj `rssi` (integer, opcjonalne) do każdej detekcji.
Pozwoli później odsiać kontakty „gdzieś w promieniu 30 metrów" od „staliście
obok siebie na światłach". Przyda się do strojenia progów po spike'cie, a
kosztuje jedno pole.

**Czas trwania kontaktu.** Podczas wspólnej jazdy telefon wykryje tę samą osobę
kilkanaście razy. Zamiast je odrzucać cooldownem w ciszy, możesz przedłużać
istniejące spotkanie — dorzucić `last_seen_at` i `contact_seconds`. Wtedy
odróżnisz „minęliśmy się" od „jechaliśmy razem 40 minut", a to bardzo różne
zdarzenia z punktu widzenia użytkownika.

**Push po spotkaniu.** Masz już `push_token` w `POST /v1/devices`, ale brak
endpointu wysyłki. Naturalne rozszerzenie: powiadomienie do obu stron po
utworzeniu spotkania.

---

## 8. Po stronie aplikacji — kontekst, nie robota dla ciebie

Dla pełności obrazu, żebyś wiedział, co robi druga strona:

- rozgłasza stałe UUID serwisu (z punktu 1)
- wystawia serwer GATT z jedną charakterystyką do odczytu, zawierającą
  `ble_token`
- skanuje w tle **wyłącznie** pod kątem tego jednego UUID (iOS w tle nie
  pozwala skanować „wszystkiego")
- po wykryciu łączy się, odczytuje token, rozłącza
- buforuje detekcje lokalnie i wysyła paczkami po ≤20
- powtarza wysyłkę do skutku, opierając się na `event_id` i statusach z punktu 5

Charakterystyka GATT ma własne stałe UUID — to również stała w kodzie
aplikacji, nie coś z API.

---

## Podsumowanie priorytetów

| # | Zmiana | Priorytet |
|---|---|---|
| 2 | Historia tokenów z okresem ważności | krytyczne |
| 3 | Spotkanie zapisywane obu stronom | krytyczne |
| 4 | Cooldown i dedup na parze | krytyczne |
| 1 | `service_uuid` w `GET /ble/identity` | zalecane |
| 5 | Opisane statusy w `results` | średnie |
| 6 | Ustawianie `incognito` | średnie |
| 7 | `rssi`, czas kontaktu, push | opcjonalne |

Punkty 2, 3 i 4 to jedna spójna zmiana — dotykają tej samej ścieżki i najlepiej
zrobić je razem. Reszta może poczekać.
