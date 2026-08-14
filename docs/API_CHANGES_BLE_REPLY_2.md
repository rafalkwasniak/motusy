# Odpowiedź na „Odpowiedź na notatkę" — runda 2

Zgadzamy się z całością. Poniżej odpowiedzi na pięć pytań z §8, trzy nowe
kwestie, które wychodzą dopiero z Waszego opisu implementacji, oraz dwie
korekty po naszej stronie.

**Werdykt: zaczynajcie.** Nic z poniższego nie blokuje paczki 2–3–4–5.

---

## A. Odpowiedzi na §8

### A.1. Format tokena w charakterystyce — przyjmujemy 32 znaki ASCII

Zgoda, z argumentacją o kolejności bajtów. Jedna uwaga na przyszłość, żeby
została zapisana, a nie żeby coś zmieniać teraz.

Domyślne ATT MTU to 23 bajty, co daje **22 bajty wartości w pojedynczej
odpowiedzi na odczyt** (opcode zjada jeden). 32 znaki ASCII się w tym nie
mieszczą, więc odczyt wymaga albo negocjacji większego MTU, albo sekwencji
Read Blob. Oba stosy — CoreBluetooth i Android — robią to przezroczyście w
wysokopoziomowym API, więc **to nie jest problem poprawnościowy, tylko jedna
dodatkowa wymiana pakietów**.

16 bajtów binarnie zmieściłoby się w jednym odczycie. Przy oknie kontaktu
liczonym w dziesiątkach sekund ta różnica jest bez znaczenia i nie warto za nią
płacić pytaniem o endianness. Odnotowujemy tylko na wypadek, gdyby kiedyś
przyszło optymalizować pod krótsze kontakty — wtedy to jest pierwsza rzecz do
ruszenia.

### A.2. Odczyt bez parowania i bez szyfrowania — potwierdzamy

Charakterystyka musi być `read`, bez `encrypted` i bez `authenticated`. Macie
rację co do skutku: iOS pokazałby systemowy monit o sparowanie przy każdym
mijanym motocykliście i funkcja umarłaby w tydzień.

Świadomie akceptujemy, że token może odczytać każdy, kto się połączy. To jest
założenie modelu, nie luka — token jest rotującym pseudonimem, a nie sekretem.
Rozpoznawalność ograniczają rotacja i wymóg fizycznej bliskości z §0.

### A.3. `detected_at` ze strefą — potwierdzamy

Wysyłamy ISO 8601 w UTC z sufiksem `Z`. Znacznik stawiamy w **momencie
wykrycia**, nie w momencie wysyłki — przy zaległej kolejce to dwie zupełnie
różne wartości.

### A.4. Restart rozgłaszania po ręcznej rotacji — potwierdzamy

Po odpowiedzi z `POST /ble/identity/rotate` aplikacja natychmiast podmienia
wartość charakterystyki GATT i restartuje rozgłaszanie. Bez czekania na kolejny
cykl.

Świadomie przyjmujemy skutek uboczny: telefony, które zdążyły odczytać stary
token i mają go w kolejce, dostaną `expired_token`. O to właśnie chodzi w
przycisku „resetuj tożsamość".

### A.5. Macierz wykrywania — **wniosek z dokumentacji, nie z terenu**

Odpowiadamy wprost, bo pytanie jest trafione w sedno i zasługuje na uczciwą
odpowiedź: **nie zweryfikowaliśmy tego na urządzeniach.**

Podstawa to udokumentowane zachowanie CoreBluetooth w tle — brak danych
producenta i danych serwisu, UUID przenoszony do obszaru przepełnienia
czytelnego wyłącznie dla urządzeń Apple skanujących pod konkretny UUID, oraz
zakaz skanowania bez podanej listy UUID. Potwierdzają to doświadczenia
aplikacji śledzenia kontaktów sprzed wprowadzenia rozwiązań systemowych, które
uderzyły dokładnie w tę asymetrię.

To mocna podstawa, ale to nie są **nasze** pomiary. Planujemy spike na dwóch
urządzeniach, w trzech scenariuszach: postój obok siebie, wspólna jazda,
minięcie. Mierzymy czas do wykrycia w każdym kierunku osobno.

Wasza propozycja korelacji z kolumną platformy na prawdziwym ruchu jest bardzo
dobra i chętnie zestawimy jedno z drugim. Sugerujemy dorzucić do
`meeting_reports` informację o platformie zgłaszającego — jeśli jej tam jeszcze
nie ma — bo wtedy asymetria wyjdzie z danych sama, bez osobnej analizy.

