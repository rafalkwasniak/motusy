# Odpowiedź na notatkę „Zmiany w API pod wykrywanie BLE w tle"

Przeczytaliśmy notatkę i skonfrontowaliśmy ją z kodem, który stoi na produkcji.
Notatka jest trafna technicznie, ale była pisana przeciw specyfikacji, a nie
przeciw implementacji — a te dwie rzeczy zdążyły się rozjechać. Dlatego część
uwag dotyczy rzeczy, które już działają, tylko nie zostały opisane.

Poniżej punkt po punkcie: co przyjmujemy, co już jest, co zrobimy inaczej i
dlaczego. Na końcu kontrakt, jaki zobaczy aplikacja, oraz kilka rzeczy do
ustalenia po Waszej stronie.

Kluczowa wiadomość: **wszystko, co notatka ma osiągnąć, osiągnie.** Różnice
dotyczą sposobu, nie celu.

---

## 0. Rozróżnienie UUID serwisu vs `ble_token`

**Macie rację i przyjmujemy to w całości.** To jest najważniejsza część notatki
i jest postawiona dokładnie tak, jak trzeba: publiczny, stały UUID serwisu mówi
„ktoś z Motusy jest w pobliżu", a tożsamość siedzi w rotującym tokenie, który
trzeba dopiero odczytać przez połączenie.

Zwracamy uwagę na uboczny skutek, który działa na naszą korzyść i warto go mieć
świadomie: przeniesienie tokena z pakietu rozgłoszeniowego do GATT-a **zawęża**
pole nadużycia, a nie poszerza. W pierwotnym modelu token leciał w reklamie,
więc dowolny skaner w promieniu kilkudziesięciu metrów zbierał go biernie i po
cichu. Teraz trzeba nawiązać połączenie, czyli fizycznie się zbliżyć. To ma
znaczenie dla punktu 3.

Drobna konsekwencja dokumentacyjna po naszej stronie: mieliśmy zapisane, że
token nie może być dłuższy niż 128 bitów, bo ramka BLE ma około 31 bajtów. To
uzasadnienie przestaje obowiązywać, skoro token nie leci już w ramce. Długości
nie zmieniamy (128 bitów w zupełności wystarcza), ale poprawimy powód w
dokumentacji, żeby za rok nikt nie wyciągnął z niego złego wniosku.

---

## 1. `service_uuid` w `GET /ble/identity`

**Racja, robimy.** Z dwiema poprawkami.

**UUID z przykładu jest ryzykowny.** `0000FEED-0000-1000-8000-00805F9B34FB` to
zapis 16-bitowego UUID-a z przestrzeni przydzielanej przez Bluetooth SIG.
`FEED` nie jest nasze i kolizja z inną aplikacją jest realna — a kolizja tutaj
oznacza, że nasze telefony zaczną wykrywać cudze urządzenia i próbować z nich
czytać charakterystykę, której tam nie ma. Wygenerujemy pełny, losowy UUID v4.

**Zwrócimy też UUID charakterystyki GATT.** Notatka słusznie zauważa, że
trzymanie UUID-a w API zamiast w kodzie daje możliwość zmiany bez wydania
aplikacji — ale jeśli charakterystyka zostaje zaszyta w kodzie, to ta dźwignia
jest połowiczna. Zmiana samego serwisu i tak wymusiłaby wydanie. Skoro
zwracamy jedno, zwracamy oba.

**Uwaga dla aplikacji, ważna:** skanowanie musi działać bez sieci. Wartości z
API traktujcie jako konfigurację do zbuforowania przy pierwszym udanym
pobraniu, z wartością zaszytą w kodzie jako fallback. Nie odpytujcie API przed
każdym startem skanowania — telefon w trasie bywa bez zasięgu, a to jest
dokładnie ta sytuacja, w której aplikacja ma działać.

---

## 2. Historia tokenów — to już mamy

**Diagnoza jest słuszna, ale problem jest rozwiązany od Etapu 3.** Nie
nadpisujemy tokenów. Tabela `ble_identities` trzyma wiele wierszy na
użytkownika:

