# API MVP – Specyfikacja

## 1. Założenia

Backend aplikacji będzie napisany w Laravelu i udostępni REST API dla aplikacji mobilnej.

Podstawowe założenia:

- REST API
- JSON jako format komunikacji
- API w wersji `/api/v1`
- baza danych MySQL
- daty i czasy przechowywane w UTC
- główne identyfikatory jako UUID

Aplikacja mobilna nie powinna utrzymywać stałego połączenia z API.

API będzie wykorzystywane głównie do:

- rejestracji i logowania,
- przechowywania danych użytkowników,
- przechowywania profili i motocykli,
- obsługi relacji społecznych,
- zapisywania spotkań,
- obsługi statusów,
- obsługi awarii,
- przechowywania historii,
- synchronizacji danych pomiędzy użytkownikami.

---

## 2. Użytkownicy

### Tabela `users`

Podstawowe dane konta użytkownika.

Pola:

- `id` – UUID, primary key
- `email` – string, wymagany, unikalny
- `password` – string, wymagany, przechowywany jako hash
- `created_at`
- `updated_at`
- `deleted_at` – nullable

Konto jest aktywne od razu po rejestracji.

W MVP nie jest wymagana aktywacja konta przez e-mail.

---

## 3. Profile użytkowników

### Tabela `user_profiles`

Dane profilu są przechowywane oddzielnie od danych logowania.

### Dane wymagane

- `id` – UUID
- `user_id` – UUID, unique
- `nickname` – string
- `gender` – string / enum
- `created_at`
- `updated_at`

### Dane opcjonalne

- `first_name` – string, nullable
- `last_name` – string, nullable
- `avatar` – string, nullable
- `bio` – text, nullable
- `phone` – string, nullable
- `phone_visible` – boolean
- `email_visible` – boolean
- `motorcycle_description` – text, nullable
- `motorcycle_photo` – string, nullable

API nie powinno zwracać danych, których użytkownik nie zezwolił udostępniać.

Dotyczy to przede wszystkim:

- imienia,
- nazwiska,
- numeru telefonu,
- adresu e-mail.

Wyjątkiem mogą być dane udostępniane w związku z aktywną awarią.

---

## 4. Motocykl

W MVP użytkownik posiada jeden główny motocykl.

### Tabela `motorcycles`

Pola:

- `id` – UUID
- `user_id` – UUID, unique
- `brand` – string
- `model` – string
- `production_year` – integer
- `color` – string
- `description` – text, nullable
- `photo` – string, nullable
- `created_at`
- `updated_at`

Relacja:

`User 1 : 1 Motorcycle`

Zdjęcie motocykla może być wykorzystywane jako główne zdjęcie lub tło profilu.

---

## 5. Tożsamość BLE

Każdy użytkownik otrzymuje unikalny identyfikator wykorzystywany przez mechanizm BLE.

### Tabela `ble_identities`

Pola:

- `id` – UUID
- `user_id` – UUID
- `token` – losowy, unikalny identyfikator
- `active` – boolean
- `created_at`
- `updated_at`

Token BLE nie powinien zawierać bezpośrednio:

- adresu e-mail,
- numeru telefonu,
- prostego ID użytkownika,
- innych łatwych do odgadnięcia danych.

Szczegółowy mechanizm bezpieczeństwa BLE zostanie ustalony podczas projektowania części mobilnej.

---

## 6. Urządzenia

API powinno przechowywać informację o urządzeniach użytkownika.

### Tabela `devices`

Pola:

- `id` – UUID
- `user_id` – UUID
- `device_id` – string
- `platform` – `ios` / `android`
- `app_version` – string, nullable
- `active` – boolean
- `last_seen_at` – nullable
- `created_at`
- `updated_at`

W MVP zewnętrzne push notifications nie są wymagane, ale struktura powinna umożliwiać ich dodanie w przyszłości.

---

## 7. Lokalizacja GPS

Współrzędne GPS przechowujemy jako:

- `latitude`
- `longitude`

Rekomendowany typ w bazie:

`DECIMAL`

Zakres:

- latitude: `-90` do `90`
- longitude: `-180` do `180`

Przykładowe dane:

- `latitude`: `50.4201`
- `longitude`: `18.9273`

API nie powinno celowo zaokrąglać lokalizacji awarii.

---

## 8. Data i czas

Daty i czasy przesyłane przez API powinny korzystać z formatu ISO 8601.

Przykład:

`2026-08-13T18:42:15Z`

W bazie dane czasowe powinny być przechowywane w UTC.

Aplikacja mobilna odpowiada za wyświetlanie czasu w lokalnej strefie użytkownika.

---

## 9. Główne encje MVP

Podstawowy model danych MVP obejmuje:

- `users`
- `user_profiles`
- `motorcycles`
- `ble_identities`
- `devices`
- `user_follows`
- `friend_requests`
- `friendships`
- `meetings`
- `breakdowns`
- `user_statuses`

Dodatkowe tabele mogą zostać dodane podczas implementacji, jeżeli okażą się potrzebne.

---

## 10. Zasada projektowa

Nie należy tworzyć w MVP struktur przeznaczonych dla funkcji, które nie są jeszcze potrzebne.

Poza MVP pozostają między innymi:

- chat 1:1,
- chat grupowy,
- grupy,
- rejestrowanie tras,
- GPX,
- nawigacja,
- odznaki,
- funkcje premium,
- sponsorzy,
- automatyczne wykrywanie wypadku.

Model danych powinien jednak umożliwiać późniejszą rozbudowę bez konieczności przebudowy podstawowych encji.

## 11. Obserwowanie użytkowników

Obserwowanie jest relacją jednostronną.

Przykład:

`A obserwuje B`

Nie oznacza to automatycznie:

`B obserwuje A`

Obserwowanie nie wymaga zgody drugiej osoby.

Obserwowanie jest ciche i nie generuje powiadomienia.

Użytkownik może obserwować dowolną liczbę osób.

### Tabela `user_follows`

Pola:

- `id` – UUID
- `follower_id` – UUID
- `followed_id` – UUID
- `created_at`

Reguły:

- użytkownik nie może obserwować samego siebie,
- ta sama relacja nie może wystąpić więcej niż raz,
- usunięcie obserwowania usuwa relację,
- obserwowanie nie wymaga akceptacji drugiej osoby.

---

## 12. Liczniki relacji

API powinno udostępniać liczbę:

- znajomych,
- osób obserwowanych,
- osób obserwujących.

Przykład:

`friends_count = 38`

`following_count = 57`

`followers_count = 143`

Użytkownik może wiedzieć, ile osób go obserwuje, ale nie musi znać ich tożsamości.

---

## 13. Zaproszenia do znajomych

Dodanie użytkownika do znajomych wymaga wysłania zaproszenia.

