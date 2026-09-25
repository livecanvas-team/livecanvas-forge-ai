# Forge AI: piano di connessione unico per tutti gli agenti

Data: 25 settembre 2026. Stato: piano di ricerca e proposta. Non è una dichiarazione di compatibilità della working copy né una release pronta.

Nell'ultima verifica il repository contiene già una nuova schermata, un registro condiviso, un installer e il collegamento fra tentativo, consenso e verifica. Sono modifiche in corso, descritte nello [stato di implementazione](./unified-connection-implementation-status.md). Questa revisione del piano lascia quei cambiamenti intatti. La presenza di una voce nel registro e il passaggio dei test automatici non significano che il relativo client abbia completato una prova reale.

Questo documento amplia la [prima ricerca sul wizard](./connection-wizard-research-and-proposal-2026-09-25.md). Il perimetro comprende tutti i client già dichiarati da Forge e un percorso di aggiunta del catalogo Novamira.

## Decisione proposta

Usare un solo flusso di connessione per Codex, OpenCode, Claude Code, Cursor e Claude Desktop, poi estenderlo agli altri agenti attraverso un registro di compatibilità. Ogni client usa gli stessi stati e la stessa prova finale di collegamento. La configurazione tecnica cambia sotto questa interfaccia.

Per i coding agent con terminale, l’azione ordinaria sarà «Copia istruzioni»: l’agente esegue un helper Forge che prepara la connessione e guida l’autorizzazione. Per le applicazioni senza terminale disponibile, la stessa schermata propone il pulsante di installazione o le istruzioni minime del connettore. Claude Desktop Chat non può essere trattato come Claude Code.

Per raggiungere anche gli agenti che Novamira aggiunge attraverso le skill, il piano prevede una seconda consegna: completare il percorso CLI Forge con installazione della skill e gestione del login. Forge ha già una modalità operativa `--tool`, che invoca lo stesso registro degli strumenti usato da MCP. Si parte da questa base. L’helper di installazione MCP e l’esecuzione di operazioni WordPress da terminale restano responsabilità diverse; non occorre costruire un secondo backend.

## Che cosa dichiara e supporta Forge oggi

