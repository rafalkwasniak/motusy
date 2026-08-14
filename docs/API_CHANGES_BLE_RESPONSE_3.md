# Odpowiedź — runda 3

Komplet odpowiedzi na B.1, B.2 i B.3, trzy sprostowania po naszej stronie oraz
domknięcie obu kwestii z §E.

**Zaczynamy prace.** Punkty 1 i 6 z pierwszej notatki wchodzą od razu, paczka
2–3–4–5 zaraz po nich.

---

## A. Odpowiedzi na nowe kwestie

### A.1. Wyścig przy równoczesnym zgłoszeniu — macie rację, to był błąd

Bez owijania: w naszym opisie tego nie było i nie dlatego, że uznaliśmy temat za
załatwiony. Po prostu go przeoczyliśmy. Sprawdzenie „czy para ma już spotkanie"
i wstawienie wiersza to u nas dwa kroki i między nimi jest szczelina, dokładnie
taka, jak opisujecie. Scenariusz z dwiema kolejkami opróżnianymi pod tą samą
kawiarnią jest realny, a nie teoretyczny.

Zgadzamy się też z Waszą analizą, dlaczego nic z gotowych mechanizmów tego nie
łapie: `event_id` są z definicji różne po obu stronach, a unikalność na samej
parze jest nie do postawienia, bo ci sami ludzie spotykają się wielokrotnie.

**Bierzemy Wasz pierwszy wariant** — nazwana blokada na znormalizowanej parze,
trzymana wyłącznie na czas rozstrzygnięcia. Drugie zgłoszenie czeka, wchodzi po
pierwszym, widzi już istniejące spotkanie i dołącza do niego. Blokada dotyczy
jednej pary, więc zgłoszenia niezwiązanych par nigdy na siebie nie czekają.

`SELECT ... FOR UPDATE` odrzucamy z powodu, którego nie wymieniliście, a który
przesądza sprawę: gdy dla pary nie ma **żadnego** wiersza, nie ma czego
zablokować. Działałoby to na blokadach zakresu silnika bazy, czyli zależnie od
tego, jaki plan wybierze optymalizator. Za kruche jak na warunek poprawności.

Wariant z kubełkiem czasowym odpada wraz ze sprostowaniem z §B.1 poniżej.

### A.2. Karencja tokena a okno `too_old` — niezmiennik już zachodzi

Karencja wynosi 72 godziny, okno zaległości 24 godziny. Zależność, o którą
prosicie, jest więc spełniona z trzykrotnym zapasem. Dopisujemy do tego test,
który porówna obie wartości i wywali się przy zmianie łamiącej zależność —
żeby przestała ona wymagać niczyjej pamięci.

**Natomiast Wasz drugi akapit opisuje sytuację, która u nas nie zachodzi, i
warto, żebyście o tym wiedzieli, bo to upraszcza Wam życie.**

Rotacja jest u nas **leniwa**. Token nie wymienia się o wyznaczonej godzinie —
wymienia się dopiero wtedy, gdy telefon poprosi o `GET /ble/identity` i okaże
się, że dotychczasowy jest już wystarczająco stary. Telefon bez zasięgu o nic
nie prosi, więc jego token **pozostaje aktywny**, a nie „wygasł trzy dni temu".

Obawa o telefon rozgłaszający token wypadnięty z karencji dotyczy więc wyłącznie
urządzenia, które zdążyło zrotować, a potem straciło zasięg na ponad trzy doby.
Karencji nie skracamy, ale nie musicie projektować kolejki pod ten przypadek.

### A.3. Dwie liczby

| | wartość |
|---|---|
| okno `too_old` | **24 godziny** |
| limit zapytań na `POST /meetings` | **30 na minutę, na użytkownika** |
| `Retry-After` przy 429 | **tak**, razem z `X-RateLimit-Remaining` |

Limit liczymy per użytkownik, a nie per adres IP, świadomie: zlot motocyklowy
siedzi za jednym NAT-em operatora komórkowego i limit na adres wyłączyłby wtedy
wszystkim naraz.

Dla skali: paczka to maksimum 20 wykryć, więc limit dopuszcza 600 wykryć na
minutę na osobę. Telefon wracający po dobie bez zasięgu z dwustoma zaległymi
detekcjami wyśle około dziesięciu zapytań w kilka sekund. Zapas jest
dwudziestokrotny — przy Waszym dławieniu lokalnym normalny użytkownik nie ma jak
w ten limit uderzyć.

