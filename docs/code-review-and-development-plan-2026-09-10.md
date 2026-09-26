# AI Bridge — revisione del codice e piano di sviluppo

Data: 10 settembre 2026. Stato: analisi completata; correzioni non implementate.

## Decisione consigliata

Iniziare dai confini di sicurezza e dall'affidabilità delle scritture. Prima di ampliare la distribuzione della beta, chiudere i rilievi P1 oppure disabilitare esplicitamente il percorso interessato quando è opzionale. Non serve una riscrittura del plugin: servono interventi piccoli, verificabili e coerenti tra PHP e Node.

La revisione identifica **13 rilievi**: **8 con riproduzioni isolate** e **5 fondati sull'ispezione del codice**, con i limiti indicati per ciascuno. Seguono tre interventi trasversali di qualità. Non è una certificazione di sicurezza né una verifica integrale di ogni combinazione di hosting e coding agent.

## Perimetro ed evidenze

Repository esaminato: `livecanvas-forge-ai`. Commit locale `eb55aded1d3b31d18b336e5c2778dabe902a3c36`; il confronto con il commit remoto main `90cb6de8029473a634e183a97a6597eb16c4f7d4` non presenta differenze di contenuto. Versioni: plugin `0.2.0-beta.4`, pacchetto MCP `0.2.0-beta.5`. La differenza tra queste versioni è intenzionale e documentata, non un difetto.

Sono stati esaminati struttura e bootstrap, API REST e autorizzazioni, sessioni e OAuth, configurazioni dei client, trasporti MCP, filesystem e backup, importazione dei temi e WindPress, inventario, code di esecuzione, test e pipeline di distribuzione. La profondità maggiore è sui confini di fiducia e sulle operazioni che modificano dati.

| Verifica eseguita | Esito | Limite |
| --- | --- | --- |
| `bash scripts/test-php.sh` | 83 script passati | Test contrattuali/unitari; non 83 installazioni WordPress reali |
| `bash scripts/test-node.sh` | 12 script passati, 1 inizialmente saltato | Il test visuale opzionale è stato poi eseguito separatamente |
| `LCFA_VISUAL_E2E=1 node mcp/tests/visual-check-runtime.test.js` | Passato | Chromium, pagina sintetica, desktop e mobile; non il sito reale |
| `bash scripts/test-js.sh` | 13 script passati | Lo script esclude lo smoke test dell'editor su sito reale |
| Controllo sintattico | Passato | 62 file PHP in `includes`, entry point PHP, 17 file JS in `assets` e `mcp/src` |
| `npm audit --omit=dev --audit-level=high --json` | Nessuna vulnerabilità segnalata | Advisory delle dipendenze, non analisi del codice applicativo |
| `composer audit --locked --no-dev --format=json` | Nessun advisory o pacchetto abbandonato | Dipendenze presenti nel lockfile |
| Riproduzioni mirate | 15 controlli hanno evidenziato i comportamenti descritti | Fixture temporanee, funzioni WP simulate, credenziali fittizie, server solo su loopback |

Ambiente di esecuzione: macOS, PHP 8.5.2, Node 25.8.1, Composer 2.9.5. Non è stata rieseguita localmente tutta la matrice PHP/Node della CI.

Non sono stati modificati siti, connessioni reali, contenuti, temi o sorgenti del plugin. Nessun commit, push o rilascio. Non è stato ricostruito o ricertificato lo ZIP di distribuzione, né rieseguito il secret scan dell'intera storia Git. Le prove temporanee sono in `/private/tmp/lcfa-code-audit-FY1tvz`; possono essere eliminate dal sistema e non sono una suite permanente.

### Legenda

- **P1**: da correggere prima di ampliare la beta per il percorso interessato; può compromettere isolamento, credenziali o ripristinabilità.
- **P2**: da pianificare per affidabilità, compatibilità e manutenzione; non equivale a compromissione dimostrata.
- **Riprodotto**: comportamento osservato invocando il codice reale con fixture isolate.
- **Codice**: meccanismo verificato nel sorgente; impatto operativo da confermare negli ambienti indicati.

## 1. Confini di sicurezza

### BR-01 — P1 — I link simbolici possono superare la root del tema

