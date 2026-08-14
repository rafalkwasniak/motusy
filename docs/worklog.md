# Log prac

Chronologiczny zapis decyzji **wykraczających poza specyfikację** oraz stanu na koniec każdej sesji, zgodnie z `FOUNDATION.md` §6.

Nie powtarza tego, co jest w `CLAUDE.md` (ustalenia obowiązujące) ani w historii gita (co się zmieniło). Zapisuje **dlaczego** i **czego jeszcze nie ma**.

---

## 2026-08-13 — sesja pierwsza

Od pustego katalogu do działającego API z pięcioma domkniętymi etapami.

### Decyzje wykraczające poza specyfikację

**Specyfikacja jest punktem wyjścia, nie wyrocznią.** `docs/motusy-api.md` powstała jako materiał generowany i nie znała zasad z `FOUNDATION.md`. Rozstrzygnięte rozbieżności są w tabeli w `CLAUDE.md` §1. Najważniejsza: §39 chciał `met_user_id` w zgłoszeniu spotkania, ale §112 przypisuje identyfikację do API — przyjęliśmy token BLE, co przy okazji likwiduje otwarty endpoint zamieniający tokeny na tożsamości.

**Rotacja tokenów BLE.** Specyfikacja opisywała pojedynczy token z flagą `active`. Rozszerzone do pełnej rotacji, bo stały identyfikator to dożywotni beacon — wystarczy tani skaner przy bramie, żeby logować przejazdy konkretnej osoby bez aplikacji i bez zgody. Schemat dopuszcza wiele wierszy od początku, bo dołożenie tego później oznaczałoby migrację na żywej bazie.

**Optymalizacja zdjęć.** Nie było w specyfikacji. Poza wagą plików daje rzecz ważniejszą: przekodowanie **usuwa EXIF z współrzędnymi GPS**. Bez tego zdjęcie motocykla zrobione przed domem zdradzałoby adres użytkownika — w aplikacji zbudowanej wokół kontroli nad własną lokalizacją.

**Wyciszanie powtórek w kanale Discord.** Wzorzec z `kramio.pl` wysyła każdy błąd. Dodane ograniczenie do jednego zgłoszenia na kwadrans: zepsuty deploy zamienia każde żądanie w ten sam wyjątek, kanał tonie w kopiach, Discord zaczyna odrzucać wiadomości i ginie alert, który miał znaczenie.

**`INVALID_CREDENTIALS` osobno od `UNAUTHENTICATED`.** Oba zwracają 401, ale aplikacja musi reagować inaczej: złe hasło zostawia użytkownika na formularzu, wygasły token wyrzuca go na ekran logowania.

### Rzeczy, które wyszły w praniu

**Testy wysłały wiadomość na produkcyjny kanał Discorda.** Wychwycił to Rafał, nie ja. Pierwsza naprawa przez `phpunit.xml` nie zadziałała, bo **scache'owany config unieważnia wpisy `<env>`**. Działające rozwiązanie siedzi w `tests/TestCase::setUp()`, plus `Http::preventStrayRequests()` blokujące każde niezamockowane wyjście na zewnątrz.

**Sprostowanie własnej rekomendacji.** Doradziłem obustronne potwierdzenie spotkań, po czym po lekturze §34–36 uznałem, że w pierwotnej formie było złe i zaproponowałem złagodzenie. Rafał wybrał wariant twardszy — spotkanie istnieje wyłącznie po zgłoszeniu z obu telefonów. Jego wersja przy okazji zamyka lukę, o którą się martwiłem: rozpoznanie tokena nie ujawnia tożsamości bez udziału drugiej strony.

**`Illuminate\Image` nie wystarcza samo z siebie.** Zadeklarowałem, że optymalizacja nie wymaga nowej zależności — nieprawda. To nakładka na `intervention/image`, wymienioną w `suggest`, nie w `require`.

### Stan na następny raz

Zbudowane: konto, profil, motocykl, zdjęcia, tożsamość BLE, urządzenia, spotkania. 16 endpointów, 117 testów. Komplet dokumentacji z §4. Discord i cron działają.

**Następny jest Etap 5 — relacje.** Przed startem czekają dwie decyzje:

1. **§16 i §18 specyfikacji** — znajomość „kasuje" wzajemne obserwowanie, ale co dzieje się przy usunięciu znajomego. Łatwo zaimplementować niespójnie i trudno potem naprawić.
2. **Blokada enumeracji kont.** Klucze są sekwencyjne (decyzja Rafała, wbrew mojej rekomendacji UUID), więc endpointy cudzych profili muszą uniemożliwiać przeglądanie kont po kolei.

**Dług do spłacenia przed premierą:** dokumentacja jest publicznie dostępna, a `code-map.html` opisuje wewnętrzną architekturę. Blokada indeksowania to nie kontrola dostępu — ustalone, że docelowo trafi za token.

