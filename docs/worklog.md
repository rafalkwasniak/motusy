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