### Tabela `friend_requests`

Pola:

- `id` – UUID
- `sender_id` – UUID
- `receiver_id` – UUID
- `status` – string / enum
- `created_at`
- `updated_at`
- `responded_at` – nullable

### Statusy

- `pending`
- `accepted`
- `rejected`
- `ignored`

Po wysłaniu zaproszenia odbiorca otrzymuje powiadomienie.

---

## 14. Obsługa zaproszenia

Odbiorca zaproszenia może:

- zaakceptować,
- odrzucić,
- zignorować.

Akceptacja powoduje utworzenie relacji znajomych.

Odrzucenie lub zignorowanie nie tworzy relacji znajomych.

Zaproszenie powinno zachować informację o swoim aktualnym statusie oraz dacie odpowiedzi.

---

## 15. Znajomi

Znajomość jest relacją dwustronną.

### Tabela `friendships`

Rekomendowana struktura:

- `id` – UUID
- `user_a_id` – UUID
- `user_b_id` – UUID
- `created_at`

Kolejność użytkowników w relacji nie ma znaczenia.

Przykład:

`user_a_id = A`

`user_b_id = B`

oznacza:

`A jest znajomym B`

oraz:

`B jest znajomym A`

Jedna para użytkowników może posiadać tylko jedną aktywną relację znajomości.

---

## 16. Znajomość a obserwowanie

Znajomość ma wyższy priorytet niż obserwowanie.

Jeżeli:

`A obserwuje B`

a następnie:

`A i B zostają znajomymi`

relacja obserwowania powinna zostać usunięta.

Dotyczy to obu kierunków.

Po zostaniu znajomymi nie powinny istnieć:

- `A -> B follow`
- `B -> A follow`

Powinna istnieć wyłącznie relacja:

`A <-> B friendship`

---

## 17. Usunięcie znajomego

Użytkownik może usunąć znajomego.

Jeżeli A usuwa B:

1. relacja znajomości A-B zostaje usunięta,
2. B automatycznie zaczyna obserwować A,
3. A może zdecydować, czy nadal chce obserwować B.

B nie musi otrzymać informacji, że został usunięty ze znajomych.

Po usunięciu:

`B -> A follow`

jest aktywne.

A może dodatkowo zdecydować:

`A -> B follow`

---

## 18. Usunięcie znajomego a obserwowanie

Podczas usuwania znajomego aplikacja może zapytać:

> Usuwasz Marka ze znajomych. Czy chcesz nadal go obserwować?

Opcje:

- Tak
- Nie

Jeżeli użytkownik wybierze „Tak”:

`A -> B follow`

pozostaje lub zostaje utworzone.

Jeżeli użytkownik wybierze „Nie”:

A nie obserwuje B.

Niezależnie od wyboru:

`B -> A follow`

pozostaje aktywne.

---

## 19. Profil użytkownika

API powinno umożliwiać pobranie profilu innego użytkownika.

Przykładowy endpoint:

`GET /api/v1/users/{id}`

Profil może zawierać:

- nick,
- płeć,
- markę motocykla,
- model motocykla,
- rocznik,
- kolor,
- zdjęcie motocykla,
- avatar,
- bio,
- imię,
- nazwisko,
- numer telefonu,
- adres e-mail,
- opis motocykla,
- aktualny status.

API powinno zwracać wyłącznie dane, które oglądający ma prawo zobaczyć.

Dane ukryte przez właściciela profilu nie powinny być wysyłane do aplikacji.

---

## 20. Dane profilu a aktywna awaria

Aktywna awaria może mieć inne zasady udostępniania danych niż normalny profil.

Jeżeli użytkownik zgłasza awarię, aplikacja informuje go, że w związku z awarią mogą zostać udostępnione dodatkowe dane kontaktowe.

W szczególności:

- imię,
- numer telefonu.

Dane te są dostępne w kontekście aktywnej awarii, nawet jeżeli normalnie są ukryte w profilu.

---

## 21. Relacja z użytkownikiem

Odpowiedź profilu powinna zawierać informacje o relacji pomiędzy oglądającym a właścicielem profilu.

Przykładowe wartości:

- `is_friend`
- `is_following`
- `is_followed_by`
- `friend_request_pending`

Przykład:

`is_friend = false`

`is_following = true`

`is_followed_by = false`

`friend_request_pending = false`

Dzięki temu aplikacja może prawidłowo wyświetlić dostępne akcje.

---

## 22. Zaproszenie do znajomych z profilu

Z poziomu profilu użytkownik może wysłać zaproszenie do znajomych.

Endpoint:

`POST /api/v1/friend-requests`

Request powinien zawierać ID użytkownika:

`user_id`

Nie można wysłać zaproszenia:

- samemu sobie,
- osobie, która już jest znajomym,
- jeżeli istnieje już aktywne zaproszenie pomiędzy użytkownikami.

API powinno sprawdzać istniejące relacje oraz wcześniejsze zaproszenia.

---

## 23. Lista znajomych

Endpoint:

`GET /api/v1/friends`

Lista powinna być paginowana.

Każdy element powinien zawierać podstawowe dane potrzebne do wyświetlenia znajomego, np.:

- ID,
- nick,
- avatar,
- motocykl,
- aktualny status,
- informację o aktywnej awarii.

Dane powinny umożliwiać szybkie wyświetlenie informacji o znajomym bez konieczności pobierania osobnego profilu dla każdej osoby.

---

## 24. Lista obserwowanych

Endpoint:

`GET /api/v1/following`

Lista powinna być paginowana.

API zwraca osoby obserwowane przez zalogowanego użytkownika.

Lista może zawierać podstawowe dane potrzebne do wyświetlenia użytkownika.

---

## 25. Lista obserwujących

W MVP użytkownik musi znać liczbę osób, które go obserwują.

Pełna lista obserwujących nie jest wymagana jako podstawowa funkcja MVP.

Jeżeli zostanie udostępniona, powinna być objęta odpowiednimi zasadami prywatności.

---

## 26. Rozpoczęcie obserwowania

Endpoint:

`POST /api/v1/users/{id}/follow`

Po wykonaniu operacji:

- użytkownik zaczyna obserwować wskazaną osobę,
- nie jest wymagane potwierdzenie,
- obserwowana osoba nie otrzymuje powiadomienia.

---

## 27. Zakończenie obserwowania

Endpoint:

`DELETE /api/v1/users/{id}/follow`

Usunięcie obserwowania:

- nie generuje powiadomienia,
- nie wpływa na znajomość,
- nie usuwa historii spotkań,
- nie usuwa historii zaproszeń.

---

## 28. Lista zaproszeń

API powinno umożliwiać pobranie zaproszeń otrzymanych przez użytkownika.

Endpoint:

`GET /api/v1/friend-requests/received`