**Evidenza: riprodotto, sia PHP sia Node.** Il controllo confronta il percorso testuale con la root consentita, senza risolvere il target dei link simbolici. Un file `linked.css` nel child theme, collegato a un file esterno alla cartella, viene accettato e modifica il file esterno.

Riferimenti: `includes/class-lcfa-theme-files-bridge.php:672`, `:921`, `:285`; `mcp/src/theme-files.js:669`.

L'effetto richiede un link simbolico già presente e l'accesso a un percorso operativo di scrittura. Non dimostra accesso anonimo da Internet. Restano applicabili i permessi del processo PHP/Node, ma il confine promesso dal plugin non è rispettato. Anche la lettura segue il link.

**Intervento:** introdurre una policy di percorso comune ai due runtime: canonicalizzare il target esistente o il primo antenato esistente per i file nuovi; rifiutare collegamenti che escono dalla root; verificare anche backup e restore. Considerare cambi di symlink tra verifica e apertura, senza dichiarare sicurezza assoluta basata sul solo `realpath`.

**Completato quando:** i test coprono symlink a file, directory annidate, file nuovi e root con prefisso simile. Nessun file esterno cambia; i file leciti continuano a funzionare.

### BR-02 — P1 — Gli header di sessione seguono redirect verso un'altra origine

**Evidenza: riprodotto.** `WPClient.request()` aggiunge `X-LCFA-MCP-Session` o il token legacy e usa `fetch` con redirect automatici. Una risposta 302 tra due porte locali diverse trasferisce l'header di sessione al secondo server. La prova ha usato solo un token fittizio.

Riferimento: `mcp/src/wp-client.js:455`, in particolare `:467` e `:497`.

Il rischio si manifesta quando l'endpoint autenticato restituisce un redirect verso un'origine diversa: configurazione del dominio, proxy o endpoint compromesso. Non è stata rilevata una fuga di credenziali reali.

**Intervento:** centralizzare il trasporto autenticato; gestire i redirect manualmente; bloccare cambio di origine e downgrade HTTPS→HTTP. Risolvere eventuali URL canonici prima dell'autenticazione. Applicare la stessa policy al pairing.

**Completato quando:** test con 301/302/307/308, host/porta/protocollo differenti e loop di redirect dimostrano che nessun segreto raggiunge una destinazione non autorizzata.

### BR-03 — P1 — Il trasporto HTTP/WebSocket opzionale non autentica chi lo usa

**Evidenza: riprodotto.** Il bridge restituisce `/snapshot` con HTTP 200 senza credenziali e accetta un upgrade WebSocket da un'Origin non fidata e da un percorso arbitrario. Il gestore WebSocket inoltra le richieste ai tool senza un controllo di accesso per connessione.

Riferimenti: `mcp/src/bridge-server.js:12`, `:68`, `:338`, `:395`; default in `mcp/src/config.js:1`.

**Ambito importante:** il default è `stdio`; il problema riguarda l'avvio del trasporto `bridge`. Il bind locale predefinito limita l'esposizione di rete, ma non autentica altri processi locali. La sfruttabilità da una pagina web dipende anche dalle protezioni del browser; non è stata dimostrata in questa revisione.

**Intervento:** nella prima patch disabilitare o rendere esplicitamente non disponibile questo trasporto finché non esistono autenticazione, validazione Host/Origin, percorso di upgrade fissato e limiti ai messaggi. Per mantenerlo, usare una libreria WebSocket mantenuta e una policy di autorizzazione esplicita. Non è sufficiente aggiungere CORS.

**Completato quando:** accesso senza credenziali rifiutato, Origin non ammessa rifiutata, binding non locale esplicitamente controllato, tool non autorizzati non invocabili e payload fuori limite respinti. Il percorso `stdio` resta compatibile. La specifica MCP tratta autenticazione e controllo Origin come protezioni dei trasporti HTTP; questo bridge è però un trasporto custom, non automaticamente Streamable HTTP conforme. [Riferimento ufficiale](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports).

### BR-05 — P1 — I backup PHP dei file tema sono collocati sotto uploads

**Evidenza: codice; esposizione HTTP da verificare per hosting.** I backup vengono salvati in `uploads/livecanvas-forge-ai/backups` con estensione originale e metadati affiancati. La classe non applica una protezione di accesso al relativo archivio.

