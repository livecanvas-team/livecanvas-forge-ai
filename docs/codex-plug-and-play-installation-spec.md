# Specifica tecnica: installazione Codex plug-and-play per LiveCanvas Forge AI

## Obiettivo

Rendere il plugin LiveCanvas Forge AI realmente plug-and-play con Codex, evitando configurazioni stale, token non allineati, workspace root sbagliate e probe REST ricorsivi. L'utente deve poter aprire la pagina del plugin, cliccare un'azione guidata, riavviare Codex se necessario e avere il bridge funzionante senza modificare manualmente `~/.codex/config.toml`.

## Problemi osservati

1. **Config Codex stale**
   - `~/.codex/config.toml` puntava ancora a una vecchia root:
     `/Users/commander/Studio/consultala`
   - Il sito attuale era invece:
     `/Users/commander/Local Sites/consultala-hr/app/public`
   - Anche `LCFA_MCP_TOKEN` era diverso dal token attuale salvato in WordPress.
   - Risultato: le chiamate MCP fallivano con:
     `Sorry, you are not allowed to do that.`

2. **Workspace root salvata nel plugin non aggiornata**
   - L'opzione WordPress `lcfa_connections.workspace_root` conteneva ancora una vecchia path.
   - Questo rischia di rigenerare bundle/config sbagliati anche dopo una correzione manuale lato Codex.

3. **Probe REST ricorsivo**
   - `LCFA_Local_MCP_Bridge::probe_rest_loopback()` chiamava:
     `/wp-json/lcfa/v1/mcp/status`
   - Ma `/mcp/status` calcola a sua volta lo stato del local bridge, quindi a cache fredda si crea una ricorsione HTTP.
   - Con PHP-FPM locale configurato con pochi worker, la ricorsione causa timeout:
     `cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received`

## Modifica urgente gia validata

Nel file:

`includes/class-lcfa-local-mcp-bridge.php`

modificare `probe_rest_loopback()` in modo che non chiami `/mcp/status`.

Prima:

```php
$response = wp_remote_get(rest_url('lcfa/v1/mcp/status'), [
    'timeout' => 5,
    'headers' => [
        'X-LCFA-MCP-Token' => (string) $connections['mcp_token'],
    ],
]);
```

Dopo:

```php
// Probe a lightweight authenticated route; /mcp/status would recurse into this status check.
$response = wp_remote_get(rest_url('lcfa/v1/theme/roots'), [
    'timeout' => 5,
    'headers' => [
        'X-LCFA-MCP-Token' => (string) $connections['mcp_token'],
    ],
]);
```

Motivo: `/theme/roots` e una route leggera, autenticata con lo stesso token MCP, ma non rientra nella costruzione dello stato MCP.

## Modifiche richieste per rendere Codex plug-and-play

### 1. Aggiungere un endpoint REST di diagnosi atomico

Aggiungere una route:

`GET /wp-json/lcfa/v1/mcp/health`

Questa route deve:

- validare `X-LCFA-MCP-Token`;
- non chiamare `LCFA_Local_MCP_Bridge::get_status()`;
- non eseguire probe HTTP verso se stessa;
- restituire solo dati atomici:
  - plugin attivo;
  - token valido;
  - site URL;
  - rest base;
  - ABSPATH;
  - path script MCP;
  - script leggibile;
  - tema attivo;
  - timestamp.

Esempio risposta:

```json
{
  "ok": true,
  "plugin": "livecanvas-forge-ai",
  "site_url": "http://consultala-hr.local/",
  "rest_base": "http://consultala-hr.local/wp-json/lcfa/v1/",
  "wp_root": "/Users/commander/Local Sites/consultala-hr/app/public",
  "mcp_script": "/Users/commander/Local Sites/consultala-hr/app/public/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js",
  "script_exists": true,
  "stylesheet": "picostrap5-child-base",
  "template": "picostrap5"
}
```

Poi usare questa route per i probe interni al posto di endpoint complessi.

### 2. Normalizzare automaticamente `workspace_root`

Quando il plugin rileva `site_mode=local`, deve impostare automaticamente:

```php
$connections['workspace_root'] = untrailingslashit(ABSPATH);
```

Questo deve avvenire in almeno questi casi:

- attivazione plugin;
- apertura pagina Connections;
- generazione bundle Codex;
- smoke test;
- reset connessioni;
- cambio site URL o cambio path rilevato.

Regola: se `workspace_root` non esiste piu su disco, o non contiene `wp-load.php`, va considerata stale e sostituita con `ABSPATH`.

### 3. Aggiungere una funzione di auto-sync per Codex

Implementare un servizio PHP dedicato, ad esempio:

`LCFA_Codex_Config_Manager`

Responsabilita:

- trovare `~/.codex/config.toml`;
- leggere la configurazione esistente;
- aggiornare solo la sezione `[mcp_servers.livecanvas-forge]`;
- preservare tutto il resto del file;
- creare backup prima di scrivere;
- rigenerare questi valori:
  - `command = "node"`
  - `args = ["{ABSPATH}/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js", "--transport=stdio"]`
  - `LCFA_MCP_ENDPOINT`
  - `LCFA_MCP_TOKEN`
  - `LCFA_REST_BASE`
  - `LCFA_SITE_URL`
  - `LCFA_WP_ROOT`

La scrittura deve essere opzionale e confermata dall'utente nella UI.

Se il plugin non puo scrivere il file, deve mostrare:

- config generata;
- comando `codex mcp remove livecanvas-forge`;
- comando `codex mcp add livecanvas-forge ...`;
- stato chiaro: "Manual step required".

### 4. Introdurre un fingerprint di configurazione

Calcolare un hash della configurazione attesa:

```php
$expected = [
  'script_path' => LCFA_DIR . 'mcp/bin/livecanvas-forge-mcp.js',
  'rest_base'   => rest_url('lcfa/v1/'),
  'site_url'    => home_url('/'),
  'wp_root'     => untrailingslashit(ABSPATH),
  'mcp_token'   => $connections['mcp_token'],
];

$hash = md5(wp_json_encode($expected));
```

Salvare `connection_last_bundle_hash`.

La UI deve confrontare:

- hash atteso;
- hash salvato;
- se possibile, hash letto da `~/.codex/config.toml`.

Se non combaciano, mostrare uno stato esplicito:

`Codex config is stale. Regenerate or sync the Codex MCP config.`

### 5. Migliorare lo smoke test Codex

Lo smoke test non deve limitarsi a controllare se REST risponde. Deve simulare il vero client MCP.

Da PHP o dalla UI eseguire/mostrare questi test:

1. `GET /lcfa/v1/mcp/health` con token.
2. Verifica script MCP esistente.
3. Verifica `node --version`.
4. Esecuzione locale:

```bash
LCFA_REST_BASE="..."
LCFA_MCP_TOKEN="..."
LCFA_SITE_URL="..."
LCFA_WP_ROOT="..."
node ".../mcp/bin/livecanvas-forge-mcp.js" --tool get_mcp_status --tool-args '{}' --output json
```

Il risultato atteso deve contenere:

```json
{
  "ok": true,
  "result": {
    "mcp": {
      "local_bridge": {
        "available": true
      }
    }
  }
}
```

Se fallisce con `401/rest_forbidden`, la UI deve dire:

`Token mismatch between WordPress and Codex config. Sync Codex config or rotate token and regenerate.`

Se fallisce con path inesistente:

`Codex is pointing to an old plugin path. Sync Codex config.`

### 6. Gestire rotazione token in modo sicuro

Quando l'utente ruota il token MCP:

- invalidare subito `connection_last_bundle_hash`;
- mostrare `Codex config must be regenerated`;
- aggiornare i bundle scaricabili;
- se auto-sync Codex e consentito, aggiornare `~/.codex/config.toml`;
- svuotare transient MCP:

```php
delete_transient('lcfa_local_mcp_status_' . md5(home_url('/') . '|' . LCFA_VERSION));
```

### 7. Evitare stati falsamente verdi

La UI Connections non deve mostrare "ready" solo perche:

- il plugin e attivo;
- REST e raggiungibile;
- un transient vecchio dice `available=true`.

Mostrare `ready` solo se:

- token corrente funziona su `/mcp/health`;
- script MCP esiste nel path atteso;
- `workspace_root` contiene `wp-load.php`;
- config Codex attesa e allineata o l'utente ha completato un setup manuale;
- lo smoke test MCP locale e passato almeno una volta dopo l'ultimo hash/token/path change.