Powinno być również możliwe pobranie wysłanych zaproszeń:

`GET /api/v1/friend-requests/sent`

Listy powinny być paginowane.

---

## 29. Obsługa zaproszeń – endpointy

Akceptacja:

`POST /api/v1/friend-requests/{id}/accept`

Odrzucenie:

`POST /api/v1/friend-requests/{id}/reject`

Ignorowanie:

`POST /api/v1/friend-requests/{id}/ignore`

Każda operacja musi sprawdzać, czy zalogowany użytkownik jest odbiorcą danego zaproszenia.

---

## 30. Usunięcie znajomego – endpoint

Endpoint:

`DELETE /api/v1/friends/{id}`

Żądanie może zawierać informację, czy użytkownik chce nadal obserwować usuwanego znajomego.

Przykład parametru:

`continue_following = true`

API powinno w ramach jednej operacji:

1. usunąć znajomość,
2. utworzyć relację B -> A,
3. zgodnie z decyzją użytkownika pozostawić lub utworzyć relację A -> B.

---

## 31. Spotkanie a relacje społeczne

Spotkanie jest całkowicie niezależne od relacji społecznych.

Spotkanie może nastąpić pomiędzy:

- znajomymi,
- osobami wzajemnie się obserwującymi,
- osobami, które się nie znają,
- osobami, z których tylko jedna obserwuje drugą.

Relacja społeczna nie wpływa na samo zapisanie spotkania.

Spotkanie znajomego z innym znajomym również generuje normalne powiadomienie i zapis w historii.

---

## 32. Powiadomienia związane z relacjami

W MVP powiadomienia mogą być generowane dla:

- nowego zaproszenia do znajomych,
- zaakceptowania zaproszenia, jeżeli zostanie to przewidziane,
- spotkania z innym użytkownikiem,
- statusów znajomych,
- awarii.

Obserwowanie użytkownika nie generuje powiadomienia.

Spotkanie zawsze jest niezależnym zdarzeniem i generuje własną informację, również wtedy, gdy spotkane osoby są już znajomymi.

## 33. Spotkania motocyklistów

Spotkanie powstaje w momencie, gdy aplikacja wykryje innego użytkownika przez BLE.

Spotkanie jest niezależne od relacji społecznej pomiędzy użytkownikami.

Przykład:

`A wykrywa B`

oraz:

`B wykrywa A`

Obie aplikacje powinny przekazać do API informację o wykryciu.

API powinno traktować te informacje jako dwa niezależne zdarzenia, ponieważ każdy telefon może posiadać inną lokalizację GPS oraz inny czas wykrycia.

---

## 34. Tabela `meetings`

Każdy zapis spotkania powinien reprezentować spotkanie z perspektywy konkretnego użytkownika.

Pola:

- `id` – UUID
- `user_id` – UUID
- `met_user_id` – UUID
- `latitude` – decimal
- `longitude` – decimal
- `detected_at` – datetime
- `created_at`

Znaczenie pól:

- `user_id` – użytkownik, którego telefon wykrył drugą osobę,
- `met_user_id` – wykryty użytkownik,
- `latitude` – lokalizacja użytkownika wykrywającego,
- `longitude` – lokalizacja użytkownika wykrywającego,
- `detected_at` – czas wykrycia.

---

## 35. Przykład spotkania A i B

Jeżeli A wykryje B:

`user_id = A`

`met_user_id = B`

`latitude = lokalizacja A`

`longitude = lokalizacja A`

`detected_at = czas wykrycia przez A`

Jeżeli B wykryje A:

`user_id = B`

`met_user_id = A`

`latitude = lokalizacja B`

`longitude = lokalizacja B`

`detected_at = czas wykrycia przez B`

Dzięki temu historia każdego użytkownika może zawierać jego własną lokalizację i czas spotkania.

---

## 36. Niezależność spotkań

Spotkanie jest rejestrowane niezależnie od tego, czy użytkownicy:

- są znajomymi,
- obserwują się,
- byli wcześniej znajomymi,
- nigdy wcześniej się nie spotkali.

Jeżeli znajomy spotka znajomego, również powstaje normalny wpis spotkania i normalne powiadomienie.

Relacja społeczna nie zmienia zasad rejestrowania spotkania.

---

## 37. Czas karencji spotkania

BLE może wielokrotnie wykrywać tę samą osobę w krótkim czasie.

Nie może to powodować tworzenia wielu wpisów w historii.

Dla każdej pary użytkowników obowiązuje czas karencji.

Przykład:

A spotyka B o 12:00.

Jeżeli karencja wynosi 6 godzin, kolejne wykrycia tej samej osoby w tym czasie nie tworzą nowych spotkań.

Po upływie karencji kolejne spotkanie może zostać zapisane.

Dokładny czas karencji:

`TODO – DO USTALENIA`

Karencja powinna być kontrolowana przez aplikację mobilną, ale API również powinno zabezpieczać bazę przed tworzeniem niepotrzebnych duplikatów.

---

## 38. Ponowne spotkanie

Po zakończeniu czasu karencji ponowne wykrycie tej samej osoby może zostać zapisane jako nowe spotkanie.

Przykład:

`08:00 – spotkanie A z B`

`08:05 – brak nowego spotkania`

`09:00 – brak nowego spotkania`

`14:01 – może zostać zapisane nowe spotkanie`

Dokładne zachowanie zależy od ustalonego czasu karencji.

---

## 39. API – zapis spotkania

Endpoint:

`POST /api/v1/meetings`

Przykładowe dane:

```json
{
    "met_user_id": "uuid",
    "latitude": 50.4201,
    "longitude": 18.9273,
    "detected_at": "2026-08-13T18:42:15Z"
}
```

API powinno:

1. sprawdzić autoryzację użytkownika,
2. sprawdzić, czy wskazany użytkownik istnieje,
3. sprawdzić zasady incognito,
4. sprawdzić czas karencji,
5. sprawdzić możliwość utworzenia spotkania,
6. zapisać spotkanie,
7. zwrócić informację o wyniku operacji.

---

## 40. Odpowiedź po zapisaniu spotkania

API powinno jasno określić, czy spotkanie zostało utworzone.

Przykładowo:

```json
{
    "success": true,
    "created": true,
    "meeting": {
        "id": "uuid",
        "met_user_id": "uuid",
        "detected_at": "2026-08-13T18:42:15Z"
    }
}
```

Jeżeli spotkanie zostało odrzucone z powodu karencji:

```json
{
    "success": true,
    "created": false,
    "reason": "cooldown"
}
```

Nie jest to błąd API. Oznacza jedynie, że spotkanie nie powinno zostać ponownie zapisane.

---

## 41. Historia spotkań

Endpoint:

`GET /api/v1/meetings`

Lista spotkań powinna być paginowana.

Możliwe parametry:

- `page`
- `per_page`
- `from`
- `to`

API zwraca spotkania zalogowanego użytkownika.

Nie powinno zwracać historii spotkań należącej do innych użytkowników.

---

## 42. Szczegóły spotkania

Endpoint:

`GET /api/v1/meetings/{id}`

Odpowiedź powinna zawierać m.in.:

- ID spotkania,
- osobę spotkaną,
- datę,
- godzinę,
- lokalizację,
- podstawowe dane motocykla,
- aktualną relację z tą osobą.

Jeżeli użytkownik ma prawo zobaczyć dodatkowe dane profilu, mogą zostać również zwrócone.

---

## 43. Profil po spotkaniu

Po kliknięciu powiadomienia o spotkaniu aplikacja powinna przejść do profilu spotkanej osoby.

Profil powinien umożliwiać:

- obejrzenie danych użytkownika,
- obejrzenie motocykla,
- obserwowanie,
- wysłanie zaproszenia do znajomych,
- przejście do istniejącej relacji znajomych.

---

## 44. Powiadomienie o spotkaniu

Po zapisaniu nowego spotkania aplikacja powinna otrzymać informację umożliwiającą pokazanie powiadomienia.

Przykład:

`Spotkałeś Marka`

`Yamaha MT-07`

Powiadomienie może zawierać ograniczoną ilość dodatkowych danych, jeżeli użytkownik zezwolił na ich udostępnianie.

Nie należy umieszczać w powiadomieniu dużej ilości informacji.

Kliknięcie powiadomienia powinno prowadzić do profilu spotkanej osoby.

---

## 45. Incognito a spotkania

Jeżeli użytkownik ma włączony tryb incognito:

- nie powinien być wykrywany jako normalny użytkownik,
- sam nie powinien wykrywać innych użytkowników,
- nie powinny powstawać normalne wpisy spotkań.

Tryb incognito nie powinien usuwać istniejącej historii spotkań.

Włączenie incognito wpływa na nowe zdarzenia.

---

## 46. BLE a API

BLE służy przede wszystkim do lokalnego wykrywania użytkowników.

API nie powinno być wykorzystywane do ciągłego sprawdzania:

`Czy ktoś znajduje się w pobliżu?`

Po wykryciu użytkownika przez BLE aplikacja może przekazać zdarzenie do API.

Schemat:

`BLE wykrywa użytkownika`

→

`aplikacja identyfikuje użytkownika`

→

`aplikacja pobiera lokalizację`

→

`aplikacja wysyła zdarzenie do API`

→

`API sprawdza karencję`

→

`API zapisuje spotkanie`

---

## 47. Brak internetu

Spotkanie może zostać wykryte również wtedy, gdy telefon chwilowo nie ma dostępu do internetu.

Aplikacja powinna mieć możliwość tymczasowego przechowania zdarzenia i wysłania go do API później.

Zdarzenie powinno zawierać rzeczywisty czas wykrycia oraz lokalizację z momentu wykrycia.

Szczegółowa obsługa kolejki offline po stronie aplikacji:

`TODO – DO USTALENIA`

---

## 48. Duplikaty spotkań

API musi być odporne na wielokrotne wysłanie tego samego zdarzenia.

Może się zdarzyć, że aplikacja:

- wyśle żądanie,
- nie otrzyma odpowiedzi,
- ponowi żądanie.

Nie powinno to powodować utworzenia dwóch identycznych spotkań.

Szczegółowy mechanizm zabezpieczenia przed duplikatami:

`TODO – DO USTALENIA`

Możliwe rozwiązanie to wykorzystanie identyfikatora zdarzenia generowanego przez aplikację.

---

## 49. Indeksy dla spotkań

Tabela `meetings` będzie często sprawdzana pod kątem wcześniejszych spotkań tej samej pary użytkowników.

Należy przygotować indeksy obejmujące co najmniej:

- `user_id`
- `met_user_id`
- `detected_at`

W szczególności należy zoptymalizować zapytania sprawdzające:

`Czy użytkownik A spotkał użytkownika B w czasie karencji?`

Finalny zestaw indeksów zostanie określony podczas projektowania migracji.

---

## 50. Spotkania a historia

Spotkanie jest trwałym zdarzeniem historycznym.

Nie jest usuwane tylko dlatego, że później zmieniła się relacja pomiędzy użytkownikami.

Przykład:

A spotkał B.

Następnie:

- zostali znajomymi,
- usunęli się ze znajomych,
- przestali się obserwować.

Historia wcześniejszego spotkania pozostaje zachowana.

---

## 51. Prywatność historii

Użytkownik może przeglądać swoją historię spotkań.

API nie powinno udostępniać użytkownikowi historii spotkań innych osób.

Spotkanie zapisane przez A:

`A -> B`

należy do historii A.

Spotkanie zapisane przez B:

`B -> A`

należy do historii B.

Są to dwa niezależne rekordy.

---

## 52. Spotkanie a awaria

Awaria i spotkanie są niezależnymi zdarzeniami.

Jeżeli A ma aktywną awarię, a B znajduje się w pobliżu:

- B otrzymuje informację o awarii,
- awaria zostaje zapisana w historii A,
- nie tworzymy automatycznie spotkania w historii B tylko dlatego, że B otrzymał informację o awarii.

Jeżeli jednocześnie normalny mechanizm BLE wykryje A jako użytkownika, może zostać zapisane normalne spotkanie zgodnie z zasadami spotkań i karencji.

Awaria nie zastępuje spotkania.

---

## 53. Przyszła możliwość rozbudowy BLE

Mechanizm BLE powinien być zaprojektowany tak, aby w przyszłości można było przekazywać również inne typy lokalnych zdarzeń.

W MVP podstawowe zdarzenia BLE dotyczą:

- wykrycia użytkownika,
- aktywnej awarii.

Inne mechanizmy BLE pozostają poza MVP.

## 54. Statusy użytkownika

W MVP użytkownik może posiadać dwa podstawowe rodzaje statusu:

- `riding` – „Lecę na moto”
- `breakdown` – „Mam awarię”

Statusy mają określony czas obowiązywania.

Użytkownik może:

- ustawić status,
- określić czas jego obowiązywania,
- zmienić czas obowiązywania,
- zakończyć status wcześniej.

Po wygaśnięciu lub ręcznym zakończeniu status przestaje być aktywny.

---

## 55. Tabela `user_statuses`

Status „Lecę na moto” jest przechowywany w tabeli:

`user_statuses`

Pola:

- `id` – UUID
- `user_id` – UUID
- `type` – string / enum
- `description` – string, nullable
- `latitude` – decimal, nullable
- `longitude` – decimal, nullable
- `started_at` – datetime
- `expires_at` – datetime
- `ended_at` – datetime, nullable
- `created_at`
- `updated_at`