Riferimento: `includes/class-lcfa-theme-files-bridge.php:705`.

Non è stato verificato se un server reale li serva o esegua: dipende da Apache/nginx e dalla configurazione dell'hosting. La posizione sotto una directory normalmente pubblica non deve essere considerata privata. Il backup WindPress applica una protezione `.htaccess` separata (`includes/class-lcfa-windpress-bridge.php:913`), non una garanzia comune ai due sistemi o a nginx.

**Intervento:** archivio privato configurabile, preferibilmente esterno alla document root; contenuti non eseguibili, permessi restrittivi e accesso tramite endpoint autenticato. Se lo storage privato non è disponibile, non dichiarare il preflight superato senza verificare un'alternativa sicura. Migrare i backup esistenti con verifica, senza cancellarli prima della copia confermata.

**Completato quando:** richieste anonime ai backup e ai metadati non restituiscono dati su Apache e nginx; il restore autenticato continua a funzionare dopo la migrazione.

## 2. Scrittura, rollback e operazioni concorrenti

### BR-04 — P1 — Il sistema di backup non garantisce sempre il ripristino

**Evidenza: riprodotto.** Sono emersi tre difetti collegati:

1. In PHP, due backup dello stesso file nello stesso secondo ricevono lo stesso percorso: il secondo sovrascrive il primo.
2. Se la creazione del backup fallisce, il codice PHP può comunque modificare il file e restituire `ok: true`. La prova ha simulato una directory backup non creabile.
3. In PHP e Node, il filtro che scarta tutti i file con suffisso `.json` scarta anche i backup reali di `theme.json`: esistono su disco ma non sono elencati. Anche la risoluzione per lettura/restore li rifiuta per estensione, come verificato nel codice.

Riferimenti: `includes/class-lcfa-theme-files-bridge.php:279`, `:712`, `:761`, `:818`; `mcp/src/theme-files.js:752`, `:829`.

**Intervento:** backup immutabili con ID univoco, manifest distinto dal payload, checksum e verifica della scrittura completa. Fermare l'apply se il backup necessario fallisce. Scrivere il target con sostituzione atomica dove supportata, preservando permessi e gestendo conflitti. Uniformare i contratti PHP/Node, compresi file nuovi e cancellazione tramite rollback.

**Completato quando:** 100 scritture ravvicinate conservano i relativi stati; un errore del backup lascia il target invariato; CSS/JSON/PHP si ripristinano correttamente; una scrittura interrotta non lascia un target parziale. Non perdere compatibilità con i backup precedenti.

### BR-06 — P1 — Dopo il cambio tema, il processo MCP può scrivere nel vecchio child

**Evidenza: riprodotto.** `ThemeFilesystem` conserva `cachedRoots` senza invalidazione. Dopo una prima operazione sul child A, il passaggio dello snapshot al child B non cambia la destinazione della scrittura successiva: il file viene creato in A.

Riferimenti: `mcp/src/theme-files.js:50`, `:95`; istanza condivisa in `mcp/src/cli.js:16`.

È rilevante per il flusso installazione/importazione tema → generazione di una nuova pagina nella stessa sessione. Inoltre il percorso locale viene costruito come `wp-content/themes`, senza supportare una content directory personalizzata (`:60`).

**Intervento:** associare la cache all'identità del sito e del tema attivo; invalidarla dopo installazione/attivazione e riverificare il target prima delle mutazioni. Separare sempre workspace agente, root WordPress e root tema, utilizzando percorsi autorevoli validati o una mappatura esplicita.

**Completato quando:** un cambio tema senza riavviare l'agente non modifica il tema precedente; un cambio inatteso ferma l'operazione e richiede una nuova preview. Test aggiuntivo con content directory personalizzata.

### BR-07 — P1 — Sessioni e job hanno aggiornamenti non atomici

**Evidenza: codice; concorrenza reale WordPress non riprodotta.** Le sessioni sono un array in un'opzione; la validazione aggiorna `last_seen_at` riscrivendo l'intero array. Anche la revoca aggiorna l'intero array. Una richiesta che ha letto lo stato prima della revoca può sovrascriverla con la propria copia obsoleta. Analogamente, due worker possono leggere lo stesso job `queued` e assegnarselo senza compare-and-swap.

