# 🤖 MCP - INSTRUKCJA DLA AI
## Jak AI obsługuje Aplikację Ważne Sprawy

---

## 📌 SPIS TREŚCI

1. [Czym jest MCP?](#czym-jest-mcp)
2. [Jak się logować?](#jak-się-logować)
3. [Co to jest TOKEN?](#co-to-jest-token)
4. [Jak wysyłać żądania?](#jak-wysyłać-żądania)
5. [Przykłady dla AI](#przykłady-dla-ai)
6. [Obsługa błędów](#obsługa-błędów)
7. [Najczęstsze problemy](#najczęstsze-problemy)

---

## Czym jest MCP?

**MCP** = **Model Context Protocol** 

To po prostu zestaw reguł jak AI (taki jak Ty) komunikuje się z aplikacją.

**Prościej:**
- 🔐 **Autentykacja** - AI musi się zalogować z tokenem
- 📡 **Protocół HTTP** - Wysyłamy żądania (GET, POST, PUT, DELETE)
- 🔑 **Token Authorization** - Każde żądanie wymaga tokenu bezpieczeństwa
- ✅ **JSON odpowiedzi** - Serwer odpowiada w formacie JSON

---

## Jak się logować?

### KROK 1: Wygeneruj token (robi to administrator)

```bash
curl -X POST http://localhost:3001/api/tokens/generate \
  -H "Content-Type: application/json" \
  -d '{"adminPassword":"admin123"}'
```

**Co dostaniesz:**
```json
{
  "token": "sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000"
}
```

### KROK 2: Zapisz token

Token to twój **bezpieczny bilet** do systemu.

```
TOKEN = sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000
```

**⚠️ WAŻNE:**
- Nie udostępniaj tokenu nikomu
- Przechowuj go w bezpiecznym miejscu
- Każdy z tokenem może dodawać/usuwać sprawy

---

## Co to jest TOKEN?

**TOKEN** to magiczny klucz, który mówi systemowi: **"To ja jestem uprawniony!"**

Przykład tokenu:
```
sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000
```

**Rozbicie tokenu:**
- `sk_` = prefix "secret key"
- `d247de2f...` = losowy ciąg (unikalne ID)
- Każdy token jest **inny**
- Każdy token może mieć **inne uprawnienia**

**Co token zawiera:**
```json
{
  "token": "sk_xyz...",
  "created": "2026-08-19T05:26:49.488Z",
  "usage": 5,          // ile razy był użyty
  "lastUsed": "2026-08-19T05:27:15.710Z"
}
```

---

## Jak wysyłać żądania?

Każde żądanie do API musi mieć **token w nagłówku**.

### Podstawowy szablon:

```
Method: GET/POST/PUT/DELETE
URL: http://localhost:3001/api/[endpoint]
Header: Authorization: Bearer [TOKEN]
Body: JSON (jeśli POST/PUT)
```

### Praktycznie:

#### 1️⃣ Pobierz wszystkie sprawy (GET)

```bash
curl -H "Authorization: Bearer sk_d247de2f..." \
     http://localhost:3001/api/cases
```

**Odpowiedź:**
```json
{
  "cases": [
    {
      "id": 1787117241819,
      "number": "190/2024",
      "name": "Eksmisja",
      "description": "Sprawa o eksmisję z nieruchomości",
      "status": "active",
      "date": "2026-08-19",
      "relatedId": null,
      "createdAt": 1787117241819,
      "createdBy": "sk_d247de2f..."
    }
  ],
  "timestamp": "2026-08-19T05:27:15.710Z"
}
```

---

#### 2️⃣ Dodaj nową sprawę (POST)

```bash
curl -X POST http://localhost:3001/api/cases \
  -H "Authorization: Bearer sk_d247de2f..." \
  -H "Content-Type: application/json" \
  -d '{
    "number": "191/2024",
    "name": "Pozew cywilny",
    "description": "Pozew o zapłatę",
    "status": "active",
    "date": "2026-08-19",
    "relatedId": null
  }'
```

**Odpowiedź:**
```json
{
  "case": {
    "id": 1787117999999,
    "number": "191/2024",
    "name": "Pozew cywilny",
    ...
  },
  "message": "Case added successfully"
}
```

---

#### 3️⃣ Edytuj sprawę (PUT)

```bash
curl -X PUT http://localhost:3001/api/cases/1787117241819 \
  -H "Authorization: Bearer sk_d247de2f..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Eksmisja - zmiana nazwy",
    "status": "closed"
  }'
```

---

#### 4️⃣ Usuń sprawę (DELETE)

```bash
curl -X DELETE http://localhost:3001/api/cases/1787117241819 \
  -H "Authorization: Bearer sk_d247de2f..."
```

---

#### 5️⃣ Sprawdź status tokenu (GET)

```bash
curl -H "Authorization: Bearer sk_d247de2f..." \
     http://localhost:3001/api/tokens/status
```

**Odpowiedź:**
```json
{
  "token": "sk_d247de2f...",
  "usage": 7,
  "lastUsed": "2026-08-19T05:27:15.710Z",
  "created": "2026-08-19T05:26:49.488Z"
}
```

---

## Przykłady dla AI

### Dla Python (requests)

```python
import requests
import json

# Ustawienia
BASE_URL = "http://localhost:3001/api"
TOKEN = "sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000"
HEADERS = {"Authorization": f"Bearer {TOKEN}"}

# 1. Pobierz wszystkie sprawy
print("📋 Pobieranie spraw...")
response = requests.get(f"{BASE_URL}/cases", headers=HEADERS)
cases = response.json()
for case in cases['cases']:
    print(f"  - {case['number']}: {case['name']} ({case['status']})")

# 2. Dodaj nową sprawę
print("\n➕ Dodawanie nowej sprawy...")
new_case = {
    "number": "192/2024",
    "name": "Apelacja",
    "description": "Apelacja od wyroku",
    "status": "active"
}
response = requests.post(f"{BASE_URL}/cases", json=new_case, headers=HEADERS)
if response.status_code == 200:
    print(f"✅ Sprawa dodana! ID: {response.json()['case']['id']}")
else:
    print(f"❌ Błąd: {response.json()['error']}")

# 3. Edytuj sprawę
print("\n✏️ Edytowanie sprawy...")
case_id = response.json()['case']['id']
update = {"status": "closed"}
response = requests.put(f"{BASE_URL}/cases/{case_id}", json=update, headers=HEADERS)
if response.status_code == 200:
    print(f"✅ Sprawa zaktualizowana!")

# 4. Sprawdź status tokenu
print("\n🔐 Status tokenu:")
response = requests.get(f"{BASE_URL}/tokens/status", headers=HEADERS)
status = response.json()
print(f"  - Użyty: {status['usage']} razy")
print(f"  - Ostatnio: {status['lastUsed']}")
```

---

### Dla JavaScript/Node.js

```javascript
const token = "sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000";
const baseURL = "http://localhost:3001/api";

async function getCases() {
  const response = await fetch(`${baseURL}/cases`, {
    headers: { "Authorization": `Bearer ${token}` }
  });
  return response.json();
}

async function addCase(caseData) {
  const response = await fetch(`${baseURL}/cases`, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${token}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify(caseData)
  });
  return response.json();
}

// Użycie
(async () => {
  // Pobierz sprawy
  const cases = await getCases();
  console.log("Sprawy:", cases);

  // Dodaj sprawę
  const newCase = await addCase({
    number: "193/2024",
    name: "Sprawa testowa",
    description: "Test z JavaScript",
    status: "active"
  });
  console.log("Dodana sprawa:", newCase);
})();
```

---

### Dla Claude (AI model)

```
System prompt dla Claude:

Jesteś asystentem do obsługi aplikacji "Ważne Sprawy".

Masz dostęp do API na http://localhost:3001

TOKEN: sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000

Wszystkie żądania wysyłasz z nagłówkiem:
Authorization: Bearer [TOKEN]

Możliwe akcje:
- GET /api/cases - pobierz sprawy
- POST /api/cases - dodaj sprawę
- PUT /api/cases/:id - edytuj sprawę
- DELETE /api/cases/:id - usuń sprawę
- GET /api/tokens/status - sprawdź status

Zawsze odpowiadaj w JSON.
```

---

## Obsługa błędów

### HTTP Status Codes

| Kod | Znaczenie | Przykład |
|-----|-----------|----------|
| **200** | OK - Sukces | Sprawa dodana |
| **400** | Bad Request - Brakuje danych | `{"error": "Missing required fields"}` |
| **401** | Unauthorized - Zły token | `{"error": "Unauthorized"}` |
| **404** | Not Found - Nie znaleziono | `{"error": "Case not found"}` |
| **500** | Server Error - Problem serwera | Serwer się zepsuł |

### Praktyczne przykłady błędów

#### ❌ Brak tokenu
```bash
curl http://localhost:3001/api/cases
```
**Odpowiedź:**
```json
{"error": "Unauthorized"}
```

**Rozwiązanie:** Dodaj nagłówek `Authorization: Bearer TOKEN`

---

#### ❌ Zły token
```bash
curl -H "Authorization: Bearer zly_token" \
     http://localhost:3001/api/cases
```
**Odpowiedź:**
```json
{"error": "Unauthorized"}
```

**Rozwiązanie:** Sprawdź czy token jest prawidłowy

---

#### ❌ Brakujące pola w POST
```bash
curl -X POST http://localhost:3001/api/cases \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Bez numeru"}'
```
**Odpowiedź:**
```json
{"error": "Missing required fields"}
```

**Rozwiązanie:** Wysyłaj `number` i `name` (wymagane pola)

---

#### ❌ ID sprawy nie znalezione
```bash
curl -X DELETE http://localhost:3001/api/cases/9999999 \
  -H "Authorization: Bearer TOKEN"
```
**Odpowiedź:**
```json
{"error": "Case not found"}
```

**Rozwiązanie:** Sprawdź czy ID sprawy jest prawidłowy

---

## Najczęstsze problemy

### ⚠️ Problem 1: "Connection refused"

```
Error: connect ECONNREFUSED 127.0.0.1:3001
```

**Rozwiązanie:**
- Serwer nie działa
- Uruchom: `node server.js`
- Sprawdź: `curl http://localhost:3001/api/health`

---

### ⚠️ Problem 2: "Unauthorized"

**Przyczyna:** Tokenu nie ma lub jest zły

**Rozwiązanie:**
```bash
# Wygeneruj nowy token
curl -X POST http://localhost:3001/api/tokens/generate \
  -H "Content-Type: application/json" \
  -d '{"adminPassword":"admin123"}'
```

---

### ⚠️ Problem 3: "Missing required fields"

**Przyczyna:** Brakuje wymaganych pól w żądaniu

**Wymagane pola przy dodawaniu sprawy:**
- ✅ `number` - numer sprawy (string)
- ✅ `name` - nazwa sprawy (string)
- ⚪ `description` - opis (optional)
- ⚪ `status` - status (optional, domyślnie: "active")
- ⚪ `date` - data (optional, domyślnie: dzisiaj)

**Przykład prawidłowego żądania:**
```json
{
  "number": "190/2024",
  "name": "Eksmisja",
  "description": "Sprawa o eksmisję",
  "status": "active",
  "date": "2026-08-19"
}
```

---

### ⚠️ Problem 4: CORS Error

```
Access to XMLHttpRequest at 'http://localhost:3001/...' 
blocked by CORS policy
```

**Przyczyna:** Frontend z innego portu próbuje dostępu do API

**Rozwiązanie:** CORS jest już włączony w `server.js`
- Frontend: http://localhost:8001
- API: http://localhost:3001
- ✅ Powinno działać

Jeśli nie: sprawdź czy `cors` jest zainstalowany:
```bash
npm install cors
```

---

## 📊 Schemat Komunikacji

```
┌─────────────┐
│     AI      │
│  (Claude)   │
└──────┬──────┘
       │
       │ 1. Generuj token
       │    POST /api/tokens/generate
       │
       ▼
┌────────────────────────────────────────┐
│      Backend API (Node.js)             │
│      http://localhost:3001             │
│                                        │
│  ✅ Weryfikuj token                    │
│  ✅ Obsługuj żądanie (GET/POST/etc)    │
│  ✅ Loguj akcję (ukryty monitoring)    │
│  ✅ Zwróć odpowiedź (JSON)             │
└────────────┬─────────────────────────┘
             │
             │ 2. Pobierz/Dodaj sprawy
             │    GET/POST /api/cases
             │
             ▼
        ┌─────────────┐
        │  Baza danych│
        │  (cases.json)
        └─────────────┘
```

---

## 🔒 Zasady Bezpieczeństwa

### ✅ BEZPIECZNE:
- Wysyłać tokeny w nagłówku `Authorization`
- Regenerować tokeny co miesiąc
- Nie dzielić się tokenami
- Logować wszystkie akcje (już implementowane)

### ❌ NIEBEZPIECZNE:
- Wysyłać tokeny w URL: `?token=abc`
- Przechowywać tokeny w logach
- Dzielić się tokenami publiczne
- Używać tego samego tokenu dla wielu aplikacji

---

## 📞 Troubleshooting

| Problem | Objawy | Rozwiązanie |
|---------|--------|------------|
| Serwer nie startuje | `Cannot find module express` | `npm install` |
| Token nie działa | `401 Unauthorized` | Zregeneruj token |
| Sprawy nie się dodają | Brak odpowiedzi | Sprawdź `Content-Type: application/json` |
| Baza danych znika | `cases.json` znika | Sprawdź uprawnienia do katalogu |

---

## 🚀 Pełny workflow dla AI

```javascript
// 1. Logowanie
const token = "sk_..."; // wygeneruj raz

// 2. Pobierz sprawy
fetch('http://localhost:3001/api/cases', {
  headers: { 'Authorization': `Bearer ${token}` }
});

// 3. Dodaj sprawę
fetch('http://localhost:3001/api/cases', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    number: '190/2024',
    name: 'Eksmisja',
    status: 'active'
  })
});

// 4. Monitoruj
fetch('http://localhost:3001/api/tokens/status', {
  headers: { 'Authorization': `Bearer ${token}` }
});

// 5. Obsłuż błędy
.catch(error => console.error('API Error:', error));
```

---

## 📚 Załączniki

### Załącznik A: Kompletny curl script

```bash
#!/bin/bash

TOKEN="sk_PRZYKLADOWY_TOKEN_WYGENERUJ_WLASNY_00000000000000000000000000000000000000"
BASE_URL="http://localhost:3001/api"

# Test API
echo "🔍 Test API - Ważne Sprawy"

# 1. Health check
echo "1️⃣  Health check..."
curl -s http://localhost:3001/api/health | jq .

# 2. Pobierz sprawy
echo -e "\n2️⃣  Pobierz sprawy..."
curl -s -H "Authorization: Bearer $TOKEN" $BASE_URL/cases | jq .

# 3. Dodaj sprawę
echo -e "\n3️⃣  Dodaj sprawę..."
curl -s -X POST $BASE_URL/cases \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"number":"195/2024","name":"Test API","status":"active"}' | jq .

# 4. Status tokenu
echo -e "\n4️⃣  Status tokenu..."
curl -s -H "Authorization: Bearer $TOKEN" $BASE_URL/tokens/status | jq .

echo -e "\n✅ Test zakończony!"
```

---

## 🎓 Streszczenie

| Co | Jak | Gdzie |
|----|-----|-------|
| **Wygeneruj token** | `POST /api/tokens/generate` | Raz, na samym początku |
| **Pobierz sprawy** | `GET /api/cases + TOKEN` | Zawsze przed operacją |
| **Dodaj sprawę** | `POST /api/cases + TOKEN` | Nowa sprawa |
| **Edytuj sprawę** | `PUT /api/cases/ID + TOKEN` | Zmiana statusu/opisu |
| **Usuń sprawę** | `DELETE /api/cases/ID + TOKEN` | Usunięcie sprawy |
| **Sprawdź token** | `GET /api/tokens/status + TOKEN` | Debug |

---

**Stworzone dla:** mf1969@gmail.com  
**Data:** 2026-08-19  
**Wersja:** 1.0.0  
**Status:** ✅ Production Ready