Typ statusu w MVP:

`riding`

---

## 56. Status „Lecę na moto”

Użytkownik może ustawić status:

`Lecę na moto`

Może dodać krótki opis, np.:

`Jadę w stronę Krakowa`

Opis powinien posiadać ograniczenie długości.

Proponowany limit:

`100 znaków`

Dokładny limit:

`TODO – DO USTALENIA`

Status może zawierać:

- opis,
- lokalizację,
- datę i godzinę rozpoczęcia,
- czas zakończenia.

---

## 57. Czas obowiązywania statusu

Status nie powinien mieć sztywno określonego czasu życia.

Użytkownik ustawia konkretny czas zakończenia:

`expires_at`

Przykład:

`started_at = 2026-08-13T18:00:00Z`

`expires_at = 2026-08-13T20:00:00Z`

Użytkownik może później zmienić `expires_at`.

Może również zakończyć status wcześniej.

Po ręcznym zakończeniu:

`ended_at`

zostaje ustawione.

---

## 58. Aktywny status

Status jest aktywny, jeżeli:

- `ended_at` jest puste,
- `expires_at` jest późniejsze niż aktualny czas.

Status wygasły lub zakończony nie powinien być zwracany jako aktywny.

Status pozostaje w bazie jako historia zdarzenia.

---

## 59. Powiadomienie o statusie „Lecę na moto”

Po ustawieniu statusu znajomi użytkownika otrzymują powiadomienie.

Powiadomienie może zawierać:

- nick,
- informację „Lecę na moto”,
- krótki opis,
- opcjonalnie godzinę ustawienia.

Po kliknięciu użytkownik przechodzi do profilu znajomego.

---

## 60. Status na liście znajomych

Aktywny status „Lecę na moto” powinien być widoczny na liście znajomych.

Przykład:

`Marek`

`Lecę na moto – jadę do Krakowa`

`od 18:42`

Kliknięcie znajomego prowadzi do jego profilu.

---

## 61. Status na profilu

Profil użytkownika może prezentować jego aktualny status.

Przykład:

`Lecę na moto`

`Jadę w stronę Krakowa`

`Ustawiono: 18:42`

Jeżeli status wygasł lub został zakończony, nie powinien być prezentowany jako aktywny.

---

## 62. Lokalizacja statusu „Lecę na moto”

Przy ustawieniu statusu aplikacja może przekazać do API aktualną lokalizację:

- `latitude`
- `longitude`

Zapisywana jest lokalizacja z momentu ustawienia statusu.

Nie oznacza to ciągłego śledzenia użytkownika.

Status może dzięki temu zawierać informację, skąd użytkownik rozpoczął jazdę.

---

## 63. Tabela `breakdowns`

Awaria jest osobnym obiektem, ponieważ posiada własną historię i szczególne zasady widoczności.

Tabela:

`breakdowns`

Pola:

- `id` – UUID
- `user_id` – UUID
- `description` – string, nullable
- `latitude` – decimal
- `longitude` – decimal
- `started_at` – datetime
- `expires_at` – datetime
- `ended_at` – datetime, nullable
- `created_at`
- `updated_at`

---

## 64. Zgłoszenie awarii

Użytkownik może zgłosić:

`Mam awarię`

Może dodać krótki opis problemu.

Proponowany limit:

`100 znaków`

Dokładny limit:

`TODO – DO USTALENIA`

Przy zgłoszeniu awarii aplikacja pobiera aktualną lokalizację GPS.

Lokalizacja awarii jest obowiązkowa.

---

## 65. Czas obowiązywania awarii

Awaria ma określony czas obowiązywania.

Domyślny czas może wynosić:

`1 godzina`

Użytkownik może ustawić inny czas.

Dokładny minimalny i maksymalny czas:

`TODO – DO USTALENIA`

Awaria może zostać zakończona ręcznie wcześniej.

---

## 66. Aktywna awaria

Awaria jest aktywna, jeżeli:

- `ended_at` jest puste,
- `expires_at` jest późniejsze niż aktualny czas.

Po zakończeniu lub wygaśnięciu:

- awaria znika z listy aktywnych awarii,
- pozostaje w historii użytkownika.

---

## 67. Lokalizacja awarii

Lokalizacja awarii jest zapisywana na podstawie GPS.

API przechowuje:

- `latitude`
- `longitude`

Pokazywana jest rzeczywista lokalizacja przekazana przez urządzenie.

Nie należy celowo zaokrąglać lub ukrywać lokalizacji awarii.

Dokładna lokalizacja jest ważna, ponieważ osoba znajdująca się w pobliżu powinna móc znaleźć motocyklistę i udzielić mu pomocy.

---

## 68. Aktualizacja lokalizacji aktywnej awarii

Aplikacja nie śledzi stale lokalizacji użytkownika.

Jeżeli użytkownik przemieści się podczas aktywnej awarii, aplikacja może ponownie przesłać lokalizację.

API aktualizuje wtedy lokalizację aktywnej awarii.

Przykład:

`awaria o 14:00 – lokalizacja A`

następnie:

`aktualizacja o 14:20 – lokalizacja B`

Historia awarii może zachować podstawowy czas rozpoczęcia i końca, natomiast szczegółowe przechowywanie kolejnych zmian lokalizacji:

`TODO – DO USTALENIA`

---

## 69. Awaria a znajomi

Aktywna awaria jest widoczna dla wszystkich znajomych użytkownika.

Znajomi nie muszą znajdować się w pobliżu.

Po pobraniu danych z API mogą zobaczyć:

- kto ma awarię,
- motocykl,
- opis,
- lokalizację,
- czas zgłoszenia,
- czas obowiązywania,
- dostępne dane kontaktowe.

---

## 70. Awaria a BLE

Aktywna awaria jest również przekazywana lokalnie przez BLE.

Informację może otrzymać każdy użytkownik aplikacji znajdujący się w zasięgu BLE.

Nie ma znaczenia, czy użytkownik:

- jest znajomym,
- obserwuje osobę z awarią,
- jest obserwowany,
- wcześniej ją spotkał,
- nigdy jej nie spotkał.

Awaria ma wyższy priorytet niż normalne zasady widoczności użytkownika.

---

## 71. Awaria a incognito

Tryb incognito nie blokuje informacji o awarii.

Jeżeli użytkownik korzystający z incognito ma aktywną awarię:

- jego normalna obecność nadal pozostaje ukryta,
- ale aktywna awaria może zostać przekazana użytkownikom w pobliżu,
- awaria jest również dostępna jego znajomym poprzez API.

Awaria jest wyjątkiem od normalnych zasad incognito.

---

## 72. Informacja o awarii na dashboardzie

Aktywne awarie powinny być widoczne na głównym ekranie aplikacji.