Riferimenti: `includes/class-lcfa-mcp-session-manager.php:279`, `:376`; `includes/class-lcfa-settings.php:1246`, `:1274`.

Un secondo percorso esegue il comando mentre legge lo stato: GET `/command/execution`, autorizzato con `can_read`, porta il record da `queued` a `running` e chiama l'esecutore. Il record è un transient con durata di 15 minuti. Non si conclude da questo, senza ulteriori prove, che un lettore possa creare arbitrariamente un comando di scrittura: l'accodamento ha un controllo separato `can_write`.

Riferimenti: `includes/class-lcfa-rest-api.php:460`, `:1676`, `:2086`, `:2156`.

**Intervento:** storage con aggiornamenti atomici per record, revoca monotona, claim con token di proprietà e scadenza, chiavi di idempotenza per le mutazioni. Separare lettura stato ed esecuzione. Definire una migrazione delle opzioni esistenti che preservi grant, job e audit; valutare il riuso dell'infrastruttura storage già presente.

**Completato quando:** test paralleli su DB reale non fanno riapparire sessioni revocate e producono una sola esecuzione per job; un worker interrotto è recuperabile; GET stato non produce scritture applicative.

### BR-11 — P2 — Il rollback dell'importazione non è un journal durevole per ogni fase

**Evidenza: codice; arresto forzato PHP non testato.** L'importer costruisce il record di rollback in memoria e lo salva a fine import o nel `catch`. Nel frattempo modifica tema, opzioni, media e contenuti. Un'eccezione intercettata è gestita; un arresto del processo prima del salvataggio può lasciare operazioni parziali senza un record completo di recupero.

Riferimenti: `includes/class-lcfa-theme-library-importer.php:126`, `:177`, `:191`, `:273`, `:287`.

**Intervento:** journal persistente con stato precedente salvato prima delle mutazioni e checkpoint successivi, fasi idempotenti, lock per import del sito, ripresa guidata degli import interrotti. I test esistenti di failure injection restano utili, ma non sostituiscono un'interruzione del worker.

**Completato quando:** un worker terminato dopo importazione header/media/homepage lascia uno stato recuperabile; al riavvio si può riprendere o ripristinare senza duplicati. Il sistema distingue attesa build, build fallita e import interrotto.

## 3. Connessione e onboarding dei coding agent

### BR-08 — P2 — Il merge può alterare configurazioni estranee ad AI Bridge

**Evidenza: riprodotto.** Il merge JSON usa `json_decode(..., true)`: oggetti vuoti di altri server o impostazioni diventano array vuoti. Nella fixture Cursor, `sentinel.env: {}` e `settings: {}` diventano `[]`. Il merge TOML non riconosce l'intestazione `[mcp_servers.livecanvas-forge] # comment` e aggiunge una seconda definizione dello stesso server.

Riferimenti: `includes/class-lcfa-connection-artifact-writer.php:101`, `:148`.

