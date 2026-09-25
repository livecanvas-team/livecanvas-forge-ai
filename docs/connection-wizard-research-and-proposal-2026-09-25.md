# Connessione Forge AI: ricerca e proposta di semplificazione

Data: 25 settembre 2026. Stato: proposta, senza modifiche al plugin.

Aggiornamento di perimetro: la proposta limitata a Codex/OpenCode è ampliata dal [piano unico per tutti gli agenti](./unified-agent-connection-plan-2026-09-25.md), con inventario completo Novamira, verifica dei template e sequenza di consegna. Questo documento conserva la ricerca iniziale sui progetti WordPress.

## Raccomandazione

Unificare Codex e OpenCode in una sola schermata di connessione. L’utente sceglie il client, se non lo ha già scelto nel setup, copia un’istruzione nel progetto e autorizza l’accesso. Forge rileva la connessione e completa la verifica automaticamente.

La scelta del trasporto, la generazione della configurazione e il test sono responsabilità del software. Nel percorso principale devono restare visibili il sito, il client, l’azione corrente e il suo esito. Conserviamo il linguaggio visivo esistente; la semplificazione riguarda soprattutto il comportamento.

Questa proposta deriva dall’ispezione del codice Forge, del codice Novamira installato su builderius.local, di uno screenshot già presente nel repository e di documentazione primaria dei progetti esterni. Non ho eseguito nuove connessioni, installato i concorrenti o misurato i tempi di completamento degli utenti. Le caratteristiche esterne indicate come documentate non costituiscono test di interoperabilità con Forge.

## Perché oggi il percorso pesa

| Evidenza nel prodotto | Effetto sull’utente | Cambiamento proposto |
| --- | --- | --- |
| Codex ha un percorso rapido dedicato; OpenCode si trova in “Other coding agents” e passa nel wizard generico. | Due esperienze diverse per lo stesso compito. | Stessa schermata e stessi stati per entrambi. |
| Il wizard generico prevede scelta client, modalità, conferma dati, generazione e test. OpenCode locale salta la modalità ma conserva quattro fasi prima di Ready. | Diverse conferme prima di arrivare al collegamento. | Selezione client direttamente nella schermata, configurazione già preparata. |
| “Confirm connection details” dichiara che la connessione è già stata rilevata e chiede comunque conferma. | Un passaggio che non risolve una decisione. | Mostrare nome e URL del sito in testata. |
| Nel percorso Codex `remote` significa “Direct Mode”, utilizzabile anche su siti locali; nel generico local/remote descrive dove gira WordPress. | La stessa scelta cambia significato. | Distinguere ambiente, trasporto e accesso ai file nel modello interno. Nessun selettore tecnico nel percorso principale. |
| Un profilo locale può selezionare il runtime locale avanzato, mentre le istruzioni raccomandano Direct Mode anche sul locale. | La schermata può invitare a correggere la propria selezione iniziale. | Il percorso ordinario viene selezionato automaticamente. |
| Il percorso affianca spiegazioni, modalità, badge, guide, configurazione manuale, sessioni e diagnostica. | Il pulsante utile arriva dopo molto testo; anche i pannelli chiusi richiedono attenzione. | Un pannello operativo e un solo collegamento “Aiuto e opzioni avanzate”. |
| Nel pairing si richiede handoff, approvazione, nuovo handoff e smoke test manuale. | Ritorni fra applicazione e WordPress, con possibilità di perdere il punto. | Autorizzazione contestuale, ripresa del collegamento e verifica automatica. |

