# Corsivo Core Editor

Configuratore WordPress della suite Corsivo: riunisce design system del tema, funzionalità del core e tutti i moduli di Corsivo Toolkit. Cartella `corsivo-core-editor`, repository `corsivo-suite/core-editor`.

## Scopo

Gestire il design system del tema e il comportamento del sito da Impostazioni → Corsivo Core Editor. I token del tema vengono applicati a runtime dentro `theme.json` e come CSS iniettato; funzionalità WordPress e moduli opzionali sono configurati per sito, indipendentemente dal tema attivo. La struttura visuale esistente resta invariata, con due nuove sezioni dedicate.

## Funzionalità WordPress e Toolkit

**WordPress Features** controlla pingback/trackback, conservazione delle revisioni, intervalli Heartbeat distinti per editor e altre schermate amministrative, pattern remoti e supporto emoji. Nessun controllo modifica il comportamento nativo finché non viene configurato. La sezione mostra anche lo stato di WP-Cron e gli eventi registrati in sola lettura: non esegue, elimina o disattiva i lavori.

**Toolkit** comprende tutti i moduli precedenti: Disable Comments, SVG Upload, Grid, WooCommerce Gutenberg e WooCommerce Notices. Solo i moduli abilitati vengono inclusi. La griglia conserva lo shortcode `corsivo_grid`, le funzioni pubbliche `corsivo_toolkit_render_grid()` / `corsivo_toolkit_get_grid()` e i relativi punti di estensione. SVG Upload conserva validazione, sanitizzazione e limiti del codice Toolkit locale.

Le opzioni `corsivo_core_wordpress_features` e `corsivo_core_enabled_modules` appartengono al singolo sito e non vengono salvate in `corsivo-theme.json`. La selezione di Toolkit viene importata dall'opzione `corsivo_toolkit_enabled_snippets` soltanto quando la nuova opzione non esiste; una selezione vuota salvata viene rispettata. Il vecchio plugin può restare installato: quando Core Editor è attivo, ne vengono esclusi loader e pagina amministrativa per evitare doppie esecuzioni. Nessuna dipendenza runtime dal vecchio plugin.

La rinomina riguarda nome, directory, file principale, repository, menu e distribuzione. I contratti specifici del tema (`corsivo_theme_*`, route REST, meta e `corsivo-theme.json`) restano validi. Il percorso multisite della configurazione del tema resta quello esistente sotto `uploads/corsivo-theme-editor/{stylesheet}/`; le cache degli asset ricostruibili usano la nuova identità.

## Come funziona

**Fonte unica** — `{tema-attivo}/corsivo-theme.json` (in multisite: `uploads/corsivo-theme-editor/{stylesheet}/`), path filtrabile via `corsivo_theme_settings_file_path`. Sezioni: `configurator` (fonts, colors, breakpoints, custom_fonts Google, install_fonts `@font-face`, spacing, global_styles, style_templates, context_styles, core_components, functional_pages), `global` (toggle), `wordpress` (homepage per lingua), `custom_slugs`, `functional_pages`.

**Doppio canale dati** — ogni sezione ha un filtro (`corsivo_theme_fonts`, `corsivo_theme_colors`, `corsivo_theme_breakpoints`, `corsivo_theme_spacing`, `corsivo_theme_global_styles`, `corsivo_theme_style_templates`, `corsivo_theme_context_styles`, `corsivo_theme_core_components`, `corsivo_theme_custom_fonts`, `corsivo_theme_install_fonts`, `corsivo_theme_pages`): il tema fornisce i dati a priorità 10, a priorità 20 la sezione corrispondente del file config, se non vuota, li sostituisce.