Do czasu spike'a traktujcie zmianę z §3 jako uzasadnioną, ale niepotwierdzoną
empirycznie. Podkreślamy jednak: **nawet gdyby macierz okazała się łagodniejsza,
niż zakładamy, zmiana z §3 i tak jest słuszna.** Reguła obustronnego
potwierdzenia gubi spotkania przy każdej asymetrii wykrycia, także tej
wynikającej ze zwykłego pecha w oknie skanowania. Nie stoi ona wyłącznie na
przypadku iOS–Android.

---

## B. Nowe kwestie — wychodzą dopiero z Waszego opisu

### B.1. Wyścig przy równoczesnych zgłoszeniach obu stron

**To jest realne ryzyko i wygląda na nieadresowane.**

Po zmianie z §3 **oba telefony zgłaszają to samo spotkanie niezależnie**, często
w zbliżonym czasie — obie kolejki opróżniają się po odzyskaniu zasięgu, na
przykład po zjechaniu z trasy pod tę samą kawiarnię.

Scenariusz: zgłoszenie Anny i zgłoszenie Marka trafiają na serwer równolegle.
Oba sprawdzają, czy dla pary `{Anna, Marek}` istnieje spotkanie w oknie. Oba
widzą, że nie. Oba tworzą. **Powstają dwa spotkania dla jednego zdarzenia** —
czyli dokładnie to, czemu ma zapobiegać §4.

`unique(reporter_id, event_id)` tego nie złapie, bo `reporter_id` i `event_id`
są różne dla obu stron. To jest właśnie ten przypadek, w którym idempotencja po
`event_id` z założenia nie pomaga.

Prosta unikalność na `(user_a_id, user_b_id)` też nie zadziała, bo ta sama para
może mieć wiele spotkań rozłożonych w czasie.

Do rozważenia, wybór należy do Was:

- blokada doradcza na znormalizowanej parze na czas transakcji rozstrzygającej
  — najprostsze i wystarczające,
- albo `SELECT ... FOR UPDATE` na istniejących spotkaniach pary,
- albo unikalny indeks na `(user_a_id, user_b_id, kubełek_czasowy)` z obsługą
  konfliktu przy wstawianiu — wymaga dyskretyzacji czasu, więc mniej elegancki.

Sygnalizujemy, nie narzucamy. Znacie swój stos lepiej.

### B.2. Karencja 72 h a okno `too_old` muszą być spójne

Przyjmujemy Waszą korektę z §2 — model `active` plus `expires_at` jest
odporniejszy na rozjazd zegara niż nasze `valid_from`/`valid_until`, bo w ogóle
nie porównuje `detected_at` z oknem ważności tokena. Nasza propozycja była pod
tym względem gorsza i to uznajemy.

Ale z Waszego opisu wynika nowa zależność, która wcześniej nie istniała. Są
teraz **dwa niezależne limity czasu na to samo zaległe zgłoszenie**:

- token rozwiązuje się przez **72 godziny od rotacji**,
- detekcja jest przyjmowana, dopóki nie przekroczy okna `too_old`.

Jeśli `too_old` jest **dłuższe** niż 72 godziny, powstaje martwa strefa:
aplikacja uznaje detekcję za wciąż wysyłalną, trzyma ją w kolejce, wysyła — i
dostaje `expired_token`, bo token zdążył wypaść z karencji. Zgłoszenie ginie w
sposób, którego aplikacja nie umie przewidzieć ani sensownie zaraportować.

**Prosimy o ustawienie karencji tokena nie krótszej niż okno `too_old`.** Wtedy
`too_old` jest jedyną granicą, aplikacja zna ją z góry i kolejka lokalna ma
jednoznaczną regułę czyszczenia.

Warto też odnotować, że karencja pełni drugą, mniej oczywistą rolę: **osłania
telefon bez zasięgu, który nie zdążył pobrać nowego tokena po automatycznej
rotacji.** Taki telefon dalej rozgłasza stary token — i dobrze, że jest on nadal
rozwiązywalny. To argument raczej za wydłużeniem karencji niż skróceniem.

### B.3. Dwie liczby, których potrzebujemy do zaprojektowania kolejki

Prosimy o wartości, gdy je ustalicie — nie blokują startu prac, ale bez nich nie
domkniemy logiki wysyłki:

1. **Okno `too_old`** — po jakim czasie detekcja jest bezpowrotnie za stara.
   Wprost przekłada się na to, jak długo trzymamy niewysłane detekcje lokalnie.
2. **Parametry limitu zapytań** — ile zapytań w jakim oknie i czy odpowiedź 429
   niesie nagłówek `Retry-After`. Jeśli tak, użyjemy go zamiast zgadywać odstęp.

---

## C. Co bierzemy na siebie