Il commento dopo l'intestazione è valido TOML: il problema è il parser basato su regex, non il file dell'utente. [Specifica TOML](https://toml.io/en/v1.0.0#table).

**Intervento:** preservare i tipi JSON, modificando soltanto il server di Bridge; usare un parser/editor TOML strutturale che gestisca commenti e chiavi quotate. Verificare sintassi e differenza semantica prima della sostituzione. Mantenere backup, scrittura atomica e permessi `0600`; non riparare silenziosamente sezioni sconosciute.

**Completato quando:** fixture sentinella per Cursor/OpenCode e TOML conservano tutti i valori e tipi non appartenenti a Bridge; un secondo onboarding è idempotente; un file non valido resta invariato e produce un errore azionabile.

### BR-09 — P2 — Errori HTTP diversi possono causare un nuovo pairing o un risultato ambiguo

**Evidenza: riprodotto per 403 e HTML 200.** Ogni 401/403 di una sessione causa invalidazione e un nuovo tentativo. Un 403 per scope di scrittura mancante elimina quindi anche una sessione di lettura valida; nella prova il risultato diventa `pairing_pending`. Una pagina HTML con HTTP 200, tipica anche di alcuni intermediari, viene restituita come `{raw: ...}` invece di essere riconosciuta subito come risposta non valida per l'API.

Riferimenti: `mcp/src/wp-client.js:497`, `:501`, `:525`.

Nel medesimo trasporto e nel polling pairing non c'è una deadline applicativa esplicita. Ciò non significa che la libreria di rete non abbia propri timeout, ma che il prodotto non controlla un tempo di attesa e un messaggio coerenti. Inoltre `device_secret` viaggia nella query del polling (`mcp/src/session-auth.js:79`): la presenza in eventuali access log dipende dall'infrastruttura e non è stata osservata su un hosting reale.

**Intervento:** distinguere sessione scaduta/revocata, scope insufficiente, autenticazione hosting, WAF, HTML inatteso, timeout e indisponibilità. Rinnovare la sessione soltanto per i codici di autenticazione pertinenti. Aggiungere deadline configurabili e retry limitati per operazioni sicure/idempotenti. Spostare il segreto del polling fuori dall'URL con un'evoluzione compatibile del contratto e policy no-store/redazione.

**Completato quando:** 403 per permessi conserva la sessione; HTML e timeout danno una diagnosi comprensibile; retry non duplicano operazioni; URL e log di test non contengono segreti.

### BR-10 — P2 — La cache delle sessioni non distingue due workspace con lo stesso nome

**Evidenza: riprodotto il conflitto della chiave, non una contaminazione su account reali.** La chiave considera endpoint, fingerprint, etichetta progetto e agente, ma non `agentWorkspaceRoot` né il profilo di autorizzazione. Due workspace con gli altri campi uguali puntano allo stesso file di cache. Un cambio degli scope richiesti non identifica da solo una nuova approvazione.

Riferimenti: `mcp/src/session-auth.js:193`; riuso cache in `:30`.

**Intervento:** identità workspace canonica esplicita, grant associato al profilo e verifica degli scope effettivi. Migrazione della cache legacy senza perdita silenziosa delle connessioni; riuso tra progetti soltanto se è una scelta di prodotto esplicita. Rendere le scritture della cache atomiche e verificarne i permessi.

**Completato quando:** progetti omonimi non condividono o revocano inaspettatamente le sessioni; l'aumento di permessi richiede consenso; riaprire lo stesso progetto non produce pairing duplicati.

## 4. Contratti e scalabilità

### BR-12 — P2 — Gli errori operativi non sono marcati come errori MCP

**Evidenza: codice.** `tools/call` serializza anche un risultato applicativo `ok: false` in una normale risposta, senza `isError: true`. Un client che usa il segnale standard può trattare l'esecuzione come riuscita, anche se un modello riesce a capire il testo. Il gestore non ha inoltre una gestione dedicata della cancellazione e concatena l'input senza un limite applicativo esplicito.

Riferimenti: `mcp/src/mcp-stdio-server.js:23`, `:92`, `:111`.

**Intervento:** normalizzare gli esiti distinguendo errori di protocollo, errori del tool e stati interlocutori come `pairing_pending`; aggiungere cancellazione, limiti e test delle versioni dichiarate. Valutare l'SDK ufficiale con una prova circoscritta, senza imporre una migrazione non misurata. La specifica distingue espressamente gli errori di esecuzione tramite `isError`. [Specifica MCP Tools](https://modelcontextprotocol.io/specification/2025-11-25/server/tools#error-handling).

**Completato quando:** gli errori operativi reali sono riconosciuti nello stesso modo da Cursor/OpenCode e dagli altri client qualificati; cancellare un tool non blocca la sessione; un frame troppo grande non esaurisce la memoria.

### BR-13 — P2 — L'inventario tronca le collezioni senza paginazione

**Evidenza: codice.** Diverse query usano `posts_per_page => 100`, ma `get_inventory()` non espone pagina/cursore né un indicatore di troncamento per collezione. Un sito più grande può far apparire assenti contenuti esistenti. Non significa che l'accesso diretto a un ID noto sia impossibile.

Riferimento: `includes/class-lcfa-inventory.php:16`.

**Intervento:** paginazione per tipo, totale e `next_cursor`/`has_more`; ordinamento stabile; caricamento del contenuto completo solo quando richiesto. Propagare il contratto a REST, tool MCP e interfaccia.

**Completato quando:** su una fixture con oltre 100 elementi tutti sono raggiungibili senza duplicati; le risposte restano limitate; filtri e riepilogo sono coerenti.

## 5. Interventi trasversali, non difetti dimostrati

### Q-01 — Ridurre il codice monolitico e i contratti duplicati

`LCFA_Admin` ha 10.608 righe, `LCFA_Command_Deck` 4.959, `LCFA_Rest_Api` 4.486 e il registro tool Node 2.010. La dimensione non dimostra un bug, ma aumenta il costo delle modifiche e la possibilità di divergenza tra UI, REST, abilities e MCP. Anche la costruzione dei servizi è concentrata nel bootstrap.

Estrarre progressivamente servizi di connessione, gestione file/backup, esecuzione e import. Tenere piccoli i controller e generare/adattare gli schemi dei tool da contratti versionati verificabili. Introdurre PHPStan e lint JS in modo incrementale. Misurare tempo, memoria e query prima di intervenire sul caricamento lazy: nessuna regressione prestazionale è stata quantificata in questo audit.

### Q-02 — Collegare la qualifica ai flussi reali, non soltanto alle fixture

La CI contiene già matrice PHP/Node, audit dipendenze, secret scan, packaging e Chromium: sono basi da preservare. I test PHP/JS ispezionati usano prevalentemente stub e fixture. Il test Chromium usa una pagina sintetica, non installa WordPress né avvia i coding agent reali.

Aggiungere un WordPress usa-e-getta con DB reale, Apache e nginx, più una matrice manuale ripetibile dei client. Includere installazione ZIP, pairing, configurazioni sentinella, snapshot, preview/apply/rollback, tema importato, build WindPress e recupero da timeout. Ogni combinazione deve dichiarare il livello raggiunto, la data e le versioni; non promuovere Claude Code o un host a "verificato" sulla base della sola generazione del config.

Riferimenti: `.github/workflows/ci.yml`, `docs/integration-completeness-audit.md`. Miglioramento della supply chain: fissare azioni a commit verificati e verificare il checksum dei binari scaricati in CI. Non sono state rilevate manomissioni.

### Q-03 — Allineare metadati e promesse della release

Il frammento della pagina prodotto dichiara `GPL-2.0-or-later`, mentre `LICENSE.md` nega una licenza open-source, Composer dichiara `proprietary` e npm `UNLICENSED`. `LICENSE.md` parla ancora di alpha. È un'incoerenza documentale verificata, non una conclusione legale e non una verifica del testo attualmente servito dal sito.

Riferimenti: `docs/livecanvas-ai-bridge-product-fragment.html:1625`, `LICENSE.md`, `composer.json:5`, `mcp/package.json:28`.

Serve una decisione del titolare sulla licenza; poi allineare sorgenti, pacchetto e pagina. Automatizzare i controlli su versione plugin/pacchetto, URL di download, checksum e matrice di compatibilità. La versione PHP minima installabile e le versioni consigliate/testate vanno comunicate separatamente; non cambiare i requisiti minimi senza una decisione di compatibilità.

## Piano di sviluppo proposto

Le stime sono **giornate di sviluppo**, per una persona che conosce il progetto, inclusi test e review. Non sono tempi di esecuzione dell'agente né una promessa di consegna; escludono attese per account, licenze e accesso agli hosting. Le fasi sono ordinate per dipendenza. Ogni correzione parte da un test di regressione che fallisce sul codice attuale.

| Fase | Obiettivo e ambito | Criterio di uscita | Stima |
| --- | --- | --- | --- |
| 1 — Confini di sicurezza | BR-01, BR-02, BR-03, BR-05. Contenimento filesystem, redirect autenticati, disabilitazione o protezione del bridge opzionale, storage privato | Nessuna uscita dalla root o credenziale inoltrata; accessi anonimi negati; backup non pubblici | 4–6 giorni |
| 2 — Scritture recuperabili | BR-04, BR-06, BR-07, BR-11. Backup immutabili, tema attivo, revoche/claim atomici, journal import | Rollback CSS/JSON/PHP e file nuovi; una esecuzione per job; revoca permanente; recupero dopo arresto worker | 6–9 giorni |
| 3 — Onboarding robusto | BR-08, BR-09, BR-10. Merge strutturale, errori e timeout, identità progetto e scope | Secondo setup idempotente; sentinelle intatte; sessioni isolate; recovery leggibile su HTTP ostile | 3–5 giorni |
| 4 — Contratti e qualifica | BR-12, BR-13, Q-02. MCP, paginazione, WordPress E2E, ripetizione client/hosting | Nessun P1 aperto nei percorsi qualificati; installazione→snapshot→apply→rollback ripetibile | 3–5 giorni |
| 5 — Manutenzione e release | Prima tranche Q-01 e Q-03. Estrarre i servizi già toccati, controlli statici incrementali, metadati coerenti | Contratti invariati; documentazione coerente; checklist e artefatti verificati | 2–4 giorni |

**Totale indicativo: 18–29 giornate**, circa 4–6 settimane di lavoro per una persona. La rifattorizzazione completa dei monoliti non è inclusa: questa stima copre solo le estrazioni necessarie. Qualifiche hosting aggiuntive e una migrazione completa all'SDK MCP richiedono una stima separata. La protezione completa del trasporto HTTP/WebSocket può essere posticipata mantenendolo disabilitato.

### Come suddividere il lavoro senza una grande riscrittura

1. Preparare test permanenti per i casi riprodotti, una PR per confine funzionale; mantenere le nuove suite inizialmente focalizzate.
2. Correggere PHP e Node insieme quando condividono lo stesso contratto, soprattutto file/backup ed errori.
3. Versionare storage e formati persistiti; provare aggiornamento e recupero su una copia con sessioni, configurazioni e backup preesistenti.
4. Riqualificare lo ZIP candidato, non soltanto il working tree. Separare release di sicurezza da ristrutturazioni ampie.
5. Aggiornare la matrice pubblica soltanto con evidenze completate; pubblicazione e modifica dei siti restano azioni da autorizzare separatamente.

## Flusso da usare come prova finale dell'onboarding

| Momento | Comportamento richiesto |
| --- | --- |
| In WordPress | Mostrare sito, workspace e root WP distinti; scelta chiara dei permessi; preflight hosting e backup |
| In WordPress → agente | Configurazione diretta quando sicura e scrivibile; altrimenti un solo setup prompt specifico per client, senza segreti statici nel progetto |
| Nel coding agent | Caricamento del server, consenso richiesto dal client, pairing, handoff e primo snapshot; nessuna sovrascrittura di server estranei |
| Di nuovo in WordPress | Stato corrispondente a quanto verificato; distinguere "Connected" da capacità di scrittura e rollback effettivamente testate |
| Test end-to-end | Preview, file temporaneo nel child, apply e rollback verificato; fixture rimosse e contenuti estranei invariati |

La prova di scrittura deve essere chiaramente autorizzata; un collegamento intenzionalmente read-only non deve restare bloccato perché non può superare un test di scrittura. Per il flusso "configura e costruisci", lo stato pienamente pronto richiede anche la verifica della capacità di recupero.

## Criteri per il prossimo candidato beta

1. BR-01–BR-07 risolti o percorso opzionale disabilitato, con prove allegate; per BR-05 e BR-07 servono anche hosting/DB reali.
2. Migrazione da beta attuale senza perdita di connessioni, configurazioni estranee e backup precedenti; release npm e plugin coerenti con la mappa versioni prevista.
3. Test di importazione Asteria Search/Picowind/WindPress e del percorso Picostrap dichiarato, inclusi CSS pronto, cambio tema e interruzione/ripresa.
4. Cursor e OpenCode qualificati in locale e remoto; altri client etichettati secondo le prove effettivamente disponibili, senza confondere limite dell'account/modello con guasto del bridge.
5. Pacchetto verificato, advisory e secret scan superati, download e licenza coerenti; report con versioni, casi falliti e limiti residui.

**Prima attività implementativa proposta:** trasformare BR-01 in test di regressione PHP/Node e chiudere il controllo dei percorsi. È il primo intervento della Fase 1; non è stato applicato durante questa revisione.