```
ble_identities
  id, user_id
  token       (32 znaki hex, unikalny globalnie)
  active      (czy to ten aktualnie rozgłaszany)
  expires_at  (NULL dla aktywnego; ustawiane przy wycofaniu)
```

Rozwiązywanie tokena bierze wiersze, które są aktywne **albo** mają
`expires_at` w przyszłości. Karencja po rotacji wynosi 72 godziny i siedzi w
konfiguracji, więc jest jedną liczbą do zmiany, nie migracją.

To działa nieco łagodniej niż Wasza propozycja z `valid_from`/`valid_until`:
nie sprawdzamy, czy `detected_at` mieści się w oknie ważności — wystarczy, że
token jeszcze nie wygasł. Efekt dla Was jest ten sam i o jeden przypadek
brzegowy lepszy, więc tej części nie przebudowujemy. Scenariusz z notatki
(Anna wykrywa o 14:00, wysyła o 15:30, token w międzyczasie zrotował) już dziś
kończy się poprawnie zapisanym spotkaniem.

**Natomiast trzy rzeczy w tym obszarze faktycznie poprawiamy i dwie z nich
sami wskazaliście:**

1. **Ręczna rotacja unieważni stary token natychmiast.** To Wasz „wyjątek
   świadomy" z §2 i macie rację — dziś `POST /ble/identity/rotate` daje staremu
   tokenowi te same 72 godziny karencji co rotacja automatyczna, więc przycisk
   „resetuj tożsamość" nie robi tego, co obiecuje. Rozdzielimy oba przypadki:
   rotacja automatyczna zachowuje karencję, ręczna kasuje rozpoznawalność od
   razu. Detekcje w locie przepadną i o to chodzi.
2. **Tolerancja na rozjazd zegara.** Racja, że zegary telefonów się rozjeżdżają,
   choć u nas boli to w innym miejscu, niż wskazujecie: nie przy oknie ważności
   tokena, tylko przy odrzucaniu detekcji z `detected_at` w przyszłości. Dziś
   telefon spieszący się o dwie minuty traci zgłoszenie. Dodamy tolerancję
   pięciu minut.
3. **Sprzątanie starych tokenów.** Racja, nic ich dziś nie kasuje. Dojdzie
   komenda czyszcząca wiersze wygasłe dawniej niż ustalony próg.

Dorzucamy też rozdzielenie statusów `unknown_token` i `expired_token`, o które
prosicie w punkcie 5 — dziś oba wracają jako `unknown_token`. Dla aplikacji
reakcja jest ta sama (skasuj z kolejki), ale dla nas to różnica między
„ktoś podrzuca śmieci" a „ktoś wysłał zaległości po tygodniu".

---

## 3. Spotkanie zapisywane obu stronom

**Macie rację i jest gorzej, niż piszecie.**

Nasza dotychczasowa reguła brzmiała: spotkanie istnieje dopiero, gdy zgłoszą je
**oba** telefony. Jednostronne zgłoszenie zostawało w bazie wyłącznie po to, by
druga strona miała się z czym sparować — nie trafiało do historii i nie
zwracało niczyich danych.

Zestawcie to z Waszą macierzą. Dla każdej pary iOS–Android istnieje tylko jeden
kierunek wykrycia. Czyli przy naszej regule **żadne spotkanie iPhone–Android
nigdy by się nie zapisało.** Nie „część by ginęła" — wszystkie. To nie jest
strojenie parametru, to jest awarią założenia i dlatego zmieniamy je bez
dyskusji.

### Cena, którą świadomie płacimy

Obustronne potwierdzenie pełniło u nas jeszcze jedną funkcję, o której notatka
nie mogła wiedzieć: chroniło przed zbieraniem tożsamości. Ktoś, kto zebrałby
cudze tokeny i sfabrykował zgłoszenia, nie dowiadywał się niczego, bo ofiara nie
zgłaszała spotkania z nim. Po zmianie ta ochrona znika — jednostronne zgłoszenie
ujawni tożsamość zgłaszającemu.