Riferimenti nel codice: [selezione dei due percorsi](../includes/class-lcfa-admin.php#L4044), [percorso rapido Codex](../includes/class-lcfa-admin.php#L4306), [fasi del wizard](../includes/class-lcfa-connection-onboarding.php#L114), [conferma di dati già rilevati](../includes/class-lcfa-admin.php#L5669), [selettore modalità](../includes/class-lcfa-admin.php#L4852), [OpenCode fra le opzioni secondarie](../includes/class-lcfa-admin.php#L5147).

Lo [screenshot già nel repository](./screenshots/connect-codex-restart-required-before.jpg) illustra bene il problema: nello stato di riavvio, la prima schermata è occupata da modalità, stato e istruzioni, mentre il prompt da copiare si trova più in basso. È una testimonianza della versione fotografata, non una nuova verifica dell’installazione corrente.

## Progetti confrontati

| Progetto e fonte | Collegamento documentato | Spunto per Forge / limite del confronto |
| --- | --- | --- |
| [Novamira](https://github.com/use-novamira/novamira), [quick start](https://novamira.ai/quickstart/) | Prompt copiato da WordPress; l’agente prepara la configurazione. Il plugin supporta OAuth e altri percorsi. | Dare una sola istruzione pronta per il client. Il codice locale attuale include ancora scelta del metodo e numerose alternative: non è un esempio di assenza totale di scelte. |
| [Novamira CLI](https://github.com/use-novamira/novamira-cli) | Client REST con login OAuth, controlli di compatibilità e verifica prima del salvataggio del grant; device authorization per ambienti senza browser sullo stesso host. | Un componente di setup può coordinare autenticazione e verifica. La CLI è un percorso REST distinto dall’MCP: adottarla come modello completo allargherebbe il progetto. |
| [Tropk MCP for WordPress](https://github.com/tropk-ai/mcp-for-wordpress) | Wizard dichiarato di tre passaggi, scelta assistente, endpoint unico e autorizzazione OAuth. | Un endpoint e istruzioni specifiche per client. Il README concentra gli esempi su connettori hosted: non dimostra da solo l’esperienza Codex/OpenCode. |
| [Agent Abilities for MCP](https://github.com/unaibamir/agent-abilities-for-mcp) | Scheda Connection con endpoint e configurazioni generate; OAuth proposto come percorso semplice, password applicativa come alternativa. | Separare la connessione dalla selezione delle abilità. Il controllo di raggiungibilità dal server non prova che il client dell’utente sia collegato. |
| [Royal MCP](https://github.com/royalplugins/royal-mcp) | Endpoint Streamable HTTP proprio, OAuth per client compatibili e istruzioni differenziate. Integrazione MCP Adapter opzionale. | Separare disponibilità della connessione e integrazione con le Abilities; utile anche il controllo di salute dedicato. Il supporto generico MCP non equivale a una certificazione Codex/OpenCode. |
| [Stifli Flex MCP](https://github.com/estebanstifli/stifli-flex-mcp) | Copia dell’URL SSE e autorizzazione OAuth; funzionalità aggiuntive modulari. | Tenere componenti opzionali fuori dalla connessione iniziale. Per Forge la scelta del trasporto va verificata sui client target, senza assumere equivalenza fra SSE e Streamable HTTP. |
| [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) + [Automattic MCP WordPress Remote](https://github.com/Automattic/mcp-wordpress-remote) | L’adapter espone abilità via HTTP/STDIO; il proxy gestisce connessione e diversi metodi di autenticazione. | Riferimento architetturale. Sono componenti, non un wizard pronto. OAuth del client richiede anche un server OAuth disponibile sul sito. |
| [WPPilot](https://github.com/wppilot-labs/wordpress-mcp-elementor-wppilot) | Connessione per client con OAuth, password applicativa o token; matrice separata delle compatibilità. Distingue client desktop, CLI e cloud. | Tenere la matrice di compatibilità fuori dal percorso ordinario, ma usarla per generare l'istruzione corretta. Il README non sostituisce test Forge sugli stessi client. |
| [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp) | Collega coding agent tramite Abilities API e MCP Adapter incluso; dichiara accesso operativo a shell, PHP e filesystem in ambienti di sviluppo fidati. | Utile per confrontare packaging e accesso operativo. Le capacità illimitate dichiarate dal progetto non sono un requisito per semplificare il wizard Forge. |

Un ulteriore riferimento UX è [WordPress.com MCP](https://developer.wordpress.com/docs/mcp/): documenta per Codex l’aggiunta di un URL e l’autorizzazione nel browser. È un servizio con requisiti propri di account e piano, non un plugin da inserire in Forge. Mostra però quanto possa essere breve il percorso quando il client gestisce nativamente OAuth.

Il vecchio [Automattic/wordpress-mcp](https://github.com/Automattic/wordpress-mcp) indirizza lo sviluppo verso WordPress/mcp-adapter; non lo sceglierei come base nuova.

## Che cosa possiamo riutilizzare subito

Forge dispone già di server OAuth, discovery, registrazione client, token rinnovabili e revoca. La UI mostra già le app OAuth collegate. Non serve progettare nuovamente queste funzioni per accorciare il wizard.

Ci sono però tre distinzioni importanti rispetto alla prima analisi:

1. Nel codice attuale OAuth diretto viene scelto soltanto per Codex, per il sito corrente, quando sono soddisfatti i requisiti di HTTPS pubblico, runtime OAuth e MCP Adapter. [Selezione della strategia](../includes/class-lcfa-admin.php#L1833), [requisiti](../includes/class-lcfa-oauth-server.php#L488).
2. OpenCode viene configurato sempre come server `type: local`. La sua [documentazione ufficiale](https://opencode.ai/docs/mcp-servers/) supporta invece server remoti con OAuth automatico e registrazione dinamica. Estendere Forge a questo percorso richiede modifiche e una prova reale: non basta cambiare l’etichetta. [Generatore OpenCode](../includes/class-lcfa-connection-bundle-builder.php#L592).
3. Anche Forge OAuth usa lo scope `mcp`; gli scope granulari descritti nella prima analisi appartengono alle sessioni di pairing. Restano distinti i controlli delle abilità e delle scritture. La semplificazione del wizard deve riutilizzare le policy correnti e mostrare correttamente ciò che viene autorizzato. [Repository scope](../includes/class-lcfa-oauth-repositories.php#L41), [gestione grant già esistente](../includes/class-lcfa-admin.php#L4510).

La [documentazione ufficiale Codex](https://learn.chatgpt.com/docs/extend/mcp?surface=cli) conferma HTTP con OAuth e configurazione per progetto, caricata nei progetti trusted. Quindi la configurazione va preparata dal lato dell’agente nel progetto corretto. WordPress remoto non può conoscere automaticamente la cartella aperta sul computer dell’utente.

Novamira locale, inoltre, non equivale sempre a “solo URL senza processo locale”: nel codice installato (`novamira/includes/connect-methods.php:635`, installed plugin source) i siti locali usano anche un bridge, e OpenCode compare fra i client indirizzati al bridge nel ramo pubblico. Il supporto nativo documentato da OpenCode merita una verifica autonoma per Forge.

## Nuova schermata proposta

Modalità d’uso: operativa. Pubblico: chi ha installato Forge e vuole iniziare a lavorare nel proprio agente senza conoscere MCP.

```text
Collega il tuo assistente
Sito: example.com

[ Codex ]  [ OpenCode ]  Altri…

Apri il progetto in Codex e incolla le istruzioni.
Codex preparerà il collegamento a questo sito.

[ Copia istruzioni per Codex ]

Aiuto e opzioni avanzate
```

Se il client è già stato scelto nel setup, è preselezionato. Il cambio client aggiorna immediatamente le istruzioni; non richiede “Avanti”. Nome e URL del sito restano visibili in ogni stato. Il testo completo del prompt è consultabile, ma non deve occupare la schermata iniziale.

L’istruzione copiata contiene client, identità del sito, configurazione generata per quel caso e verifica richiesta. L’agente prepara solo il collegamento nel progetto corrente, conserva le configurazioni esistenti e avvia il metodo di autorizzazione previsto. L’utente non deve scrivere JSON, TOML, percorsi o comandi di handoff.

### Tre momenti, una sola pagina

| Stato visibile | Contenuto e azione | Condizione di avanzamento |
| --- | --- | --- |
| Collega | Sito, client, “Copia istruzioni”. Dopo la copia: “Incolla nel progetto aperto”. | Il client avvia davvero il collegamento; la sola copia non basta. |
| Autorizza | Richiesta OAuth nel browser oppure approvazione del codice di pairing. Mostrare client, sito e accesso effettivo. | Consenso dell’utente e autenticazione completata. |
| Collegato | Esito della verifica e invito a iniziare nel client scelto. | Handoff autenticato del collegamento corrente e controlli pertinenti superati. |

“Verifica in corso” è uno stato automatico fra autorizzazione e completamento, non una quarta pagina. Il riavvio o ricaricamento compare solo quando necessario per il client e il metodo adottato; non va promesso che tutti i client lo possano evitare.

Durante il pairing la richiesta pertinente occupa il pannello principale. La lista di tutte le sessioni, lo storico e le revoche vivono in “Gestisci connessioni”, raggiungibile dopo il completamento e dalle opzioni avanzate.

Desktop e mobile usano lo stesso ordine. Etichette esplicite, navigazione da tastiera, focus stabile e aggiornamenti di stato accessibili; il cambiamento non deve essere indicato soltanto dal colore. Dopo il successo il wizard si riduce a una riga di stato e all’azione per iniziare a lavorare.

## Che cosa scompare dal percorso principale

- Scelta Local / Remote / Direct Mode / Local runtime.
- Conferma separata dell’URL già noto e del riepilogo tecnico.
- Pulsante per generare un bundle prima di poterlo copiare.
- Scelta fra scrittura diretta, download, script e copia della configurazione.
- Cartella del progetto, REST base, fingerprint, versione del pacchetto e variabili d’ambiente.
- Smoke test manuale come passaggio obbligatorio.
- Guide estese, tutte le sessioni, strumenti di riparazione e opzioni di build.

Le opzioni restano disponibili nella sezione avanzata o nell’errore pertinente. L’autorizzazione rimane esplicita; le preferenze di accesso già scelte nel setup vengono riutilizzate. Questa schermata non deve chiedere nuovamente framework, tema o tipo di sito.

## Selezione automatica e verifica

| Caso | Percorso proposto | Che cosa vede l’utente |
| --- | --- | --- |
| Sito HTTPS pubblico, endpoint MCP e OAuth disponibili, client compatibile | HTTP con OAuth nativo, per Codex e OpenCode dopo verifica di interoperabilità. | Copia istruzioni → autorizza → collegato. |
| Sito locale o ambiente che oggi non soddisfa i requisiti OAuth Forge | Bridge esistente con pairing, predisposto automaticamente. | Stessa schermata; approvazione del codice quando arriva. |
| Client eseguito su un host che non raggiunge il sito locale | Errore di raggiungibilità verificato dal client. | Spiegazione specifica e soluzione: usare l’host corretto o rendere raggiungibile il sito. |
| Necessità di compilazione o strumenti sul filesystem locale | Runtime avanzato attivabile quando serve. | Opzione separata dopo il collegamento iniziale. |
| Endpoint OAuth previsto ma non funzionante | Diagnostica contestuale e tentativo recuperabile. | Una causa e una prossima azione, senza cambiare silenziosamente configurazione e grant. |

Rilevare un hostname `.local` è un indizio; la raggiungibilità dal computer dell’agente si verifica da quel computer. HTTP locale, HTTPS con certificati locali, container e shell remote vanno trattati come casi distinti nella validazione tecnica. Un client HTTP nativo non rende automaticamente raggiungibile un sito privato.

Nella prima consegna è ragionevole usare il bridge se manca l’adapter, evitando una scelta aggiuntiva durante la connessione. Se vogliamo eliminare anche questa dipendenza dal percorso ordinario, occorre una decisione separata sul packaging dell’adapter e sulle compatibilità. Non è un prerequisito per snellire subito la UI.

La verifica automatica deve correlare il tentativo corrente a sito, client, progetto/sessione e configurazione. Un grant vecchio, una sessione di un altro client o una chiamata di salute dal server non devono completare il wizard corrente. Nel codice attuale il controllo OAuth considera un grant attivo, mentre il pairing registra l’handoff e torna allo smoke test: prima di automatizzare Ready va resa coerente questa prova per entrambi i trasporti. [Tester](../includes/class-lcfa-connection-tester.php#L195), [registrazione handoff](../includes/class-lcfa-mcp-session-manager.php#L639).

La pagina può leggere uno stato leggero mentre è aperta e fermarsi al successo, all’errore o alla scadenza. I controlli costosi si eseguono dopo un evento significativo, non ad ogni aggiornamento. Un’assenza temporanea del client deve apparire come attesa, con possibilità di riprendere, anziché come generico errore di configurazione.

## Ordine di realizzazione consigliato

1. **Unificare la schermata.** Riutilizzare le preferenze esistenti; promuovere OpenCode accanto a Codex; generare direttamente le istruzioni; ridurre il pannello a una sola azione. Mantenere validi i collegamenti già funzionanti.
2. **Automatizzare completamento e recupero.** Introdurre stato del tentativo, handoff correlato e verifica automatica. Nel fallback, coordinare richiesta, apertura della pagina di approvazione e recupero della sessione, così l’utente non deve richiedere due handoff manuali. Un eventuale helper di setup è lavoro nuovo, non un comando già disponibile nel pacchetto attuale.
3. **Portare OpenCode su OAuth nativo nei casi compatibili.** Aggiungere il formato remoto al generatore, verificare discovery, login, refresh, revoca e ritorno al progetto. Adeguare gli stati e i testi oggi specifici di Codex.
4. **Misurare e ottimizzare l’avvio locale.** Il [pacchetto MCP attuale](../mcp/package.json#L19) include Playwright e Sass. Valutare una distribuzione leggera per la sola connessione e caricamento degli strumenti avanzati quando richiesti. L’impatto sui tempi di prima installazione va misurato: non è stato dimostrato in questa analisi.

Le aree principali sono admin/presenter/onboarding, generatore bundle, tester e stato sessioni; il runtime interviene per il coordinamento del pairing. Non serve riscrivere le abilità LiveCanvas per realizzare questa proposta.

## Criteri per approvare il risultato

- Stesso percorso per Codex e OpenCode; nessuna scelta tecnica obbligatoria nel caso ordinario.
- Al massimo una scelta di client, se non già effettuata, oltre alle azioni necessarie di copia/incolla e autorizzazione.
- Un pulsante principale e il sito visibile senza scorrere, anche a 390 px di larghezza.
- Nessun test manuale obbligatorio; Ready segue una prova del client corrente.
- Configurazioni preesistenti conservate; rientro nella pagina e ripresa dopo interruzione senza ricominciare.
- Verifica su installazione pulita e già configurata, per entrambi i client, con sito pubblico e locale; includere token scaduto, sito non raggiungibile, progetto non trusted e configurazione da ricaricare.
- Confronto osservato con utenti: tempo fino al primo handoff riuscito, completamento senza assistenza, cambi di applicazione, errori e aperture dell’aiuto. Separare attesa di download/autenticazione dal tempo impiegato a capire la UI.

Un obiettivo iniziale da validare può essere completare il percorso ordinario entro due minuti con client e prerequisiti già installati. È un obiettivo di progetto, non una misura ottenuta né una promessa per il primo download.

La priorità proposta è eliminare subito conferme ridondanti e divergenza fra i due client, poi automatizzare la prova di connessione e sfruttare OAuth nativo dove è già sostenibile.

Nota di manutenzione del contesto di design: Impeccable segnala che PRODUCT.md usa uno schema precedente e conserva il campo dismesso “Register”. Un eventuale aggiornamento con `impeccable init` può rimuoverlo e riallineare il documento; non è necessario per questa proposta e non è stato eseguito.