---

## B. Sprostowania po naszej stronie

Wszystkie trzy dotyczą tego samego: okna deduplikacji. W poprzedniej rundzie
podaliśmy Wam uzasadnienie, które było błędne, i teraz je wycofujemy.

### B.1. Okno wraca do sześciu godzin, liczone od momentu spotkania

Pisaliśmy, że parametr zmieni znaczenie na „przerwę kończącą spotkanie" i
wyjdzie na wartość zbliżoną do Waszych trzydziestu minut. **Wycofujemy to.**
Zostaje blokada sześciogodzinna liczona od momentu spotkania danej pary — czyli
to, co działa dziś.

Rozstrzygnął zwykły dzień pracy:

| | przerwa 30 min | blokada 6 h |
|---|---|---|
| 8:00, mijam Marka jadąc do pracy | spotkanie | spotkanie |
| 10:00, mijam Marka jadąc do urzędu | **drugie spotkanie** | zablokowane, wciąż to samo |
| 16:00, mijam Marka wracając | trzecie spotkanie | drugie spotkanie |

Wariant z przerwą produkuje trzy wpisy tam, gdzie z punktu widzenia użytkownika
zdarzenia są dwa. Broniliśmy go argumentem o wspólnej jeździe, ale to był
fałszywy problem: przy blokadzie sześciogodzinnej wspólna trasa i tak daje jeden
wpis, bo wszystkie kolejne wykrycia mieszczą się w oknie. Jedna reguła obsługuje
oba przypadki, druga tylko jeden.

Okno działa w obie strony wokół zgłaszanego czasu wykrycia, bo zaległości
przychodzą w losowej kolejności.

Jedyny przypadek, w którym ta reguła daje wynik dyskusyjny: wspólny wyjazd
dłuższy niż sześć godzin rozbije się na dwa wpisy. Uznajemy to za akceptowalne.

### B.2. Status wraca do Waszej nazwy `cooldown`

Zaproponowaliśmy `merged`, bo zakładaliśmy doklejanie detekcji do trwającego
spotkania. Skoro nie doklejamy, Wasza pierwotna nazwa jest trafniejsza i do niej
wracamy. Znaczenie: para ma już spotkanie w oknie, zwracamy jego kartę,
detekcję można skasować z kolejki.

### B.3. Czas trwania kontaktu wypada

Rezygnujemy, a przekonał nas Wasz własny punkt C. Skoro zwijacie powtarzające
się wykrycia lokalnie, zanim cokolwiek wyślecie, to serwer **z założenia nigdy
nie zobaczy pełnego strumienia**. Liczenie z tego czasu trwania byłoby
budowaniem wskaźnika na danych, które druga strona świadomie przerzedza — i
podawaniem użytkownikowi liczby, za którą nic nie stoi.

`rssi` zostaje, bo to pojedynczy pomiar przy pojedynczym wykryciu i nie zależy
od tego, ile detekcji przepuścicie dalej.

Model danych zostaje dwutabelowy — nadal zarabia na siebie idempotencją per
zgłaszający, własnym GPS każdej ze stron i kolumną platformy z §A.5.

---

## C. Domknięcie kwestii z §E

### C.1. Incognito — decyzja była podjęta, i ma wyjątek, o którym nie wiecie

Dobrze, że zapytaliście, bo odpowiedź jest bogatsza, niż zakładacie.

Reguła obowiązująca w obie strony nie jest naszym wnioskiem z implementacji —
jest ustaleniem produktowym sprzed prac nad API i została potwierdzona teraz
wprost. Brzmi ona: **incognito ma działać tak, jakbyś nie miał aplikacji.** Nie
widzisz i nie jesteś widziany. Wasze rozumienie jest zgodne z tym, co
zbudowaliśmy.

**Ale jest wyjątek, którego jeszcze nie znacie, i on będzie miał wpływ na
interfejs:** awarie przebijają incognito. Użytkownik w trybie niewidzialnym
nadal dostaje informacje o awariach w pobliżu, a jego własna aktywna awaria jest
przekazywana ludziom obok i znajomym. To jedyne odstępstwo od zasady „jakby
aplikacji nie było" i jest w niej celowe: tryb prywatności nie może kosztować
kogoś pomocy na drodze.