Akceptujemy to, bo dwie rzeczy tę cenę mocno obniżają:

- **Model GATT z punktu 0 wymaga fizycznej bliskości.** Żeby zdobyć token,
  trzeba się połączyć. Bierne zbieranie z dystansu, które było ryzykiem w
  modelu z tokenem w reklamie, przestaje być możliwe.
- **Nadużycie przestaje być ciche.** Skoro spotkanie widzą obie strony, ten kto
  je sfabrykuje, wchodzi ofierze do historii i jest tam widoczny.

Konsekwencje, które z tego wyciągamy: dokładamy limit zapytań na
`POST /meetings` (o tym niżej) i przesuwamy blokowanie oraz zgłaszanie
użytkownika na wcześniejszy etap, niż planowaliśmy. **To oznacza ekran i
przyciski po Waszej stronie** — warto ująć w planach.

### Model danych — zrobimy inaczej niż proponujecie

Normalizacja pary przez `user_a_id < user_b_id` jest dobra i ją bierzemy.
Odradzanie dwóch lustrzanych wierszy też jest słuszne. Ale samego płaskiego
wiersza nie zrobimy, bo gubi dwie rzeczy:

- **Własny GPS i własny czas każdej ze stron.** Specyfikacja świadomie chciała
  je zachować, bo dwa telefony nigdy nie są w tym samym miejscu w tej samej
  sekundzie. Przy jednostronnym zgłoszeniu to i tak zwykle będzie jeden zestaw,
  ale przy parach iOS–iOS i Android–Android będą dwa i szkoda je wyrzucać.
- **Idempotencję `event_id`.** Sami piszecie, że zgłaszający generują różne
  `event_id` dla tego samego zdarzenia. Skoro tak, to jeden wiersz nie ma gdzie
  pomieścić dwóch `event_id` — a bez tego ponowiona wysyłka drugiego zgłaszającego
  nie ma po czym się rozpoznać.

Dlatego dzielimy to na dwie tabele:

```
meetings
  id, user_a_id, user_b_id (a < b), first_detected_at, last_detected_at,
  latitude, longitude

meeting_reports
  id, meeting_id, reporter_id, event_id, detected_at,
  latitude, longitude, rssi
  unique(reporter_id, event_id)
```

Kosztuje to jeden join przy zapisie. W zamian idempotencja zostaje ścisła dla
obu stron, a czas trwania kontaktu z Waszego punktu 7 wychodzi za darmo.

**Z zewnątrz kontrakt zostaje taki, jak piszecie** — listing i szczegóły
wybierają wiersze, w których zalogowany jest którymkolwiek z uczestników, i
zwracają tego drugiego. Aplikacja nie widzi różnicy.

**Jedna implikacja dla aplikacji, łatwa do przeoczenia:** w historii pojawią się
spotkania, których ten telefon **nigdy nie zgłosił**, bo zgłosiła je druga
strona. Historia przestaje być sumą własnych wysyłek. Jeżeli macie po swojej
stronie lokalny cache spotkań budowany z własnych zgłoszeń, trzeba go oprzeć na
tym, co zwraca `GET /meetings`.

---

## 4. Cooldown i deduplikacja na parze

**Mechanizm: racja, robimy dokładnie tak.** Dedup po parze i oknie czasu, z
`event_id` zachowanym jako druga, węższa ochrona przed ponowioną wysyłką z tego
samego telefonu. Zgadzamy się też, że jedno zapytanie obsługuje wszystkie trzy
przypadki, które wymieniacie.

**Liczba: skorygujemy, ale w Waszą stronę.** Mieliśmy dotąd ustalone okno
sześciu godzin. Wasze trzydzieści minut wygląda przy tym na zbyt agresywne —
tylko że oba parametry znaczą co innego, więc porównanie jest mylące.

Skoro dokładamy `last_detected_at` i **przedłużamy istniejące spotkanie**
zamiast odrzucać kolejne detekcje, okno przestaje znaczyć „jak długo trwa
spotkanie", a zaczyna znaczyć **„jaka przerwa je kończy"**. Liczymy je od
ostatniego wykrycia, nie od pierwszego. Przy takiej semantyce trzygodzinna
wspólna trasa to nadal jeden wpis, a sześć godzin byłoby już wartością
absurdalną — przerwa pięciogodzinna to oczywiście dwa osobne spotkania.