**Otwarte, nierozstrzygnięte:** marka i model motocykla jako słownik czy wolny tekst; blokowanie i zgłaszanie użytkowników (brak w specyfikacji, przy aplikacji kojarzącej nieznajomych zwykle potrzebne szybciej, niż się zakłada); odzyskiwanie konta po usunięciu wraz z zachowaniem unikalnego indeksu na e-mailu; HEIC z iPhone'a.

**Nie sprawdzone w praktyce:** nic z tego API nie było jeszcze wołane z FlutterFlow. Pierwsze zderzenie z prawdziwym klientem mobilnym jest przed nami.

---

## 2026-08-14 — sesja druga

Testy BLE na dwóch telefonach po stronie FlutterFlow wywróciły jedno z założeń API. Trzy rundy korespondencji technicznej, zapisane w `docs/API_CHANGES_BLE.md` (notatka od FF), `API_CHANGES_BLE_RESPONSE.md`, `API_CHANGES_BLE_REPLY_2.md` i `API_CHANGES_BLE_RESPONSE_3.md`. Warto je czytać po kolei — decyzje mają tam uzasadnienia, których nie ma sensu przepisywać tutaj.

### Co się zmieniło i dlaczego

**Token przenosi się z ramki rozgłoszeniowej do GATT.** iOS w tle nie nadaje danych producenta ani danych serwisu, czyli pól, w których miał lecieć token. Zostaje stałe UUID serwisu — publiczne, wspólne dla całej aplikacji, mówiące wyłącznie „ktoś z Motusy jest w pobliżu". Tożsamość dociąga się połączeniem.

Uboczny skutek jest korzystny i warto go pamiętać: **zdobycie tokena wymaga teraz fizycznej bliskości**, bo trzeba nawiązać połączenie. Poprzedni model pozwalał zbierać tokeny biernie, dowolnym skanerem z kilkudziesięciu metrów.

Traci przy tym ważność uzasadnienie długości tokena z `CLAUDE.md` §6e — 31-bajtowa ramka przestała być ograniczeniem. Wartość zostaje, powód poprawiony.

**Obustronne potwierdzenie spotkań pada.** To była decyzja Rafała z pierwszej sesji i broniła się dobrze, dopóki nie zderzyła się z tym, że **Android nie widzi iPhone'a nadającego w tle**. Dla każdej pary iOS–Android istnieje tylko jeden kierunek wykrycia, więc żadne takie spotkanie nigdy by się nie potwierdziło. Nie część — wszystkie.

Cena: znika ochrona opisana w §6f, czyli „rozpoznanie tokena nie ujawnia tożsamości bez zgody drugiej strony". Płacimy ją świadomie, przy dwóch okolicznościach łagodzących: bliskość fizyczna wymagana do zdobycia tokena oraz to, że sfabrykowane spotkanie **jest widoczne dla ofiary** w jej historii, więc nadużycie przestaje być ciche. Konsekwencją jest limit zapytań na zapisie spotkań i przesunięcie blokowania oraz zgłaszania użytkownika na wcześniejszy etap.

Macierz wykrywania, na której to wszystko stoi, jest na dziś **wnioskiem z dokumentacji Apple, nie pomiarem** — FF przyznał to wprost, zapytany. Stąd kolumna z platformą zgłaszającego w `meeting_reports`: po pierwszym miesiącu ruchu jedno zapytanie zweryfikuje założenie na prawdziwych danych.

**Karencja spotkań zostaje sześciogodzinna.** Zaproponowałem zmianę znaczenia parametru na „przerwę kończącą spotkanie" i wycofałem ją po tym, jak Rafał opisał zwykły dzień pracy: mijasz tego samego motocyklistę rano i dwie godziny później w drodze do urzędu, i to nie są dwa zdarzenia. Blokada liczona od momentu spotkania obsługuje jednocześnie wspólną jazdę i powtórne minięcie tego samego dnia; wariant z przerwą tylko pierwszy przypadek.

**Czas trwania kontaktu wypada z planu.** Przekonał mnie argument, którego nie postawiłem sam: skoro aplikacja zwija powtarzające się wykrycia lokalnie przed wysyłką, serwer z założenia nigdy nie zobaczy pełnego strumienia. Liczony z tego czas byłby wskaźnikiem bez pokrycia w danych.

**Wyścig przy równoczesnym zgłoszeniu obu stron** — realny błąd w moim projekcie, wychwycony przez FF. Sprawdzenie „czy para ma już spotkanie" i wstawienie wiersza to dwa kroki, a po zmianie oba telefony zgłaszają niezależnie i często w zbliżonym czasie. Zamykamy blokadą na znormalizowanej parze. `SELECT ... FOR UPDATE` odpada, bo gdy dla pary nie ma jeszcze żadnego wiersza, nie ma czego blokować.