La [documentazione pubblica Forge](https://livecanvas.com/bridge-doc/) e il [README locale](../README.md#L74) distinguono questi cinque client. I test indicati sono quelli già documentati dal progetto, non nuovi test eseguiti durante questa ricerca.

| Client dichiarato | Implementazione attuale | Qualifica documentata |
| --- | --- | --- |
| Codex | Percorso rapido dedicato; configurazione progetto TOML; OAuth diretto nelle condizioni previste o bridge con pairing | Test locali completi con MCP 0.2.0-beta.5 |
| OpenCode | Wizard generico; `opencode.json`; bridge STDIO anche quando il client potrebbe usare HTTP | Test locali completi con MCP 0.2.0-beta.5 |
| Cursor | Wizard generico; `.cursor/mcp.json`; bridge | Test locali completi con MCP 0.2.0-beta.5 |
| Claude Desktop | Ramo `claude` con destinazione `desktop_app`; configurazione dell’app | Test locali completi con versione Free e MCP 0.2.0-beta.5 |
| Claude Code | Ramo `claude` con destinazione CLI; comando e configurazione di progetto | Generazione e preview; manca la qualifica autenticata completa |
| MCP generico | Configurazione di base per altri client | Nessuna promessa di compatibilità con ogni prodotto |

I test completi citati comprendono handoff, snapshot, preview, scrittura e rollback. Non dimostrano automaticamente OAuth pubblico, tutti i sistemi operativi o le versioni future.

Il limite non è soltanto grafico. La versione di partenza ripete le chiavi ammesse in settings, REST, sessioni, abilità e JavaScript. La working copy ora centralizza diverse parti nel [registro](../includes/class-lcfa-agent-registry.php): anche il generatore dei bundle normalizza l'identità tramite il registro e non converte più ogni client sconosciuto in Codex. Restano liste e rami legacy nel setup e nell'amministrazione, oltre a formati generici nel vecchio generatore. Occorre chiudere questa migrazione prima di dichiarare nuovi client compatibili.

## Che cosa fa Novamira nella pagina indicata

Ho ispezionato la pagina autenticata `builderius.local`, i percorsi Codex CLI, OpenCode, Cursor, Claude Desktop, ChatGPT.com e Gemini CLI, e i sorgenti della versione installata 1.12.5. Nell’ultima ispezione le AI Abilities erano già attive. Ho cambiato solo le selezioni del configuratore: nessun salvataggio di permessi, installazione, creazione di credenziali o autorizzazione. Il nuovo controllo di Codex CLI conferma che la selezione dell'app non sceglie da sola il metodo: occorre ancora premere «Novamira CLI». Forge può eliminare questa decisione ordinaria, mantenendo le alternative nell'aiuto.

La pagina ha quattro sezioni: abilitazione delle abilità, scelta dell’app, scelta del metodo e istruzioni. Quindi Novamira conserva diverse scelte. Il vantaggio più utile per Forge è il prompt che trasferisce all’agente il lavoro di installazione e diagnosi.

| Percorso osservato | Comportamento |
| --- | --- |
| OpenCode e Cursor | Propone CLI come consigliata, OAuth e password applicativa come alternative. Il prompt cambia il nome dell’agente e la destinazione della skill. |
| CLI | Il prompt fa scegliere all’agente l’installer per la shell corrente, poi avvia login, eventuale device flow e `doctor --json`. I comandi manuali sono chiusi in un pannello secondario. |
| Claude Desktop, sito locale | Nessun percorso CLI. Mostra il file dell’app, il JSON con `mcp-remote`, poi riavvio e autorizzazione. |
| ChatGPT.com, sito locale | Avverte che non esiste un metodo funzionante per questo sito, perché il servizio cloud non può raggiungerlo. |
| Gemini CLI | Nel catalogo esteso; mostra direttamente la CLI, unico metodo configurato da Novamira per questa voce. |

Il conteggio dai manifesti locali è: 20 client MCP, 72 destinazioni skill/CLI, 12 voci in comune dopo la normalizzazione degli alias. Il selettore contiene quindi 80 voci distinte, di cui 60 aggiunte solo attraverso il catalogo CLI.

Fonte: catalogo MCP e unione del selettore (`novamira/includes/connect-page.php:484`, installed plugin source), destinazioni della CLI (`novamira/includes/connect-methods.php:27`, installed plugin source). «Presente nel catalogo» non prova che sia stato eseguito un test completo con ciascun client.

Il [repository Novamira CLI](https://github.com/use-novamira/novamira-cli) conferma che questa CLI usa un ingresso REST distinto dall’MCP, per agenti capaci di eseguire comandi. Per Forge, replicare questa copertura richiede rendere installabile e documentato il percorso CLI esistente; aggiungere 80 nomi al generatore MCP non sarebbe sufficiente.

## Esperienza utente proposta

La schermata conserva lo stile operativo di Forge. Il selettore è unico, ricercabile, con tutti i cinque client attuali allo stesso livello. Il client scelto nel setup viene riutilizzato; gli altri sono disponibili con la ricerca. Il browser non può rilevare con affidabilità quali app sono installate sul computer.

```text
Collega il tuo assistente
Sito: builderius.local

Assistente: [ Cursor                         ▾ ]

Apri il progetto in Cursor e incolla le istruzioni.
Cursor preparerà il collegamento a questo sito.

[ Copia istruzioni ]

Mostra istruzioni · Aiuto e opzioni avanzate
```

Il percorso ha esattamente tre momenti visibili:

1. Collega: una scelta di client, se manca, e una sola azione pertinente.
2. Autorizza: consenso con sito, client e accesso effettivamente richiesto. Il software riprende dopo l’approvazione.
3. Collegato: prova autenticata dal client corrente e breve risultato utile, per esempio il tema rilevato. Nessuna modifica al sito come test di onboarding.

La verifica è automatica. «In attesa dell’agente», «Autorizzazione richiesta» e «Verifica in corso» sono stati dello stesso pannello. Riavvio, trust del progetto o approvazione di un comando compaiono quando richiesti dall’app. Non promettiamo due clic totali: alcune conferme appartengono ai client e devono restare.

Per Claude Desktop il primo stato contiene l’azione di installazione disponibile, oppure una breve istruzione di configurazione. Non mostra un prompt che presuppone una shell già accessibile. Per i client cloud propone il connettore solo quando il sito è raggiungibile e il metodo è compatibile.

Dal percorso principale vengono rimossi Local/Remote, Direct Mode, scelta del runtime, conferma dell’URL già noto, generazione separata del bundle, cartella del progetto, selezione del metodo di autenticazione, JSON/TOML e smoke test manuale. Questi dettagli rimangono nella diagnostica o nelle opzioni avanzate.

Accessibilità: selettore utilizzabile da tastiera, etichette testuali degli stati, focus stabile durante gli aggiornamenti, annunci accessibili senza rumore continuo, disposizione a colonna su mobile e a zoom 200%.

### Inizializzazione del plugin

Proporrei «Collega il tuo assistente» come primo compito dopo l’attivazione. Forge rileva sito e ambiente; il consenso di accesso resta esplicito. Framework, tema desiderato, brief e configurazione delle build possono essere completati quando inizia il lavoro che li richiede.

Occorre separare `connection_ready` da `project_setup_complete`: un agente può essere collegato senza che il progetto sia pronto per ogni operazione di build. La proposta iniziale prevedeva accesso in lettura. Il successivo obiettivo di implementazione richiede il massimo accesso operativo: il piano aggiornato prevede quindi Full Access per la nuova connessione, con consenso esplicito e limiti dati dai permessi WordPress dell'utente. Le connessioni precedenti e le policy globali conservano i loro permessi. La prova iniziale resta una lettura, senza modifiche al sito. Non si disattivano TLS, conferme dell'agente, controlli di sito o audit per ridurre i passaggi.

## Catalogo da portare in Forge

Nella tabella, «candidato» significa formato o capacità documentata, da implementare e qualificare con Forge. Nessuna delle nuove integrazioni viene considerata testata da questa ricerca.

| Voce MCP di Novamira | Collocazione nel piano Forge | Adattamento richiesto |
| --- | --- | --- |
| Claude Code | Consegna 1, già dichiarato | Separarlo da Desktop; completare qualifica autenticata; configurazione progetto e login supportato dalla versione |
| Claude Desktop | Consegna 1, già dichiarato | Percorso app senza shell; mantenere il bridge locale qualificato; valutare separatamente connettore remoto |
| Claude.ai | Consegna 3, connettori cloud | OAuth HTTP pubblico; disponibilità del connettore e policy account da verificare |
| ChatGPT.com | Consegna 3, connettori cloud | Adattatore connettore dedicato; nessuna equivalenza con Codex; requisiti account e endpoint da qualificare |
| Codex in ChatGPT Desktop | Famiglia Codex, consegna 1 | Variante app nel registro; verificare percorso UI e caricamento configurazione per versione |
| Grok.com | Consegna 3, connettori cloud | Studio e test del connettore ufficiale prima di esporlo come supportato |
| Grok Bot | Consegna 3, connettori cloud | Voce distinta, requisiti e autorizzazioni da verificare |
| Codex CLI | Famiglia Codex, consegna 1 | Variante CLI con configurazione progetto; preservare il trust |
| Antigravity | Consegna 2 | Adattatore `mcpServers`, remoto `serverUrl`; distinguere superfici e versioni |
| Antigravity CLI | Consegna 2 | Stessa famiglia di schema, gestione CLI e skill distinta |
| Cursor | Consegna 1, già dichiarato | Prompt/helper; install link solo dove preserva lo scope richiesto; niente confusione con Cloud Agents |
| VS Code | Consegna 2 | Copilot in VS Code: `.vscode/mcp.json` oppure formato portabile verificato; trust e host remoto |
| GitHub Copilot | Consegna 2, da disambiguare | Varianti VS Code e CLI; Cloud agent separato, con vincoli diversi |
| Windsurf | Consegna 2 | Rilevare versione/famiglia: i documenti attuali reindirizzano a Devin Desktop e distinguono Cascade legacy |
| Cline | Consegna 2 | Distinguere estensione e CLI; percorsi diversi; STDIO iniziale se OAuth non qualificato |
| Roo Code | Consegna 2 | `.roo/mcp.json`, preservare le autorizzazioni; qualifica bridge e reload |
| Amazon Q | Consegna 2 | Distinguere IDE/CLI e formato corrente/legacy, senza assumere un solo percorso |
| Zed | Consegna 2 | Schema `context_servers`; OAuth remoto candidato, bridge locale come alternativa |
| Kilo Code | Consegna 2 | Schema corrente `mcp` in `kilo.json/jsonc`; gestire separatamente configurazioni precedenti |
| OpenCode | Consegna 1, già dichiarato | Gestire JSON e JSONC; HTTP OAuth candidato e bridge locale |

Gemini CLI entra nella consegna 2 insieme ai nuovi client principali. Novamira lo elenca soltanto fra le destinazioni CLI, ma [Gemini CLI documenta anche MCP e OAuth](https://geminicli.com/docs/tools/mcp-server/). Questo dimostra perché le categorie del catalogo Novamira non vanno trattate come limiti tecnici dei prodotti.

Le altre destinazioni CLI saranno aggiunte attraverso lo stesso registro e la stessa skill Forge, dopo aver verificato installazione e caricamento delle istruzioni nel client. Il catalogo completo è in fondo al documento. Nessuna tabella di 80 pulsanti viene mostrata nella prima schermata.

### Differenze trovate rispetto ai template Novamira

| Caso | Evidenza attuale | Conseguenza |
| --- | --- | --- |
| Copilot | Novamira genera `.github/copilot/mcp.json` con `servers`. [GitHub CLI documenta](https://docs.github.com/en/copilot/how-tos/copilot-cli/customize-copilot/add-mcp-servers) `.mcp.json` o `.github/mcp.json`, con `mcpServers` o formato piatto. | Non riutilizzare quel template come configurazione universale Copilot. |
| Copilot cloud | [GitHub](https://docs.github.com/en/copilot/how-tos/copilot-on-github/customize-copilot/configure-mcp-servers) dichiara che cloud agent e code review non supportano server MCP remoti autenticati con OAuth. | Il nome Copilot deve includere la superficie; la compatibilità CLI non vale per il cloud. |
| Kilo | Il sorgente Novamira usa `.kilocode/mcp.json`. La [documentazione corrente](https://kilo.ai/docs/automate/mcp/using-in-kilo-code) usa `mcp` in `kilo.json/jsonc`. | Adapter per versione; conservare i file esistenti e non migrare senza controllo. |
| Windsurf | La [pagina ufficiale richiesta](https://docs.windsurf.com/windsurf/cascade/mcp) reindirizza alla documentazione Devin Desktop, che separa Cascade legacy dal nuovo agente. | Risolvere il prodotto realmente installato prima di scegliere file e comandi. |
| Amazon Q | Novamira propone i file `mcp.json`. [AWS](https://docs.aws.amazon.com/amazonq/latest/qdeveloper-ug/mcp-ide.html) documenta anche `default.json` e condizioni per il formato legacy. | Individuare la configurazione attiva; non aggiungere un secondo file ignorato. |
| OpenCode e Zed | Novamira li instrada al bridge anche nel ramo OAuth pubblico. [OpenCode](https://opencode.ai/docs/mcp-servers/) e [Zed](https://zed.dev/docs/ai/mcp) documentano OAuth remoto. | Testare l’opzione nativa, senza imporre un bridge per imitazione. |

Per gli altri adapter, fonti di partenza verificate: [Claude Code](https://code.claude.com/docs/en/mcp), [Codex](https://learn.chatgpt.com/docs/extend/mcp?surface=cli), [Cursor install links](https://prod.cursor.com/docs/mcp/install-links), [VS Code](https://code.visualstudio.com/docs/agent-customization/mcp-servers), [Antigravity](https://www.antigravity.google/docs/mcp), [Cline](https://docs.cline.bot/mcp/mcp-overview), [Roo Code](https://roocodeinc.github.io/Roo-Code/features/mcp/using-mcp-in-roo/). Documentazione e prova reale restano due livelli separati.

## Architettura da realizzare

### Registro unico dei client

Una fonte dati alimenta selettore, generazione, backend, JavaScript, test e documentazione. Ogni voce registra `id`, famiglia, superficie, alias legacy, capacità shell, trasporti, schema/file di configurazione, scope, metodo di installazione, gestione reload, versioni e sistemi operativi verificati, livello di supporto e fonte della verifica.

La disponibilità dipende da client e ambiente. Un nome sconosciuto resta `generic` o genera un errore esplicito; non diventa Codex. I nomi dichiarati dal client sono metadati, non prove di identità su cui concedere permessi.

Migrazione prevista: `claude` con target CLI diventa `claude-code`; target desktop diventa `claude-desktop`. Conservare alias, sessioni e impostazioni pregresse. Codex app e CLI possono condividere la famiglia, mantenendo istruzioni e risultati dei test distinti. Copilot in VS Code, CLI e cloud devono restare distinguibili.

### Helper di connessione

Componente già avviato nella working copy come `livecanvas-forge-connect`, runtime `0.2.0-beta.6`. È incluso nell'archivio distribuito con la build locale del plugin; questo non equivale a pubblicazione npm o qualifica della release. Installazione del pacchetto reale e prove dei client restano criteri di uscita.

L’helper riceve il sito atteso e il client, rileva shell, versione e workspace sul computer dell’agente, controlla la raggiungibilità e prepara soltanto i file necessari. È idempotente: conserva server e preferenze estranei, crea backup, usa scritture atomiche e si ferma su conflitti. Supporta JSONC e TOML senza ricostruzioni distruttive. La configurazione di progetto è preferita dove supportata; le app con configurazione globale usano nomi server specifici del sito.

La distribuzione deve avere release versionate e provenienza verificabile. Il bootstrap deve restare leggero: i moduli di browser e compilazione vengono caricati solo quando necessari. Oggi il pacchetto MCP dichiara dipendenze Playwright e Sass; misurare download a freddo e startup prima di decidere lo split. Nessun numero di velocità è stato misurato in questa ricerca.

Il rilascio deve includere una prova da computer pulito contro il pacchetto effettivamente distribuito. Il prompt generato non può richiamare un comando nuovo dentro una versione npm che ancora non lo contiene. Scegliere e verificare il canale del pacchetto prima di aggiornare le istruzioni: pubblicazione versionata oppure artefatto distribuito con il plugin, con verifica di integrità. La pubblicazione resta un'attività di rilascio separata dalla ricerca.

Il prompt comune è breve e vincola l’agente a preparare il collegamento, preservare la configurazione, mostrare il consenso e verificare il sito. Non contiene token, non autorizza modifiche al contenuto e non disattiva le conferme di sicurezza dell’app. Istruzioni manuali e comando completo rimangono consultabili.

### Selezione del trasporto

| Condizione verificata | Strategia |
| --- | --- |
| HTTPS pubblico, MCP Adapter e OAuth disponibili, coppia client/versione qualificata | HTTP con OAuth gestito dal client |
| Sito locale/privato raggiungibile dall’host dell’agente | Bridge Forge STDIO con pairing esistente |
| OAuth non disponibile, client capace di eseguire il bridge | Bridge con pairing, senza chiedere all’utente di scegliere il protocollo |
| Client cloud e sito non raggiungibile, oppure autenticazione incompatibile | Stato non disponibile con motivo preciso; nessun tunnel o cambio di accesso automatico |
| Funzioni che richiedono filesystem o build locali | Attivazione del runtime necessario quando richiesto dal lavoro, fuori dalla connessione di base |

L’idoneità OAuth è oggi [limitata al ramo Codex](../includes/class-lcfa-admin.php#L1864); va sostituita con capacità qualificate. Il [server Forge](../includes/class-lcfa-oauth-server.php#L471) dichiara authorization code e refresh token, non OAuth device authorization. Il pairing esistente può già servire per approvare da un altro browser: non va presentato come implementazione standard del device grant.

La compatibilità OAuth richiede test di discovery, DCR, callback, PKCE, audience/resource, issuer, rinnovo e revoca. Per esempio, Gemini CLI documenta controlli sull’issuer della risposta: la sola presenza di un endpoint OAuth non basta. Non allentare i controlli per ottenere una connessione verde.

L’HTTP locale richiede una policy esplicita limitata all’ambiente di sviluppo e al sito selezionato. Per HTTPS locale usare un certificato attendibile o una CA configurata per quella connessione; non trasferire da Novamira il bypass `NODE_TLS_REJECT_UNAUTHORIZED=0`. La semplificazione non deve esporre automaticamente il sito su Internet.

### Stato e verifica comuni

Internamente: `prepared → waiting_for_client → authorization_required → verifying → ready`, con stati recuperabili per reload, rifiuto, scadenza e incompatibilità. Ogni tentativo ha identità propria, collegata a sito, client, configurazione attesa e sessione o grant autenticato.

La prova finale deve arrivare dal client che userà Forge. Un test eseguito soltanto dall’helper può provare rete e credenziali, ma non dimostra che l’app abbia caricato MCP. Dopo l’eventuale reload, il client effettua un handoff autenticato con una challenge breve del tentativo e una lettura innocua del sito. Per una futura CLI operativa, la prova sarà la chiamata autenticata eseguita dall’agente attraverso quella CLI.

Non usare la presenza di un grant qualsiasi come successo. Due agenti, due schede WordPress o due progetti non devono completarsi a vicenda. Polling limitato mentre la pagina è aperta, pausa quando nascosta, arresto a successo o scadenza; ripresa senza rigenerare tutto. I token non devono finire nei log o negli URL del wizard.

### CLI e skill per il catalogo esteso

La base esiste in [cli.js](../mcp/src/cli.js#L27): `--tool` esegue uno strumento e restituisce JSON. La seconda consegna aggiunge un'interfaccia stabile per login/pairing, stato e diagnosi, con guida per le operazioni Forge già disponibili. Riutilizzare [WPClient](../mcp/src/wp-client.js) e [SessionAuth](../mcp/src/session-auth.js), evitando un secondo sistema di policy.

Va definito il contratto degli esiti: il wrapper attuale imposta `ok: true` quando l'invocazione ritorna, anche se il risultato interno richiede ancora il pairing. Login in attesa, errore operativo e operazione riuscita devono avere esiti distinguibili e codici di uscita documentati. Questo controllo è necessario prima che la skill usi la CLI per dichiarare conclusa una connessione o una scrittura.

La CLI deve usare gli stessi controlli di autorizzazione, sito, preview, audit e rollback. Aggiungere provenienza `cli` distinta da `mcp`, senza falsificare i log esistenti. La skill descrive le operazioni disponibili e il punto in cui richiedere consenso. L’installer sceglie la destinazione prevista per ciascun agente, rispetta lo scope e verifica che la skill venga caricata.

Per la prima release CLI definire esplicitamente le operazioni coperte. Non dichiarare parità completa MCP/CLI prima dei test, soprattutto per preview visive, tool locali e build. Gli agenti che dispongono solo di questa strada diventano «supportati via CLI» quando passano la loro qualifica, anche se il loro adattatore MCP non esiste.

## Piano di consegna e criteri di uscita

| Fase | Lavoro | Criterio di uscita |
| --- | --- | --- |
| 0. Contratto e inventario | Registro, identità canoniche, alias, matrice ambiente/client/versione, schema dei tentativi | Tutti i cinque client dichiarati e generic rappresentati; migrazione testata senza perdita di sessioni |
| 1. Wizard unico | Sostituire fast path Codex e wizard generico con stesso presenter; stesso prompt base; nascondere scelte tecniche | Tutti i cinque client hanno gli stessi stati e una sola azione principale; nessun privilegio UI esclusivo Codex |
| 2. Bootstrap e verifica | Helper, merge sicuro, ripresa del pairing, handoff correlato, errori contestuali | Qualifica completa dei client attuali, inclusa Claude Code; nessun falso Ready |
| 3. Nuovi client principali | VS Code/Copilot CLI, Gemini CLI, Antigravity app/CLI, Windsurf/versione corrente, Cline, Roo, Kilo, Amazon Q, Zed | Adapter versionati e prove reali; HTTP nativo pubblicato solo dove qualificato |
| 4. Copertura CLI estesa | Completare la CLI `--tool` esistente con login, diagnosi e skill Forge; destinazioni del catalogo Novamira e livelli di supporto espliciti | Installazione e uso provati per ogni client pubblicizzato; feature CLI ed esiti dichiarati con precisione |
| 5. Connettori cloud | Claude.ai, ChatGPT.com, Grok.com e Grok Bot; verifica separata delle superfici cloud dei coding agent | Accesso/account/trasporto verificati; nessuna promessa di supporto a `.local` dal cloud |
| 6. Documentazione e rollout | Guida pubblica, README e istruzioni generate dal registro; rilascio graduale; percorso precedente recuperabile | I client pubblicizzati corrispondono alla matrice di qualifica e le connessioni esistenti restano funzionanti |

Le fasi 0, 1 e 2 formano la consegna 1. Le fasi 3 e 4 formano la consegna 2. La fase 5 è la consegna 3. La fase 6 accompagna ogni consegna. Questa sequenza riduce prima l’attrito per chi usa già Forge, poi amplia il catalogo senza tre wizard separati.

### Stato della working copy e prossimo ordine di lavoro

La prima consegna è parzialmente implementata: stessa schermata per i cinque client, installer con merge JSONC/TOML, consenso Full Access per sessione, polling e verifica correlata. Il test del pacchetto ZIP passa. Mancano le prove end-to-end della nuova versione nei client reali; i test della release precedente restano evidenza separata.

1. Chiudere i problemi del bootstrap: quoting Windows, interruzione delle richieste lente, errori comprensibili per ruoli WordPress con permessi parziali e compatibilità delle configurazioni esistenti.
2. Verificare il pacchetto installato in ambiente isolato e il wizard in WordPress. Il sito del repository `http://localhost:8887` risulta spento; l'uso di un altro sito di test deve essere concordato prima del deploy.
3. Eseguire la matrice dei cinque client dichiarati. Un client supera il test di connessione dopo una lettura autenticata dalla propria app; scrittura e rollback si provano separatamente su dati di test.
4. Solo dopo questa qualifica, procedere con gli adapter degli altri client principali e la CLI/skill. Le voci ancora sperimentali restano marcate come tali e fuori dalla promessa di compatibilità della release.

File attuali coinvolti: `class-lcfa-admin.php`, `class-lcfa-connection-onboarding.php`, `class-lcfa-connection-wizard-presenter.php`, `class-lcfa-connection-bundle-builder.php`, `class-lcfa-direct-agent-onboarding.php`, `class-lcfa-connection-tester.php`, settings, REST, registry delle abilità, session manager e client JavaScript. Il componente oggi chiamato `direct-agent-onboarding` deve smettere di produrre stato e testo specifici per Codex.

## Test e misurazione

| Area | Prove obbligatorie |
| --- | --- |
| Configurazioni | Fixture per ogni schema e versione; file vuoti, già configurati, JSONC, path con spazi, collisioni di nomi, file non scrivibili; rollback della sola modifica Forge |
| Ambienti | macOS, Windows e Linux dove supportati; HTTPS pubblico, HTTP locale, certificato locale, container/SSH, sito irraggiungibile, Node assente, proxy/basic auth |
| Autorizzazione | Consenso negato, richiesta scaduta, token revocato, rinnovo, privilegi insufficienti, sessione vecchia; nessun aumento di scope implicito |
| Identità | Due siti, due client e due tentativi contemporanei; nessun falso completamento; migrazione `claude` senza perdita dello storico |
| App reale | Installazione, trust/reload quando necessario, handoff e snapshot; per dichiarare supporto alle scritture, preview/apply/rollback su sito di test |
| UX | Singola scelta del client, azione principale subito visibile, niente editing JSON nel percorso ordinario dei coding agent con shell, recupero senza ripartire, tastiera e zoom |

Registrare localmente le durate fra avvio, preparazione, richiesta di consenso e handoff. Separare tempo di download, tempo di esecuzione e attesa dell’utente; distinguere installazione a freddo da riconnessione. Misurare completamento ed errori per client/versione, senza memorizzare token, prompt o percorsi personali. Eventuale telemetria esterna richiede una scelta di prodotto separata.

Obiettivi proposti: zero decisioni sul protocollo nel percorso ordinario, zero smoke test da avviare manualmente, zero falsi stati Collegato nei test, riduzione di almeno il 50% del tempo mediano di prima connessione rispetto alla baseline misurata. Sono criteri di progetto, non risultati già ottenuti. Verificare anche p95 e numero di ritorni tra WordPress e app, perché la mediana da sola nasconde gli utenti bloccati.

## Catalogo CLI aggiuntivo completo di Novamira

Queste sono le 60 voci assenti dal catalogo MCP di Novamira 1.12.5. La classificazione descrive il loro inserimento in Novamira; alcuni prodotti, come Gemini CLI, hanno anche MCP proprio. Non costituisce una lista di client già supportati da Forge.

| Gruppo alfabetico | Voci |
| --- | --- |
| A fino a C | AdaL, AiderDesk, Amp, AstrBot, Augment, Autohand Code CLI, CodeArts Agent, CodeBuddy, Codemaker, Code Studio, Command Code, Continue, Cortex Code, Crush |
| D fino a J | Deep Agents, Devin for Terminal, Dexto, Droid, Eve, Firebender, ForgeCode, Gemini CLI, Goose, Hermes Agent, IBM Bob, iFlow CLI, inference.sh, Jazz, Junie |
| K fino a P | Kimi Code CLI, Kiro CLI, Kode, Lingma, Loaf, MCPJam, Mistral Vibe, Moxby, Mux, Neovate, Ona, OpenClaw, OpenHands, Pi, Pochi, PromptScript |
| Q fino a Z | Qoder, Qoder CN, Qwen Code, Reasonix, Replit, Rovo Dev, Tabnine CLI, Terramind, Tinycloud, Trae, Trae CN, Warp, ZCode, Zencoder, Zenflow |

Le 12 destinazioni CLI già presenti nelle 20 voci MCP sono Antigravity, Antigravity CLI, Claude Code, Cline, Codex CLI, Cursor, GitHub Copilot, Kilo Code, OpenCode, Roo Code, Windsurf e Zed. Eve e PromptScript sono marcati project-only nel manifesto Novamira; gli altri sono global. Il registro Forge deve verificare e scegliere il proprio scope, con preferenza per il progetto quando consentito.

I riferimenti WordPress/GitHub confrontati sono nella [ricerca precedente](./connection-wizard-research-and-proposal-2026-09-25.md), aggiornata anche con WPPilot e Agent Connector for WP. Per questo lavoro i riferimenti più utili sono Novamira per prompt e CLI, WPPilot per la distinzione fra client e metodi, WordPress MCP Adapter per il contratto delle abilità e Tropk per il wizard breve. Le dichiarazioni dei repository non equivalgono a test svolti in questa ricerca.

Novamira resta un riferimento di comportamento. I suoi sorgenti dichiarano AGPL-3.0-or-later: questo piano prevede implementazione Forge autonoma, senza copia del codice concorrente.