### 8. Aggiungere una sezione "Repair Codex Connection"

Nella pagina Connections aggiungere un pannello:

`Repair Codex Connection`

Azioni:

- `Detect current WordPress path`
- `Sync WordPress connection settings`
- `Update Codex config`
- `Run smoke test`
- `Show exact restart instruction`

Output desiderato:

```text
WordPress root: OK
MCP token: OK
Codex config: stale/fixed
MCP script: OK
REST health: OK
Local bridge: OK
Restart required: yes/no
```

### 9. Riavvio Codex: messaggio esplicito

Dopo aver aggiornato `~/.codex/config.toml`, la UI deve sempre mostrare:

`Codex must be restarted or the current MCP server must be reloaded before the new config is used.`

Motivo: una sessione Codex gia aperta puo continuare a usare variabili d'ambiente vecchie anche se `config.toml` e stato corretto.

### 10. Test automatici da aggiungere

Aggiungere test PHP per:

- `probe_rest_loopback()` non chiama `/mcp/status`;
- `/mcp/health` non richiama `LCFA_Local_MCP_Bridge::get_status()`;
- token valido restituisce 200;
- token mancante restituisce 401;
- workspace stale viene sostituito da `ABSPATH`;
- cambio token invalida bundle hash;
- config Codex generata contiene path corrente, token corrente, rest base corrente.

Aggiungere test JS/Node per:

- `WPClient` restituisce errore leggibile su 401;
- comando `--tool get_mcp_status --output json` fallisce chiaramente se token non valido;
- comando `--tool get_mcp_status --output json` passa con token valido.

## Prompt operativo per l'implementatore

Implementa nel plugin `livecanvas-forge-ai` una procedura Codex plug-and-play robusta. Parti dai problemi reali osservati: Codex puo avere `~/.codex/config.toml` con token e path stale, WordPress puo conservare `lcfa_connections.workspace_root` obsoleto, e il local bridge aveva un probe ricorsivo perche `/mcp/status` chiamava se stesso tramite `LCFA_Local_MCP_Bridge::get_status()`. Correggi definitivamente questi casi.

Modifica `LCFA_Local_MCP_Bridge::probe_rest_loopback()` affinche usi una route leggera autenticata, non `/mcp/status`. Aggiungi una nuova route `GET /lcfa/v1/mcp/health` che validi `X-LCFA-MCP-Token` e restituisca solo segnali atomici senza invocare lo status bridge. Usa questa route per i probe.

Normalizza automaticamente `workspace_root` a `untrailingslashit(ABSPATH)` quando il sito e locale o quando la root salvata non esiste/non contiene `wp-load.php`. Fai questa normalizzazione prima di generare bundle Codex, nella pagina Connections, durante lo smoke test e dopo reset/rotazione token.

Aggiungi un manager per sincronizzare opzionalmente `~/.codex/config.toml`, aggiornando solo la sezione `[mcp_servers.livecanvas-forge]` con script path, token, REST base, site URL e WP root correnti. Crea backup prima della scrittura e mostra fallback manuale se il file non e scrivibile.

Introduci un fingerprint/hash della configurazione attesa e usalo per rilevare config stale. Dopo rotazione token o cambio path, invalida lo stato ready e richiedi sync/smoke test. La UI non deve mostrare ready se la config Codex non e allineata o se lo smoke test non e passato dopo l'ultimo cambio.

Migliora lo smoke test simulando il vero comando Node MCP con env `LCFA_REST_BASE`, `LCFA_MCP_TOKEN`, `LCFA_SITE_URL`, `LCFA_WP_ROOT` e `--tool get_mcp_status --output json`. Interpreta 401 come token mismatch, path inesistente come config stale, timeout come problema REST/PHP-FPM, e mostra messaggi riparabili.

Aggiungi una sezione UI "Repair Codex Connection" con azioni per rilevare path corrente, sincronizzare impostazioni WordPress, aggiornare config Codex, eseguire smoke test e indicare se serve riavviare Codex. Dopo ogni aggiornamento della config, comunica esplicitamente che Codex deve essere riavviato o deve ricaricare il server MCP.

Completa con test PHP e Node che coprano token valido/non valido, workspace stale, hash stale, health endpoint non ricorsivo, probe non ricorsivo, e comando MCP locale con token valido.
