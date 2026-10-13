# Biblioteki widoku czatu

Pliki są serwowane lokalnie. Czat nie pobiera bibliotek z CDN podczas używania aplikacji.

| Biblioteka | Wersja | Plik | Źródło |
| --- | --- | --- | --- |
| Marked | 18.1.0 | marked.umd.js | https://cdn.jsdelivr.net/npm/marked@18.1.0/lib/marked.umd.js |
| DOMPurify | 3.4.16 | purify.min.js | https://raw.githubusercontent.com/cure53/DOMPurify/3.4.16/dist/purify.min.js |

Licencje: MARKED-LICENSE.txt i DOMPURIFY-LICENSE.txt.

Marked przetwarza Markdown, a DOMPurify oczyszcza wynik. Warstwa aplikacji dopuszcza wyłącznie elementy tekstowe, listy, tabele, kod i linki HTTP/HTTPS. Obrazy, formularze, ramki, skrypty i style z treści wiadomości są niedopuszczone. Oczyszczanie HTML nie zastępuje izolacji kontekstów modeli ani maskowania poufnych danych na serwerze.

Przy aktualizacji bibliotek pobierz przypięte wydanie, zachowaj licencje i uruchom testy renderowania. Nie modyfikuj kodu bibliotek ręcznie.