Wychodzimy więc na wartość bliską Waszej, choć innym uzasadnieniem. Ostateczna
liczba idzie do konfiguracji i będzie do strojenia po pierwszych testach w
terenie.

---

## 5. Opisana odpowiedź `results`

**To jest nasza zaległość dokumentacyjna, nie brak w kodzie.** Endpoint od
początku zwraca wynik per detekcja, tylko specyfikacja tego nie opisywała —
stąd pusta tablica bez typu, na którą trafiliście. Przepraszamy za zgadywanie,
do którego to Was zmusiło.

Przy okazji przejdziemy na Wasz płaski `status`, bo obecny kształt niesie pole
`confirmed`, które po punkcie 3 traci sens. Teraz taka zmiana nic nie kosztuje;
po wydaniu aplikacji kosztowałaby.

Jedna różnica względem Waszej propozycji: **zwracamy pełną kartę spotkanego
użytkownika, nie samo `meeting_id`.** Powód jest produktowy — powiadomienie ma
zawierać nick, a nie „spotkałeś kogoś". Bez karty w odpowiedzi musielibyście
dobijać drugim zapytaniem.

Lista statusów, z Waszych plus dwa nasze:

| status | znaczenie | karta |
|---|---|---|
| `created` | nowe spotkanie | tak |
| `merged` | dołączone do trwającego spotkania tej pary (to, co u Was `cooldown`) | tak |
| `duplicate` | ten `event_id` już był przetworzony | tak, oryginału |
| `unknown_token` | takiego tokena nie ma w bazie | nie |
| `expired_token` | token istniał, ale nie jest już rozwiązywalny | nie |
| `self` | użytkownik wykrył sam siebie | nie |
| `incognito` | któraś ze stron ma tryb niewidzialny | nie |
| `too_old` | detekcja starsza niż dopuszczalne okno zaległości | nie |
| `invalid_time` | `detected_at` w przyszłości poza tolerancją zegara | nie |

Wasza reguła „wszystko poza chwilowymi błędami sieci oznacza: możesz skasować"
obowiązuje dla całej tej listy.

**Nowa rzecz do obsłużenia po Waszej stronie:** dojdzie limit zapytań na
`POST /meetings`, więc endpoint zacznie potrafić odpowiedzieć **429**. To jest
ten „chwilowy błąd", przy którym detekcji nie wolno kasować — trzeba ponowić z
narastającym odstępem. Odpowiedź ma kod `TOO_MANY_REQUESTS` w naszej standardowej
kopercie błędu. Podobnie 5xx i brak sieci.

Przypominamy też, że limit paczki (dwadzieścia detekcji) jest twardy i
sprawdzany walidacją: przekroczenie zwraca **422 na całą paczkę**, nie wynik per
element. Rozbijajcie lokalnie przed wysyłką.

---

## 6. Ustawianie `incognito`

**Racja, dziura jest.** Pole wraca w odpowiedziach i żaden endpoint go nie
zmienia.

**Wybieramy Wasz wariant alternatywny — osobny `POST /profile/incognito`** — i
warto powiedzieć, dlaczego prostszy wariant nie zadziała: `POST /profile`
wymaga nicka i płci jako pól obowiązkowych, bo pełni też rolę tworzenia profilu.
Przełącznik w interfejsie musiałby więc wysyłać cały formularz i wywracałby się
walidacją u kogoś, kto nie skończył onboardingu. Osobna ścieżka to jedno pole i
żadnych warunków wstępnych.

Powiązanie z BLE zgodnie z tym, co piszecie: `incognito = true` daje
`should_broadcast = false`, a zgłoszenie wykrycia takiej osoby wraca ze statusem
`incognito`. Działa to w obie strony — użytkownik w trybie niewidzialnym nie
tylko nie jest wykrywany, ale też jego własne zgłoszenia nie tworzą spotkań.

