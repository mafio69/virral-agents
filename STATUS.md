# STATUS

**Ostatnia aktualizacja:** 2026-08-30
**Aktualny branch:** `feature/multi-ai-providers` (z `master`)

## W trakcie

### Wielu dostawców AI (OVH + Z.AI + ...) — modele w bazie, szyfrowane sekrety
- Cel: system wielodostawcowy OpenAI-compatible; dostawcy i modele w SQLite,
  sekrety zaszyfrowane w bazie (klucz `DB_HASH` z `.env`), fabryka klientów AI.
- Plan szczegółowy: `docs/plan-multi-ai-providers.md`

## Etapy
1. [x] Plan zaakceptowany, branch utworzony, CHANGELOG zainicjowany
2. [x] Dokumentacja planu w `docs/`
3. [x] Zadania zarejestrowane w DevBrain TODO
4. [x] Migracja bazy (ai_urls, ai_providers, ai_models, chats.model_id) + seed
5. [x] Moduł szyfrowania (SecretService, sodium, DB_HASH)
6. [x] DTO + Repozytoria (AiUrl, AiProvider, AiModel) z soft delete
7. [x] AiClientInterface + OpenAiCompatClient (openai-php/client) + AiClientFactory
8. [x] ChatService/ChatController na model_id + endpoint /api/ai-models z bazy
9. [x] Testy + quality gates (phpunit)
10. [x] HANDOFF.md

## Znane ograniczenia
- Seed providerów ma puste sekrety (do uzupełnienia przez ownera przez API/SQL —
  sekret musi być zaszyfrowany SecretService, nigdy plaintext w bazie).
- Wybór modelu w UI czatu jeszcze nie czyta /api/ai-models (kolejny task).