**Applicazione runtime, nessun file generato nel tema** — (1) il filtro `wp_theme_json_data_theme` inietta palette, `fontFamilies` con `fontFace`, layout (`contentSize`/`wideSize`), `spacingSizes`, `blockGap`, stili globali body/heading/link e le variazioni di stile dei blocchi (CSS parsato in struttura theme.json, con risoluzione della catena `inherits` e pseudo-stati); (2) CSS inline accodato a `wp_enqueue_scripts` (98/99) per titoli di pagina, blocchi default, template di contesto e componenti core; (3) Google Fonts come `<link>` asincroni con preconnect e font self-hosted emessi dal runtime `fontFace` core, comprese le OpenType features.

**Style template e contesti** — i template si assegnano ai contesti (`home`/`blog`, `page`, `single-{cpt}`, `post-type-archive-{cpt}`, `page-template-{slug}`, più i contesti WooCommerce quando attivi) con specificità deterministica: page template > home > single > archivio > page > custom. Risoluzione per post: meta `_corsivo_theme_style_template` (pannello sidebar nell'editor) > assegnazione di contesto > template default. `body_class` aggiunge `corsivo-theme-template-{slug}`; nell'editor lo stesso CSS viene iniettato nell'iframe.

**Scrittura** — REST `corsivo/v1/theme-config/` (`config` GET/POST, `rebuild`, `upload-font`, `theme-fonts`, `pages`; tutte `manage_options`) e form PHP della pagina impostazioni. `pages` espone ricerca paginata per lingua e hydration degli ID selezionati, senza serializzare l'intero archivio nel bootstrap. Il file viene sostituito sotto lock con controllo di revisione (hash sha256, conflitto 409 se un'altra sessione ha salvato), scrittura atomica verificata dopo il replace e backup `.bak` ripristinabile dall'admin. JSON corrotto = salvataggio disabilitato (fail-closed) con notice e pulsante di restore.

**Registro attività** — tabella `{prefix}corsivo_theme_activity_log` (InnoDB): data GMT, request id, utente, sorgente (cli/rest/ajax/admin/frontend), evento, stato, summary, dettagli JSON con limiti e path redatti (`[site]/`, `[content]/`), hash prima/dopo della configurazione. Registra salvataggi, rebuild, restore, export, upload font, integrazioni, errori di minificazione.

**Rinomina dei template** — il POST `config` accetta `template_renames`, una mappa dal vecchio slug al nuovo, separata dalla configurazione persistita. La mappa deve riferirsi a template esistenti prima del salvataggio e presenti nella nuova configurazione, con destinazioni univoche; sono ammessi scambi di slug. Dopo la scrittura verificata, sotto lo stesso lock, vengono migrate le assegnazioni `_corsivo_theme_style_template`; le assegnazioni ai template effettivamente eliminati vengono rimosse. I client che rinominano uno slug devono inviare questa mappa per conservare le assegnazioni ai contenuti.

**Riferimenti a font e colori** — il configuratore aggiorna i riferimenti strutturati in stili globali, template ed ereditarietà dei componenti quando uno slug viene rinominato; la rimozione di un preset svuota i relativi riferimenti. Le bozze temporaneamente vuote o duplicate mantengono l'origine fino alla risoluzione dello slug. Classi, variabili e slug scritti nei contenuti WordPress o nel CSS personalizzato richiedono un aggiornamento esplicito: non vengono riscritti e non vengono creati alias persistenti.

**Tipografia responsive e visibilità** — gli override `max-width` si applicano in ordine decrescente anche agli stili predefiniti, ai titoli e ai font globali o dei template, nel frontend e nell'editor. L'anteprima compone la stessa cascata. `hideInEditor` nasconde una variazione dal selettore Gutenberg mantenendo la registrazione server, il CSS e la variazione `theme.json` necessari ai contenuti esistenti.

## Punti rilevanti