Jeżeli użytkownik ma aktywną awarię, dashboard może prezentować ją jako najważniejszą informację.

Jeżeli znajomy ma aktywną awarię, użytkownik może zobaczyć informację o niej na dashboardzie.

Aktywne awarie powinny mieć wyższy priorytet wizualny niż zwykłe statusy.

---

## 73. Powiadomienie o awarii

Po wykryciu aktywnej awarii użytkownik może otrzymać powiadomienie.

Powiadomienie powinno być krótkie.

Przykład:

`MOŻLIWA AWARIA`

`Marek – Yamaha MT-07`

Po kliknięciu użytkownik przechodzi do szczegółów awarii.

Powiadomienie nie powinno zawierać nadmiernej ilości informacji.

---

## 74. Szczegóły awarii

Podstrona aktywnej awarii powinna zawierać m.in.:

- nick,
- dane motocykla,
- opis awarii,
- lokalizację na mapie,
- datę i godzinę,
- czas obowiązywania,
- dostępne dane kontaktowe.

Jeżeli użytkownik udostępnił numer telefonu w związku z awarią, podstrona powinna umożliwić rozpoczęcie połączenia telefonicznego.

---

## 75. Dane kontaktowe przy awarii

Przy zgłoszeniu awarii użytkownik powinien otrzymać jasną informację, że dodatkowe dane mogą zostać udostępnione w celu umożliwienia pomocy.

W szczególności mogą zostać udostępnione:

- imię,
- numer telefonu.

Jeżeli użytkownik normalnie ukrywa numer telefonu, może on zostać pokazany w kontekście aktywnej awarii.

Po zakończeniu awarii dane nie powinny stawać się publiczne w normalnym profilu użytkownika.

---

## 76. Historia awarii

Każda awaria pozostaje zapisana w historii użytkownika.

Historia zawiera co najmniej:

- datę,
- godzinę rozpoczęcia,
- lokalizację,
- opis,
- czas zakończenia lub wygaśnięcia.

Historia awarii pozostaje dostępna po jej zakończeniu.

---

## 77. Wspólna historia zdarzeń

Historia użytkownika może zawierać różne typy zdarzeń.

W MVP co najmniej:

- spotkania,
- awarie.

Historia może być prezentowana jako jedna chronologiczna lista.

Przykład:

`18:42 – Spotkałeś Marka`

`17:31 – Spotkałeś Pawła`

`14:20 – Awaria`

Nie jest wymagane tworzenie osobnej historii dla każdego typu zdarzenia.

---

## 78. Tryb incognito

Użytkownik może włączyć lub wyłączyć tryb incognito.

Przykładowe pole:

`incognito = true`

W trybie incognito:

- użytkownik nie jest wykrywany jako normalny użytkownik,
- użytkownik nie wykrywa innych użytkowników,
- normalne spotkania nie są zapisywane.

Tryb incognito dotyczy normalnego mechanizmu spotkań.

Nie blokuje awarii.

---

## 79. Ustawienia incognito

Stan incognito może być przechowywany jako ustawienie użytkownika.

Przykład:

`incognito = true`

`incognito = false`

Zmiana ustawienia powinna obowiązywać dla nowych zdarzeń.

Wcześniejsza historia spotkań nie jest usuwana po włączeniu incognito.

---

## 80. Status a incognito

Jeżeli użytkownik jest w trybie incognito, status „Lecę na moto” może nadal być dostępny dla jego znajomych.

Incognito dotyczy przede wszystkim mechanizmu lokalnego wykrywania i spotkań.

Dokładne zachowanie widoczności statusu podczas incognito:

`TODO – DO USTALENIA`

---

## 81. Statusy i awarie – wspólna zasada czasu

Statusy i awarie korzystają ze wspólnego modelu czasu:

- `started_at`
- `expires_at`
- `ended_at`

Dzięki temu użytkownik może:

- ustawić czas zakończenia,
- zmienić czas zakończenia,
- zakończyć element ręcznie,
- pozwolić mu automatycznie wygasnąć.

API nie powinno zakładać jednego sztywnego czasu obowiązywania dla wszystkich przypadków.

---

## 82. Aktywność elementów czasowych

Element czasowy jest aktywny, jeżeli:

`ended_at IS NULL`

oraz:

`expires_at > current_time`

Po wygaśnięciu lub zakończeniu ręcznym element nie jest zwracany jako aktywny.

Dane historyczne pozostają w bazie.

## 83. Autoryzacja API

Większość funkcji aplikacji jest dostępna wyłącznie dla zalogowanych użytkowników.

API powinno rozróżniać:

- użytkownika niezalogowanego,
- użytkownika zalogowanego.

Po zalogowaniu aplikacja otrzymuje token umożliwiający korzystanie z API.

Token nie powinien być przechowywany w aplikacji w postaci jawnej, jeżeli platforma udostępnia bezpieczny mechanizm przechowywania danych uwierzytelniających.

---

## 84. Rejestracja

Endpoint:

`POST /api/v1/auth/register`

Rejestracja wymaga:

- adresu e-mail,
- hasła.

Po poprawnej rejestracji konto jest od razu aktywne.

Po rejestracji użytkownik powinien zostać poprowadzony do uzupełnienia obowiązkowych danych profilu:

- nick,
- płeć,
- marka motocykla,
- model motocykla,
- rocznik motocykla,
- kolor.

---

## 85. Logowanie

Endpoint:

`POST /api/v1/auth/login`

Request powinien zawierać:

- `email`
- `password`

Po poprawnym logowaniu API zwraca token dostępu oraz podstawowe informacje o zalogowanym użytkowniku.

---

## 86. Wylogowanie

Endpoint:

`POST /api/v1/auth/logout`

Po wylogowaniu aktualny token powinien zostać unieważniony.

Aplikacja nie powinna mieć możliwości dalszego korzystania z API przy użyciu unieważnionego tokena.

---

## 87. Aktualny użytkownik

Endpoint:

`GET /api/v1/auth/me`

Powinien zwracać podstawowe dane zalogowanego użytkownika oraz informacje potrzebne aplikacji do rozpoczęcia pracy.

Może zawierać:

- ID użytkownika,
- e-mail,
- profil,
- motocykl,
- ustawienia,
- stan incognito,
- aktywny status,
- aktywną awarię.

---

## 88. Aktualizacja profilu

Endpoint:

`PATCH /api/v1/profile`

Użytkownik może zmienić własne dane profilu.

API musi sprawdzać poprawność danych oraz ich długość.

Użytkownik nie może za pomocą tego endpointu zmienić danych należących do innego użytkownika.

---

## 89. Aktualizacja motocykla

Endpoint:

`PATCH /api/v1/motorcycle`

Użytkownik może zmienić:

- markę,
- model,
- rocznik,
- kolor,
- opis,
- zdjęcie motocykla.