Awarie to osobny etap, więc dziś nie ma tego w API. Sygnalizujemy z
wyprzedzeniem, bo jeśli będziecie opisywać ten tryb w interfejsie, to zdanie
powinno się tam znaleźć od razu — inaczej trzeba je będzie dopisywać po
wydaniu.

Praktyczna konsekwencja dla Was, i powód, dla którego prosimy o trzymanie się
flag z API zamiast wyliczania ich z `incognito`: gdy dojdą awarie,
`should_broadcast` przestanie być prostym zaprzeczeniem `incognito`.
Użytkownik z aktywną awarią będzie musiał rozgłaszać mimo trybu niewidzialnego.
Jeżeli aplikacja czyta gotową flagę, zmienimy tę regułę na serwerze i nie
wymusi to wydania nowej wersji.

Nazwę trybu w interfejsie rozważamy — „incognito" faktycznie sugeruje „ja widzę,
mnie nie widać", czyli coś innego, niż ten tryb robi. To decyzja po stronie
produktu, ale zgadzamy się z Waszą diagnozą, że sama etykieta wprowadza w błąd.

### C.2. Ręczna rotacja jest globalna dla konta — potwierdzamy

Tożsamość jest per osoba, nie per urządzenie, więc token jest jeden i rotacja
unieważnia go wszędzie. Wasze założenie jest słuszne.

Dorzucamy konsekwencję, której nie wymieniliście, a która wynika z ustalenia, że
ręczna rotacja kasuje stary token **bez karencji**: po rotacji z telefonu tablet
tego samego konta dalej rozgłasza token, który już nie istnieje, i jest
nierozpoznawalny do czasu odświeżenia. Prosimy o wywołanie `GET /ble/identity`
przy każdym wejściu aplikacji na pierwszy plan, nie tylko przy starcie.

---

## D. Pozostałe punkty

**A.1, MTU.** Przyjęte do wiadomości, zostajemy przy 32 znakach ASCII. Zgadzamy
się, że przy oknie kontaktu liczonym w dziesiątkach sekund jedna dodatkowa
wymiana pakietów jest bez znaczenia, a pytanie o kolejność bajtów kosztowałoby
więcej niż oszczędza. Zapisujemy Waszą uwagę jako pierwszą rzecz do ruszenia,
gdyby kiedyś przyszło optymalizować pod krótsze kontakty.

**A.5, macierz wykrywania.** Dziękujemy za uczciwą odpowiedź — łatwiej było
napisać, że sprawdziliście. Kolumnę z platformą zgłaszającego dorzucamy zgodnie
z Waszą sugestią, więc asymetria wyjdzie z prawdziwego ruchu jednym zapytaniem i
zestawimy to z wynikami Waszego spike'a.

Zgadzamy się też z argumentem, że zmiana z §3 broni się niezależnie od wyniku:
reguła obustronnego potwierdzenia sypie się przy każdej asymetrii wykrycia, nie
tylko przy tej wynikającej z zachowania iOS w tle. Nawet gdyby pomiary wyszły
łagodniej, nie wracamy do niej.

**Wasz punkt C.** Wszystkie sześć zobowiązań przyjęte, żadnego nie
kwestionujemy. Dławienie lokalne okazało się dodatkowo argumentem w §B.3.

---

## E. Co robimy teraz

Kolejność prac:

1. **Od razu:** `service_uuid` i UUID charakterystyki w `GET /ble/identity`
   (losowy v4, nie placeholder z przykładu), `POST /profile/incognito`, flaga
   `should_scan`. Nie zależy od niczego w tym liście — dostaniecie UUID do
   zaszycia jako fallback i przełącznik trybu najwcześniej, jak się da.
2. **Zaraz potem:** paczka 2–3–4–5 wraz z blokadą pary, limitem zapytań,
   kolumną platformy, rozdzieleniem `unknown_token` i `expired_token`,
   natychmiastowym unieważnieniem przy ręcznej rotacji, tolerancją zegara i
   sprzątaniem starych tokenów.

Kontrakt każdego endpointu trafia do dokumentacji w tym samym kroku co kod, więc
nie będziecie już musieli zgadywać kształtu odpowiedzi tak, jak przy `results`.

Jeśli nie macie zastrzeżeń do sprostowań z §B, uznajemy temat za domknięty i
odzywamy się z gotowymi endpointami.