### Incognito

Potwierdzone przez Rafała, zgodnie ze specyfikacją §45: tryb działa tak, **jakby aplikacji nie było** — nie widzisz i nie jesteś widziany.

Wyjątek, dopisany ponad specyfikację po stronie przychodzącej: **awarie przebijają incognito w obie strony** (§71 opisuje tylko kierunek wychodzący). Uzasadnienie: tryb prywatności nie może kosztować nikogo pomocy na drodze. Do zbudowania w Etapie 6.

To jest powód, dla którego `should_broadcast` i `should_scan` **liczy serwer i zwraca gotowe**. Gdy dojdą awarie, użytkownik z aktywną awarią będzie musiał rozgłaszać mimo trybu — przy fladze wyliczanej w aplikacji ta zmiana wymagałaby wydania nowej wersji do sklepów.

### Rzeczy, które wyszły w praniu

**Testy chodziły po produkcyjnej bazie.** Wyszło przypadkiem, przy pierwszym uruchomieniu suity po nowej migracji: test zgłosił brak tabeli `meeting_reports` na połączeniu `mysql`, mimo że `phpunit.xml` od początku wskazuje SQLite w pamięci. Mechanizm jest ten sam, który rok temu wysłał wiadomość na Discorda — **scache'owany config unieważnia wpisy `<env>`** — ale skutek nieporównywalnie gorszy: `RefreshDatabase` zaczyna od `migrate:fresh`, więc każde `php artisan test` kasowało wszystkie tabele produkcyjne. Nie zauważyliśmy tego przez dwie sesje wyłącznie dlatego, że baza była pusta, a schemat po skasowaniu odtwarzał się identyczny.

Załatane u źródła zamiast obchodzenia: `phpunit.xml` ustawia `APP_CONFIG_CACHE` na nieistniejący plik, więc framework w testach czyta konfigurację z plików i wszystkie `<env>` znów obowiązują. Pierwsza próba — wymuszanie połączenia w `TestCase::refreshApplication()` — została odrzucona, bo walczyła z objawem i rozsypała bazę w pamięci. Skutek uboczny poprawki: suita przyspieszyła z 4,4 s do 1,6 s, bo przestała gadać z MariaDB.

Dołożony `TestDatabaseIsIsolatedTest`, żeby to nie mogło wrócić po cichu.

**`Retry-After` ginął w kopercie.** Obiecaliśmy FF nagłówek przy 429, a własny handler wyjątków budował odpowiedź od zera i gubił nagłówki oryginalnego wyjątku. Wyszło dopiero w teście limitu. `ApiResponse::fromException()` przepuszcza teraz nagłówki każdego `HttpException`.

**Scramble publikował komentarze wewnętrzne.** Opis pola w OpenAPI bierze się z komentarza nad kluczem tablicy — w publicznym kontrakcie wylądowała notatka o funkcji, której jeszcze nie ma, i uzasadnienie decyzji architektonicznej. Uzasadnienia przeniesione do docbloka metody prywatnej, przy polach zostały opisy pisane do klienta.

### Stan na następny raz

Zrobione w tej sesji: Etap 4a (UUID-y BLE, `POST /profile/incognito`, `should_scan`) i cały Etap 4b — przebudowa spotkań. Dwie tabele zamiast jednej, jednostronne zgłoszenie widoczne dla obu stron, dedup i blokada na znormalizowanej parze, płaskie `status`, limit 30/min per konto, `expired_token` oddzielony od `unknown_token`, natychmiastowe unieważnienie przy ręcznej rotacji, tolerancja zegara, `rssi` i platforma w zgłoszeniach, `ble:prune-identities` zamiast `meetings:prune`. 129 testów zielonych.

**Baza przeszła przez `migrate:fresh`** — była pusta, więc bez przenoszenia danych. To ostatni moment, kiedy taki ruch był darmowy.

**Do zweryfikowania w terenie, nie w kodzie:** macierz wykrywania. FF przyznał, że to wniosek z dokumentacji Apple, a nie ich pomiar. Kolumna `platform` w `meeting_reports` jest właśnie po to — po pierwszym miesiącu ruchu jedno zapytanie pokaże, ile spotkań iOS–Android zgłosił wyłącznie iPhone.

**Dług, który urósł:** blokowanie i zgłaszanie użytkownika. Przy jednostronnym zapisie obcy może wejść komuś do historii, a ofiara nie ma czym zareagować. Do zrobienia przed pierwszym realnym ruchem, nie „kiedyś".

**Nadal nie sprawdzone:** czy cron faktycznie woła `schedule:run` — teraz zależy od tego `ble:prune-identities`. Oraz to samo co poprzednio: nic z tego nie było jeszcze wołane z FlutterFlow.

**Etap 5 jest następny.** Obie decyzje sprzed poprzedniej sesji nadal czekają.
