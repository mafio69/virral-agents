# CHANGELOG

## 2026-08-30 — start tasku: multi-AI providers (branch `feature/multi-ai-providers`)

Zaczynam: system wielu dostawców AI (OVH + Z.AI + …) z modelami i dostawcami w SQLite.
Sekrety providerów zaszyfrowane w bazie (sodium secretbox, klucz `DB_HASH` z `.env`).
Fabryka `AiClientFactory` tworzy klientów OpenAI-compatible (`openai-php/client`)
na podstawie rekordu modelu z bazy. Przebudowa `chats.model` (string) → `chats.model_id` (FK).
Endpoint `/api/ai-models` będzie serwował modele z bazy (bez sekretów).

---