W MVP użytkownik posiada jeden główny motocykl.

---

## 90. Dashboard

Endpoint:

`GET /api/v1/dashboard`

Dashboard powinien dostarczyć podstawowe dane potrzebne na głównym ekranie aplikacji.

Może zawierać:

- dane użytkownika,
- liczbę znajomych,
- liczbę obserwowanych,
- liczbę obserwujących,
- aktywny status użytkownika,
- aktywną awarię użytkownika,
- aktywne awarie znajomych,
- aktywne statusy znajomych,
- ostatnie spotkania.

Zakres dashboardu może zostać rozszerzony podczas projektowania interfejsu.

---

## 91. Statusy – endpointy

Utworzenie statusu:

`POST /api/v1/statuses`

Pobranie aktywnego statusu użytkownika:

`GET /api/v1/statuses/active`

Zmiana statusu:

`PATCH /api/v1/statuses/{id}`

Zakończenie statusu:

`POST /api/v1/statuses/{id}/end`

Status może zostać zmieniony lub zakończony wyłącznie przez jego właściciela.

---

## 92. Awaria – endpointy

Zgłoszenie awarii:

`POST /api/v1/breakdowns`

Pobranie własnej aktywnej awarii:

`GET /api/v1/breakdowns/active`

Aktualizacja awarii:

`PATCH /api/v1/breakdowns/{id}`

Zakończenie awarii:

`POST /api/v1/breakdowns/{id}/end`

Historia awarii:

`GET /api/v1/breakdowns/history`

Szczegóły awarii:

`GET /api/v1/breakdowns/{id}`

Tylko właściciel może zakończyć lub zmienić własną awarię.

---

## 93. Aktywne awarie znajomych

API powinno umożliwiać pobranie aktywnych awarii znajomych.

Przykładowy endpoint:

`GET /api/v1/friends/breakdowns`

Odpowiedź powinna zawierać:

- użytkownika,
- motocykl,
- opis awarii,
- lokalizację,
- czas rozpoczęcia,
- czas wygaśnięcia,
- dostępne dane kontaktowe.

Lista zawiera wyłącznie aktywne awarie.

---

## 94. Spotkania – endpointy

Zapis spotkania:

`POST /api/v1/meetings`

Historia spotkań:

`GET /api/v1/meetings`

Szczegóły spotkania:

`GET /api/v1/meetings/{id}`

Lista powinna być paginowana.

---

## 95. Obserwowanie – endpointy

Rozpoczęcie obserwowania:

`POST /api/v1/users/{id}/follow`

Zakończenie obserwowania:

`DELETE /api/v1/users/{id}/follow`

Lista obserwowanych:

`GET /api/v1/following`

Liczba obserwujących może być zwracana w profilu lub danych użytkownika.

---

## 96. Znajomi – endpointy

Lista znajomych:

`GET /api/v1/friends`

Usunięcie znajomego:

`DELETE /api/v1/friends/{id}`

Zaproszenia:

`GET /api/v1/friend-requests/received`

`GET /api/v1/friend-requests/sent`

Wysłanie zaproszenia:

`POST /api/v1/friend-requests`

Akceptacja:

`POST /api/v1/friend-requests/{id}/accept`

Odrzucenie:

`POST /api/v1/friend-requests/{id}/reject`

Ignorowanie:

`POST /api/v1/friend-requests/{id}/ignore`

---

## 97. Format JSON

API wykorzystuje JSON.

Przykładowa poprawna odpowiedź:

```json
{
    "success": true,
    "data": {
        "id": "uuid"
    }
}
```

Przykładowa odpowiedź zawierająca listę:

```json
{
    "success": true,
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100
    }
}
```

Dokładny format odpowiedzi powinien być jednolity w całym API.

---

## 98. Błędy API

API powinno zwracać spójny format błędów.