- **Lokalne dławienie przed wysyłką.** Podczas wspólnej jazdy ten sam token
  wykryjemy kilkadziesiąt razy. Nie wyślemy tego jeden do jednego — zwiniemy do
  jednej detekcji na token w kubełku czasowym, żeby nie zapychać Wam endpointu i
  własnego limitu. Wasze `merged` i tak by to pochłonęło, ale nie ma powodu
  wysyłać śmieci.
- **Rozbijanie paczek po 20 lokalnie**, przed wysyłką. Odnotowane, że
  przekroczenie to 422 na całą paczkę.
- **Rozróżnienie błędów trwałych od chwilowych.** Wszystkie statusy z Waszej
  tabeli kasują detekcję z kolejki. 429, 5xx i brak sieci — ponawiamy z
  narastającym odstępem, nie kasujemy.
- **Historia wyłącznie z `GET /meetings`.** Odnotowane, że pojawią się tam
  spotkania, których ten telefon nigdy nie zgłosił. Nie budujemy lokalnego
  cache'u z własnych wysyłek.
- **Buforowanie konfiguracji BLE.** `service_uuid`, UUID charakterystyki,
  `should_broadcast` i `should_scan` zapisujemy przy pierwszym udanym pobraniu,
  z wartościami zaszytymi w kodzie jako awaryjny fallback. Skanowanie nigdy nie
  czeka na sieć.
- **Ekrany blokowania i zgłaszania użytkownika.** Przyjęte do planu jako skutek
  §3. Zaprojektujemy je razem z ekranem szczegółów spotkania, żeby nie doklejać
  ich później.

---

## D. Korekty po naszej stronie

**UUID z przykładu był nieprzemyślany.** Macie całkowitą rację —
`0000FEED-0000-1000-8000-00805F9B34FB` to rozwinięcie 16-bitowego UUID-a z
przestrzeni Bluetooth SIG i nie jest nasze. Traktujcie to jako placeholder w
przykładzie JSON, nie jako propozycję wartości. Losowy v4 jest jedynym
poprawnym wyborem.

**Model historii tokenów: Wasz jest lepszy.** Uzasadnienie w B.2. Wycofujemy
`valid_from`/`valid_until`.

---

## E. Dwie drobne rzeczy do rozważenia

**`incognito` wycisza użytkownika w obie strony.** Z §6 wynika, że osoba w trybie
niewidzialnym nie tylko nie jest wykrywana, ale też jej własne zgłoszenia nie
tworzą spotkań, a `should_scan = false` wyłącza jej skanowanie. To spójne i
bronimy tego technicznie — ale użytkownik może tego nie oczekiwać. Ktoś może
sądzić, że „niewidzialny" znaczy „ja widzę, mnie nie widać". Opiszemy to w
interfejsie jednoznacznie. Sygnalizujemy, bo to decyzja produktowa, nie
techniczna, i warto żeby była świadoma po obu stronach.

**Wiele urządzeń na jedno konto.** `POST /devices` dopuszcza wiele urządzeń, a
`GET /ble/identity` zwraca token per użytkownik — czyli telefon i tablet
rozgłaszają ten sam token. Status `self` sugeruje, że to przewidzieliście.
Upewniamy się tylko, że ręczna rotacja z jednego urządzenia jest zamierzenie
globalna dla konta. Naszym zdaniem powinna być, ale wolimy zapytać niż założyć.

---

## Podsumowanie

| Pytanie z §8 | Nasza odpowiedź |
|---|---|
| 1. format tokena w GATT | 32 znaki ASCII — przyjęte, z uwagą o MTU |
| 2. bez parowania i szyfrowania | potwierdzone |
| 3. `detected_at` ze strefą | potwierdzone, UTC z `Z`, znacznik z chwili wykrycia |
| 4. restart rozgłaszania po rotacji | potwierdzone |
| 5. macierz wykrywania | **wniosek z dokumentacji, nie z terenu** — spike zaplanowany |

| Nowa kwestia | Do kogo |
|---|---|
| B.1. wyścig przy równoczesnym zgłoszeniu obu stron | do Was, sposób do wyboru |
| B.2. karencja tokena ≥ okno `too_old` | do Was |
| B.3. wartość `too_old` i parametry limitu zapytań | do Was, gdy ustalicie |

Świetna odpowiedź. Punkt o tym, że reguła obustronnego potwierdzenia wyzerowałaby
wszystkie pary iPhone–Android, jest ostrzejszy niż nasza własna diagnoza —
patrzyliśmy na to jako na ubytek, a Wy policzyliście, że to zero. Dobrze, że
wyszło przed wydaniem.
