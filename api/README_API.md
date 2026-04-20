# 📘 Guida al Template per Endpoint API

Questo file (`api/endpoint_template.php`) serve come base standardizzata per tutti i nuovi endpoint del backend. Garantisce coerenza nelle risposte, gestione degli errori e compatibilità con il frontend.

---

## 🏗️ Struttura del Codice

### 1. Intestazioni HTTP e CORS
Il template inizia configurando gli header necessari per la comunicazione web moderna:
*   **JSON Content-Type**: Comunica al client che i dati restituiti sono in formato JSON.
*   **CORS Support**: Permette a applicazioni esterne (come un sito React o Vue) di chiamare l'API senza blocchi di sicurezza del browser.
*   **Gestione `OPTIONS`**: Gestisce le "richieste pre-volo" (preflight) inviate dai browser prima di una richiesta `POST` o `PUT`.

### 2. Struttura della Risposta Standard
Viene inizializzato un array `$response` che assicura che ogni risposta API abbia sempre lo stesso formato, facilitando il lavoro del frontend:
```json
{
  "status": "success" o "error",
  "message": "Descrizione dell'esito",
  "data": { ... i tuoi dati qui ... }
}
```

### 3. Gestione degli Errori (`try...catch`)
Tutta la logica dell'endpoint è racchiusa in un blocco `try`.
*   **Successo**: Se il codice viene eseguito senza errori, viene restituito lo stato `200 OK`.
*   **Errore**: Se viene lanciata un'eccezione (`throw new Exception`), il blocco `catch` cattura l'errore, imposta il codice di stato HTTP corretto (es. 400 per dati errati, 500 per errore server) e restituisce un messaggio d'errore pulito in JSON.

### 4. Parsing dei Dati in Ingresso
Il template gestisce automaticamente due tipi di input:
*   `$input`: Contiene i dati inviati nel corpo della richiesta (formato JSON, tipico di `POST`/`PUT`).
*   `$queryParams`: Contiene i parametri passati nell'URL (tipico di `GET`).

---

## 🚀 Come Creare un Nuovo Endpoint

Per creare una nuova funzionalità (ad esempio, una lista di prodotti):

1.  **Copia il template**: Salva una copia di `endpoint_template.php` con un nuovo nome (es. `get_products.php`).
2.  **Inserisci la Logica**: Trova il commento `YOUR LOGIC STARTS HERE` e inserisci il tuo codice (es. query al database).
3.  **Popola i Dati**:
    ```php
    // Esempio di logica interna
    // $prodotti = $db->query("SELECT * FROM prodotti");
    
    $response["status"] = "success";
    $response["message"] = "Prodotti recuperati con successo";
    $response["data"] = $prodotti;
    http_response_code(200);
    ```
4.  **Lancia Errori se necessario**:
    ```php
    if (!$utente_autorizzato) {
        throw new Exception("Non sei autorizzato", 401);
    }
    ```

---

## 📝 Esempio di Risposta JSON Successo
```json
{
  "status": "success",
  "message": "Endpoint reached successfully",
  "data": {
    "method": "GET",
    "timestamp": "2026-04-20 14:30:00"
  }
}
```
