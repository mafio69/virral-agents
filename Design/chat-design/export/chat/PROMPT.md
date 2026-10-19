# Prompt dla programisty / agenta — czat Virral Agents

Masz gotowy widok `chat.html` (jeden plik: HTML + CSS + krótki JS demo, bez zewnętrznych zasobów). Twoje zadanie: **podłącz go do backendu bez zmiany wyglądu**.

## Zasady
- Nie zmieniaj stylów, układu ani tekstów interfejsu (po polsku). Zmieniasz tylko logikę w `<script>` i ewentualnie wynosisz CSS/JS do osobnych plików.
- Zachowaj identyfikatory: `chat-list`, `new-chat`, `chat-title`, `chat-model`, `chat-connection`, `chat-messages`, `chat-form`, `chat-input`, `send-button`, `chat-status`, `markdown-help`.
- Blok `<aside>…<div class="demo" data-demo>` (przełącznik stanów) i `<template id="tpl-sample">` to tylko demo — **usuń** przy integracji.

## Do podłączenia
1. **Historia (`#chat-list`)** — renderuj z API: grupy „Dziś / Wczoraj / Ostatnie 7 dni / Starsze”, element `button.chat-item[data-chat-id]`, aktywny ma `aria-current="true"`. Kliknięcie ładuje wiadomości do `#thread`.
2. **Nowa rozmowa (`#new-chat`)** — czyści wątek, stan `empty`, tytuł „Nowa rozmowa”. Rozmowa trafia na listę po pierwszej wiadomości (`createChat()` w JS pokazuje wzorzec).
3. **Połączenie i model (`#chat-connection`, `#chat-model`)** — wypełnij `<option>` z API; wartość wysyłaj razem z wiadomością. Długie nazwy są obcinane wielokropkiem przez CSS — nic nie dopisuj.
4. **Wysyłanie (`#chat-form`)** — w handlerze `submit` zastąp `respond()` wywołaniem API (najlepiej streaming). Na czas odpowiedzi: `setState('pending')`; po sukcesie `setState('chat')`; po błędzie `addError()` + `setState('error')`; przy 401 `setState('loggedout')`.
5. **Stany** (`#app[data-state]`): `chat`, `empty`, `pending`, `error`, `loggedout`. CSS już je obsługuje, wystarczy `setState(...)`. Krótkie komunikaty statusu w obiekcie `MSG`.
6. **Markdown** — odpowiedzi AI renderuj do `.prose` **po stronie klienta z sanityzacją** (np. marked + DOMPurify albo markdown-it z `html:false`). Nigdy `innerHTML` z surowego tekstu modelu. Wymagana struktura:
   - blok kodu: `<figure class="code"><div class="code-head"><span class="lang">php</span><button class="btn btn-sm" data-copy-code>…</button></div><pre><code>…</code></pre></figure>` (przycisk „Kopiuj” już działa przez delegację),
   - tabela: owiń w `<div class="table-wrap">`,
   - linki: `target="_blank" rel="noopener"`.
   Wiadomości użytkownika wstawiaj przez `textContent`.
7. **Ściąga Markdown (`#markdown-help`)** — natywne `<details>`; nic nie dotykaj, nie renderuj go ponownie (jego stan musi przetrwać pisanie, wysyłanie i zmianę rozmowy).

## Zachowanie, które ma zostać
- Enter wysyła, Shift+Enter nowa linia (na ekranach dotykowych Enter = nowa linia). Wysyłanie zablokowane przy `pending`, `loggedout` i pustym polu.
- Pole rośnie do 200 px; `font-size:16px` na telefonie (iOS bez zoomu).
- Po dodaniu wiadomości przewiń `#chat-messages` na dół (`toBottom()`).
- Telefon (<861 px): historia jako szuflada, Esc ją zamyka.

## Definicja gotowości
Rozmowa, nowa rozmowa, wysyłanie ze streamingiem, błąd, wygaśnięcie sesji i kopiowanie kodu działają na prawdziwych danych; widok wygląda identycznie jak oryginał na desktopie i telefonie; brak błędów w konsoli.