**Dorzucimy do tego drugie pole: `should_scan`.** Serwer i tak odrzuci
zgłoszenia od użytkownika w incognito, więc skanowanie w tym trybie tylko zżera
baterię. Zwracamy to jako osobną flagę, żeby polityka została na serwerze i
dała się zmienić bez wydania aplikacji — tak samo jak `should_broadcast`.

---

## 7. Opcjonalne

- **`rssi` — bierzemy od razu**, jako pole opcjonalne przy detekcji. Zgadzamy
  się z uzasadnieniem: dane do strojenia progów zbiera się od pierwszego dnia
  albo wcale.
- **Czas trwania kontaktu — bierzemy od razu**, bo przy modelu z punktu 3
  wychodzi praktycznie za darmo. `first_detected_at` i `last_detected_at` na
  spotkaniu, reszta wyliczalna.
- **Push — zostaje na później.** `push_token` zbieramy właśnie po to, żeby nie
  robić potem migracji na żywej bazie, ale Firebase nie jest jeszcze podpięty.
  Wraca jako osobny etap.

---

## 8. Do ustalenia po Waszej stronie

Kilka rzeczy, których nie da się rozstrzygnąć z serwera, a mają wpływ na to, czy
się dogadamy w terenie:

1. **Format tokena w charakterystyce GATT.** Proponujemy 32 znaki ASCII (zapis
   szesnastkowy), a nie 16 bajtów binarnie — znika wtedy pytanie o kolejność
   bajtów, a przy odczycie po połączeniu rozmiar nie ma znaczenia. Potwierdzicie?
2. **Odczyt charakterystyki bez parowania i bez szyfrowania.** Inaczej iOS
   pokaże systemowy monit o sparowanie przy każdym mijanym motocykliście, co
   zabije funkcję.
3. **`detected_at` zawsze ze strefą czasową** (ISO 8601, UTC albo z offsetem).
   Czas lokalny bez strefy będzie interpretowany błędnie i wpadnie w
   `invalid_time` albo `too_old`.
4. **Zachowanie po ręcznej rotacji.** Skoro stary token ginie natychmiast,
   aplikacja musi zaraz po odpowiedzi zrestartować rozgłaszanie z nowym.
5. **Czy potwierdzacie macierz wykrywania z terenu, czy jest wnioskiem z
   dokumentacji?** Pytamy, bo cała zmiana z punktu 3 stoi na tym jednym
   ustaleniu. Mamy w bazie kolumnę z platformą urządzenia, więc po uruchomieniu
   możemy to zweryfikować na prawdziwym ruchu — chętnie zobaczymy Wasze
   obserwacje obok naszych.

---

## Podsumowanie

| # | Wasza uwaga | Nasza odpowiedź |
|---|---|---|
| 0 | rozdzielenie UUID serwisu i tokena | przyjęte w całości |
| 1 | `service_uuid` w API | robimy, plus UUID charakterystyki, plus losowy v4 zamiast `FEED` |
| 2 | historia tokenów | już działa; poprawiamy ręczną rotację, tolerancję zegara i sprzątanie |
| 3 | spotkanie obu stronom | przyjęte, model danych rozbity na dwie tabele |
| 4 | dedup na parze | przyjęte; okno zmienia znaczenie na „przerwa kończąca spotkanie" |
| 5 | opisane `results` | już działa; przechodzimy na `status`, zwracamy kartę, dochodzi 429 |
| 6 | ustawianie `incognito` | osobny endpoint, plus `should_scan` |
| 7 | rssi i czas kontaktu | bierzemy od razu; push osobnym etapem |

Punkty 2, 3, 4 i 5 wchodzą jedną paczką, bo dotykają tej samej ścieżki — tu
zgadzamy się z Wami co do joty. Punkty 1 i 6 są niezależne i pójdą osobno,
prawdopodobnie wcześniej, żebyście dostali UUID i przełącznik incognito jak
najszybciej.

Dobra notatka. Podniosła rzecz, która wywróciłaby nam produkt po wydaniu.