- Storage del registro con schema verificato fisicamente (engine, colonne, indici confrontati con l'atteso) e attestato nelle opzioni; installazione fresh-only serializzata con lock MySQL. Una tabella completa e fisicamente esatta viene riattestata anche se contiene già dati, senza riscriverli; su schema incoerente il plugin è fail-closed, il log rifiuta le scritture e l'admin mostra l'errore.
- Config sanitizzata in profondità: le dichiarazioni CSS non possono contenere selettori (`{}`, `@import`, `expression(`, `javascript:` rifiutati), gli slug devono essere unici (`corsivo_theme_duplicate_slug`), i formati non correnti vengono respinti (`corsivo_theme_unsupported_config_format`) — il file è current-format-only.
- Upload font validati sui magic byte (woff/woff2/otf/ttf), destinazione `wp_get_font_dir()` con fallback a `{tema}/fonts`; i MIME font sono ammessi solo per `manage_options`.
- Minificazione opzionale CSS/JS in cache fingerprintata sotto `uploads/corsivo-core-editor/cache/`, preservando ordine e dipendenze. Gli asset con inline, traduzioni, media o strategie incompatibili restano gestiti da WordPress. I bundle precedenti rimangono disponibili fra richieste e salvataggi della configurazione; il rebuild esplicito pulisce la cache generata. I sorgenti del tema non vengono mai modificati.
- Retention del log: cron giornaliero, 1–3650 giorni (default 90), 100–100000 voci (default 2000); gli utenti cancellati vengono anonimizzati. Log disattivabile da admin.
- Multisite gestito per sito in attivazione, disattivazione e uninstall; l'uninstall rimuove tabella e opzioni del registro.
- Il runtime PHP resta standalone; lo sviluppo del configuratore usa il package privato `@corsivo-suite/wp-admin-ui` alla versione esatta `0.1.0`. Requisiti WordPress 6.6+, PHP 8.1+. Versione 2.0.0.

## Integrazioni con la suite

- **Plugin cliente** — forniscono il design system via filtri: fondazione-plugin implementa `corsivo_theme_colors`, `corsivo_theme_fonts`, `corsivo_theme_spacing`, `corsivo_theme_global_styles`.
- **Helper per i temi** — `corsivo_theme_get_function_page('slug')` / `corsivo_theme_get_function_page_content()` per le functional pages, `corsivo_theme_render_footer()` per il footer Gutenberg in `footer.php`; REST pubblico read-only `corsivo-theme-data/pages` e `/page/{slug}` (solo pagine pubblicate non protette).
- **WPML** — attiva di default quando presente (disattivabile da Integrations): functional pages, homepage e custom slug per lingua. **WooCommerce** — opt-in: aggiunge i contesti shop/cart/checkout/account e blocca l'override dello slug `product`.
- **Punti di estensione** — `corsivo_theme_available_contexts`, `corsivo_theme_custom_slug_post_type_allowed`, `corsivo_theme_activity_logged`, `corsivo_theme_user_fonts`, `corsivo_theme_edit_font` / `_heading` / `_paragraph`, `corsivo_theme_settings_file_path`, `corsivo_theme_settings_lock_timeout`.
- **Corsivo Updater** — canale privato dichiarato nell'header `Update URI: https://www.corsivo.dev/plugins/corsivo-core-editor`.

## Test

```bash
npm run check
npm run package
wp eval-file wp-content/plugins/corsivo-core-editor/tests/runtime-smoke.php
wp eval-file wp-content/plugins/corsivo-core-editor/tests/runtime-regressions.php
wp eval-file wp-content/plugins/corsivo-core-editor/tests/configurator-output-smoke.php
```

I comandi npm vanno eseguiti dalla root del plugin con le dipendenze del configuratore installate. `check` esegue lint, test React, `test:php` e build; `package` crea lo ZIP distribuibile, inclusi `assets/` e `templates/`. `test:php` richiede PHP nel PATH e copre storage, sicurezza, rinomine e validazione backend, stili nascosti, tipografia responsive, parsing CSS, redirect dei feed, minificazione, asset e navigazione amministrativa.

Il runtime smoke (WP-CLI, plugin attivo) verifica schema e prontezza del registro, sanitizer della config, guardie CSS e slug duplicati, rifiuto dei formati non correnti, lock e conflitti di revisione, backup e verifica post-scrittura, scope dei font e merge dei `fontFace` in theme.json. Le regressioni runtime verificano anche proxy archivio, Query Loop ereditato, paginazione, footer e lock del writer; lo smoke output controlla il CSS generato. Lo static check pretende l'assenza di installer lazy e il contratto dello storage current-only.

## Configurazione

Impostazioni → Corsivo Core Editor (`manage_options`). Sezioni: Settings, WordPress, WordPress Features, Toolkit, Breakpoints, Spacing, Colors, Fonts, Styles, Templates, Overview, Integrations, History, più Functional Pages e Custom Slugs attivabili da Settings. Toggle globali: `core_renders` (helper PHP `render_core_*` per i template), `gutenberg_footer` (pagina privata come footer a blocchi, link in Aspetto → Footer), `cpt_menu_parent` (evidenzia la voce di menu dell'archivio sul singolo CPT), `force_style_injection`, `minify_css`, `minify_js`. Export da admin: `theme.json` arricchito (usabile anche senza il plugin) e `theme-json.scss` con mappe SCSS di colori, font, breakpoint e spacing. Configuratore React buildato con `@wordpress/scripts` (`cd configurator && npm install && npm run build`). Con un tema di terze parti conviene un child theme, così `corsivo-theme.json` sopravvive agli aggiornamenti del parent.
## Bootstrap, entry point e lifecycle

`corsivo-core-editor.php` carica le funzionalità WordPress e Toolkit, quindi storage e journal dell'attività, amministrazione, integrazioni, funzioni di design system, REST pubblico e configuratore, stato delle pagine, impostazioni runtime e azioni WordPress. `includes/core-renders.php` è condizionale: viene incluso soltanto se la configurazione abilita gli helper di rendering del tema. Il configuratore React non è una seconda fonte dati: legge e scrive tramite REST lo stesso `corsivo-theme.json` consumato dalle funzioni PHP.

Gli entry point sono la pagina Impostazioni → Corsivo Core Editor, i form amministrativi di funzionalità WordPress e Toolkit, i moduli opzionali, le route REST del configuratore e delle functional page, i filtri `theme.json`, gli enqueue frontend/editor, il pannello stile nel block editor, il footer Gutenberg opzionale e il cron di retention del registro. Non esistono scritture automatiche ai sorgenti del tema durante il normale rendering.

All'attivazione, anche network-wide, viene creato e verificato lo storage fresh-only del registro e pianificata la pulizia. La disattivazione rimuove il cron senza eliminare configurazione, font o log. `uninstall.php` attraversa i siti interessati e rimuove soltanto tabella e opzioni possedute dal plugin. La cache minificata è derivata e rigenerabile; la fonte resta il file di configurazione corrente.

## Mappa completa dei file

I sorgenti del configuratore e gli artefatti `configurator/build/` sono entrambi versionati: i primi servono allo sviluppo, i secondi sono quelli caricati da WordPress. `configurator/node_modules/` è una dipendenza locale di build e non fa parte della distribuzione documentata file-per-file. Le librerie PHP in `includes/libs/` sono terze parti vendorizzate e vengono descritte come unità, con licenze riportate in `THIRD_PARTY_NOTICES.md`.

| File | Responsabilità |
|---|---|
| `.editorconfig` | Regole comuni di indentazione, encoding e fine riga. |
| `.gitignore` | Esclusioni di dipendenze e artefatti locali. |
| `corsivo-core-editor.php` | Bootstrap, hook principali, lifecycle e caricamento condizionale dei moduli. |
| `includes/activity-storage.php` | Blueprint, installazione fresh-only e verifica fisica del registro attività. |
| `includes/activity-log.php` | Scrittura, redazione, query, retention e anonimizzazione del journal. |
| `includes/admin/settings.php` | Pagina impostazioni PHP, lock/scrittura atomica, backup, restore ed enqueue del configuratore. |
| `includes/admin/tabs.php` | Struttura e rendering delle tab amministrative non React. |
| `includes/admin/script.js` | Interazioni delle schermate impostazioni PHP. |
| `includes/admin/style.css` | Stili delle schermate amministrative classiche. |
| `includes/admin/pages-status.php` | Stato e diagnostica delle functional page configurate. |
| `includes/admin/editor-panel.js` | Pannello Gutenberg per il template di stile assegnato al contenuto. |
| `includes/integrations.php` | WPML, WooCommerce e punti di integrazione con tema/plugin clienti. |
| `includes/functions.php` | Lettura/merge della configurazione, theme.json, CSS, font e risoluzione dei contesti. |
| `includes/rest-api.php` | REST read-only delle functional page e dei dati pubblici ammessi. |
| `includes/rest-api-configurator.php` | REST `manage_options` per config, rebuild e gestione font. |
| `includes/settings.php` | Applicazione runtime delle impostazioni, enqueue e cache degli asset derivati. |
| `includes/actions.php` | Rewrite, proxy degli archivi, template, menu e azioni collegate alle functional page. |
| `includes/core-renders.php` | Helper opzionali per renderizzare componenti core nei template PHP. |
| `includes/minify.php` | Coordinamento della minificazione e cache fingerprintata di CSS/JS. |
| `includes/libs/minify/` | Libreria terza Matthias Mullie Minify, inclusi sorgenti, dati, binari e licenza. |
| `includes/libs/path-converter/` | Libreria terza per riscrivere path CSS durante la minificazione, con licenza e metadata Composer. |
| `assets/footer-editor.js` | Controlli dell'editor per la pagina privata usata come footer Gutenberg. |
| `style.css` | Stili runtime di base del plugin sul frontend. |
| `template-sample.php` | Esempio d'integrazione degli helper e dei token in un tema PHP. |
| `templates/archive-page-proxy.php` | Template proxy che rende una functional page come archivio configurato. |
| `configurator/package.json` | Manifest npm privato, dipendenza Corsivo UI `0.1.0` e script del configuratore. |
| `configurator/package-lock.json` | Lock riproducibile delle dipendenze del configuratore. |
| `configurator/webpack.config.js` | Configurazione di build basata sullo stack WordPress. |
| `configurator/src/index.js` | Entry point React e montaggio dell'applicazione. |
| `configurator/src/index.scss` | Stili legacy sorgente dell'interfaccia del configuratore. |
| `configurator/src/admin-ui.scss` | Integrazione scoped dei componenti Corsivo UI nel configuratore. |
| `configurator/src/adminUiTranslations.js` | Label, formatter e annunci accessibili dei componenti Corsivo UI nel text domain del plugin. |
| `configurator/src/collectionAdapters.js` | Adapter delle collection UI verso le shape persistite e riconciliazione dei riferimenti tra slug. |
| `configurator/src/collectionAdapters.test.js` | Test di identità, reorder, rename e remove per le collection. |
| `configurator/src/configAdapter.js` | Normalizzazione del caricamento e payload di salvataggio senza stato UI transient. |
| `configurator/src/configAdapter.test.js` | Test save/load, ordine e rimozione dello stato UI transient. |
| `configurator/src/api.js` | Client delle route REST del configuratore e gestione errori/conflitti. |
| `configurator/src/store.js` | Stato condiviso, caricamento, modifiche e salvataggio della configurazione. |
| `configurator/src/fontPreviewStyles.js` | Composizione della cascata tipografica responsive usata dall'anteprima. |
| `configurator/src/fontPreviewStyles.test.js` | Regressioni sull'ereditarietà dei breakpoint e sull'ordine delle soglie uguali. |
| `configurator/src/lib/fontFeatures.js` | Catalogo e normalizzazione delle feature OpenType. |
| `configurator/src/components/App.jsx` | Shell, navigazione e orchestrazione delle sezioni del configuratore. |
| `configurator/src/components/Breakpoints/BreakpointsTab.jsx` | Editing dei breakpoint del design system. |
| `configurator/src/components/Colors/ColorsTab.jsx` | Editing della palette e degli slug colore. |
| `configurator/src/components/Fonts/FontsTab.jsx` | Gestione delle famiglie, font face e ruoli tipografici. |
| `configurator/src/components/FunctionalPages/FunctionalPagesTab.jsx` | Associazione e stato delle pagine funzionali. |
| `configurator/src/components/GoogleFonts/GoogleFontsImport.jsx` | UI per importare famiglie e varianti da una URL Google Fonts. |
| `configurator/src/components/GoogleFonts/parseGoogleFontsUrl.js` | Parser e validatore della URL Google Fonts. |
| `configurator/src/components/InstalledFonts/InstalledFontsTab.jsx` | Gestione dei font caricati e installati localmente. |
| `configurator/src/components/Overview/OverviewTab.jsx` | Riepilogo operativo della configurazione corrente. |
| `configurator/src/components/Spacing/SpacingTab.jsx` | Editing della scala spaziature e delle dimensioni layout. |
| `configurator/src/components/Styles/StylesTab.jsx` | Stili globali, componenti core e dichiarazioni CSS controllate. |
| `configurator/src/components/Templates/TemplatesTab.jsx` | Template di stile, inheritance e assegnazioni ai contesti. |
| `configurator/src/components/Templates/TemplateCollections.jsx` | Repeater ordinabili di style template e assegnazioni ai contesti. |
| `configurator/src/components/Typography/BreakpointOverrides.jsx` | Override tipografici per breakpoint. |
| `configurator/src/components/Typography/FontPreview.jsx` | Anteprima delle famiglie, pesi e feature tipografiche. |
| `configurator/build/index.js` | Bundle JavaScript di produzione caricato in admin. |
| `configurator/build/wawoff2-decompress.js` | Decoder WOFF2 copiato dalla build per il supporto ai font installati. |
| `configurator/build/index.css` | CSS LTR compilato del configuratore. |
| `configurator/build/index-rtl.css` | Variante RTL generata del CSS. |
| `configurator/build/index.asset.php` | Manifest WordPress di dipendenze e versione del bundle. |
| `tests/runtime-smoke.php` | Smoke E2E in WordPress su storage, config, lock, font e theme.json. |
| `tests/storage-static.php` | Guardie statiche sul contratto current-only dello storage. |
| `tests/security-regressions.php` | Regressioni isolate su CSS, pagine funzionali e minificazione. |
| `tests/backend-regressions.php` | Regressioni isolate su validazione, rinomine dei template, metadati e rewrite. |
| `tests/runtime-style-regressions.php` | Regressioni isolate su variazioni nascoste, tipografia responsive, shorthand CSS e feed degli archivi. |
| `tests/minify-regressions.php` | Regressioni su cache, ordine, dipendenze e selezione degli asset minificabili. |
| `tests/runtime-regressions.php` | Fixture WordPress per archivi, Query Loop, paginazione, footer e lock del writer. |
| `tests/admin-navigation.php` | Verifiche statiche della navigazione amministrativa. |
| `tests/configurator-assets.php` | Verifica statica di dipendenza privata, manifest e asset LTR/RTL del configuratore. |
| `tests/configurator-output-smoke.php` | Snapshot smoke dell'output CSS generato per breakpoint e contesti. |
| `uninstall.php` | Rimozione multisite-safe del registro e delle opzioni possedute. |
| `THIRD_PARTY_NOTICES.md` | Attribuzioni e condizioni delle librerie vendorizzate. |
| `changelog.txt` | Storico delle release. |
| `LICENSE` | Testo della licenza GPL-2.0-or-later. |
| `README.md` | Specifica operativa e mappa del plugin. |