Przykład:

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Nieprawidłowe dane.",
        "fields": {
            "email": [
                "Podany adres e-mail jest nieprawidłowy."
            ]
        }
    }
}
```

Błędy powinny wykorzystywać odpowiednie kody HTTP.

Najważniejsze:

- `200` – OK
- `201` – utworzono
- `204` – brak treści
- `400` – nieprawidłowe żądanie
- `401` – brak autoryzacji
- `403` – brak uprawnień
- `404` – nie znaleziono
- `409` – konflikt
- `422` – błąd walidacji
- `429` – zbyt wiele żądań
- `500` – błąd serwera

---

## 99. Walidacja

API musi samodzielnie walidować wszystkie dane otrzymane z aplikacji.

Nie można zakładać, że aplikacja mobilna zawsze wysyła poprawne dane.

Walidacji wymagają między innymi:

- adres e-mail,
- hasło,
- nick,
- dane motocykla,
- rocznik,
- opisy,
- latitude,
- longitude,
- daty,
- czas wygaśnięcia statusu,
- czas wygaśnięcia awarii,
- identyfikatory użytkowników.

Przykładowo:

`latitude` musi mieścić się w zakresie `-90` do `90`.

`longitude` musi mieścić się w zakresie `-180` do `180`.

---

## 100. Paginacja

Wszystkie potencjalnie duże listy powinny być paginowane.

Dotyczy to przede wszystkim:

- historii spotkań,
- historii awarii,
- znajomych,
- obserwowanych,
- zaproszeń.

Przykład:

`GET /api/v1/meetings?page=1&per_page=20`

API powinno posiadać rozsądny maksymalny limit `per_page`.

---

## 101. Uprawnienia

Każdy endpoint musi sprawdzać uprawnienia użytkownika.

Użytkownik może modyfikować wyłącznie własne dane.

Nie może:

- zmienić profilu innej osoby,
- zakończyć cudzej awarii,
- zakończyć cudzego statusu,
- usunąć cudzej relacji,
- odczytać ukrytych danych innego użytkownika,
- pobrać prywatnej historii innego użytkownika.

Wyjątkiem są informacje, które zgodnie z zasadami MVP mają być dostępne w związku z aktywną awarią.

---

## 102. Prywatność danych

API powinno stosować zasadę minimalnego udostępniania danych.

Jeżeli użytkownik ukrywa dane w profilu, API nie powinno ich zwracać aplikacji tylko po to, aby aplikacja ukryła je później.

Dotyczy to przede wszystkim:

- telefonu,
- e-maila,
- imienia,
- nazwiska.

Dane udostępniane w związku z awarią powinny być traktowane jako osobny przypadek.

---

## 103. Usunięcie konta

Endpoint:

`DELETE /api/v1/account`

Usunięcie konta powinno usunąć wszystkie dane należące do użytkownika.

Dotyczy to co najmniej:

- konta,
- profilu,
- motocykla,
- tokenów BLE,
- urządzeń,
- obserwowania,
- znajomych,
- zaproszeń,
- spotkań,
- statusów,
- awarii,
- ustawień.

Po usunięciu konta dane nie powinny pozostać dostępne poprzez API.

Jeżeli relacje lub dane historyczne wymagają specjalnego rozwiązania ze względów technicznych lub prawnych, sposób ich obsługi należy ustalić przed implementacją.

---

## 104. Wersjonowanie API

API powinno być wersjonowane od początku.

Pierwsza wersja:

`/api/v1`

Przykład:

`/api/v1/profile`

Wprowadzenie kolejnej wersji nie powinno wymagać natychmiastowej aktualizacji wszystkich użytkowników aplikacji.

---

## 105. Bezpieczeństwo tokenów

Tokeny API oraz tokeny BLE powinny być traktowane jako dane wrażliwe.

Nie należy:

- logować ich w zwykłych logach aplikacji,
- zwracać ich osobom nieuprawnionym,
- umieszczać ich w odpowiedziach API, jeżeli nie jest to konieczne,
- przechowywać haseł w postaci jawnej.

Token BLE nie powinien pozwalać osobie postronnej na bezpośrednie poznanie danych użytkownika.

---

## 106. Rate limiting

API powinno posiadać ograniczenia liczby żądań.

Szczególnej ochrony wymagają:

- logowanie,
- rejestracja,
- endpointy związane ze spotkaniami,
- endpointy związane z awariami,
- zaproszenia,
- operacje na relacjach.

Ma to ograniczyć spam oraz przypadkowe lub złośliwe przeciążenie API.

---

## 107. Duplikaty żądań

API musi być odporne na wielokrotne wysłanie tego samego żądania.

Jest to szczególnie ważne dla:

- spotkań,
- zaproszeń,
- statusów,
- awarii.

Przykład:

Aplikacja wysyła żądanie utworzenia spotkania, ale nie otrzymuje odpowiedzi.

Aplikacja wysyła żądanie ponownie.

API nie powinno utworzyć dwóch identycznych zdarzeń.

Dokładny mechanizm idempotencji:

`TODO – DO USTALENIA`

---

## 108. Praca bez internetu

Aplikacja może działać przez pewien czas bez połączenia z API.

W szczególności BLE powinno działać niezależnie od internetu.

Jeżeli aplikacja wykryje użytkownika lub inne zdarzenie bez dostępu do internetu, może przechować zdarzenie lokalnie i wysłać je do API później.

Zdarzenie powinno zachować:

- rzeczywisty czas zdarzenia,
- lokalizację z momentu zdarzenia,
- identyfikator wykrytej osoby,
- typ zdarzenia.

Szczegółowa kolejka offline po stronie aplikacji:

`TODO – DO USTALENIA`

---

## 109. Oszczędzanie baterii

Aplikacja nie powinna wymagać stałej komunikacji z API.

Nie należy projektować mechanizmu polegającego na ciągłym wysyłaniu:

- lokalizacji,
- informacji o obecności,
- zapytań o użytkowników znajdujących się w pobliżu.

BLE powinno odpowiadać za lokalne wykrywanie użytkowników.

GPS powinien być pobierany wtedy, gdy jest potrzebny do konkretnego zdarzenia.

API powinno otrzymywać dane głównie w związku z konkretnymi zdarzeniami.

---

## 110. Powiadomienia w MVP

W MVP nie jest wymagane korzystanie z zewnętrznego systemu push notifications.

Aplikacja powinna wykorzystywać przede wszystkim dostępne mechanizmy lokalnych powiadomień aplikacji.

Dotyczy to m.in.:

- spotkań,
- zaproszeń,
- statusów,
- awarii.

Zewnętrzne push notifications, np. za pomocą usług Google/Apple, pozostają poza zakresem pierwszej wersji.

Architektura API powinna jednak umożliwiać ich późniejsze dodanie.

---

## 111. Ważność awarii

Awaria ma wyższy priorytet niż normalne zdarzenia.

Jeżeli aplikacja wykryje aktywną awarię przez BLE, powinna potraktować ją jako ważniejszą informację niż zwykłe spotkanie.

Aktywna awaria może zostać pokazana:

- jako powiadomienie,
- na dashboardzie,
- na dedykowanej stronie szczegółów,
- na mapie,
- z możliwością kontaktu z motocyklistą.

---

## 112. API a lokalne BLE

API nie odpowiada za samo wykrywanie urządzeń.

Odpowiedzialność jest podzielona:

### Aplikacja

- skanowanie BLE,
- wykrywanie tokenów,
- pobranie GPS,
- lokalne powiadomienie,
- przechowywanie zdarzeń offline.

### API

- identyfikacja użytkownika,
- zapis zdarzenia,
- sprawdzanie uprawnień,
- sprawdzanie karencji,
- przechowywanie historii,
- synchronizacja danych.

---

## 113. Projektowanie pod przyszłą rozbudowę

MVP nie powinno zawierać funkcji, które nie są potrzebne na start.

Jednocześnie API powinno pozostawiać możliwość późniejszego dodania:

- czatu 1:1,
- czatu grupowego,
- grup motocyklistów,
- śledzenia przejazdu,
- zapisu GPX,
- odznak,
- systemu sponsorów,
- funkcji premium,
- zewnętrznych push notifications,
- automatycznego wykrywania wypadku.

Te funkcje nie powinny być implementowane jako część MVP.

---

## 114. Podstawowa zasada architektury

API powinno być:

- proste,
- bezpieczne,
- oszczędne,
- skalowalne,
- przygotowane na późniejszy rozwój.

Nie należy komplikować MVP rozwiązaniami potrzebnymi dopiero przy przyszłych funkcjach.

Najważniejszym zadaniem API w MVP jest niezawodne przechowywanie i synchronizowanie:

- użytkowników,
- profili,
- motocykli,
- relacji,
- spotkań,
- statusów,
- awarii,
- historii.

---

# 115. Elementy do ustalenia przed rozpoczęciem implementacji

Przed przygotowaniem finalnych migracji Laravel oraz dokładnego kontraktu API należy ustalić:

- dokładny mechanizm tokenów BLE,
- czas karencji spotkań,
- limity długości pól,
- dokładne zasady widoczności profilu,
- dokładne dane udostępniane przy awarii,
- domyślny i maksymalny czas awarii,
- domyślny i maksymalny czas statusu,
- zachowanie statusu podczas incognito,
- dokładny mechanizm lokalizacji aktywnej awarii,
- sposób obsługi zdarzeń offline,
- mechanizm ochrony przed duplikatami,
- finalny format odpowiedzi JSON,
- finalny sposób autoryzacji,
- finalny model relacji znajomych,
- sposób całkowitego usuwania danych.

Te decyzje powinny zostać podjęte przed przygotowaniem finalnej bazy danych i implementacją API.