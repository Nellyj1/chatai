# AI Product Chatbot

## 🔑 Licentie Tiers & Functionaliteiten

De AI Product Chatbot werkt met een flexibel licentiesysteem met drie tiers. Elke tier biedt verschillende functionaliteiten om aan te sluiten bij de behoeften van je bedrijf.

### 📊 Functionaliteiten per Licentie Tier

**🚫 Geen Licentie:**
- Geen chatbot functionaliteit
- Alleen licentie activatie en basis admin toegang

| Functionaliteit | Basic (€99/jaar) | Business (€299/jaar) | Enterprise (€599/jaar) |
|------------------|------------------|----------------------|------------------------|
| **Basis Chatbot** | ✅ | ✅ | ✅ |
| **WooCommerce Basis** | ✅ (handmatig, 50 producten) | ✅ (volledig) | ✅ (volledig) |
| **FAQ Systeem** | ✅ (max 20 items) | ✅ (max 100 items) | ✅ (onbeperkt) |
| **Kenmerken Beheer** | ❌ | ✅ | ✅ |
| **Basis Analytics** | ✅ | ✅ | ✅ |
| **Nederlandse Taal** | ✅ | ✅ | ✅ |
| **Product Quiz** | ❌ | ✅ | ✅ |
| **Documenten Upload** | ❌ | ❌ | ✅ (onbeperkt) |
| **Content Sources (CPT)** | ❌ | ❌ | ✅ (volledig) |
| **Multilingual Support** | ❌ | ✅ | ✅ |
|| **Privacy & GDPR Tools** | ❌ | ✅ | ✅ |
|| **Advanced Analytics** | ❌ | ❌ | ✅ |
|| **Error Tracking** | ❌ | ✅ | ✅ |
|| **API Monitoring & Rate Limiting** | ❌ | ❌ | ✅ |
| **API Integraties** | ❌ | ✅ | ✅ |
| **Priority Support** | ❌ | ✅ | ✅ |

### 🏷️ Basic Tier (€99/jaar) - Professioneel Starten

**Wat krijg je:**
- ✅ **Basis Chatbot** met FAQ en WooCommerce antwoorden (geen AI)
- ✅ **WooCommerce Basis** - Handmatige sync, max 50 producten
- ✅ **FAQ Systeem** - Tot 20 FAQ items
- ❌ **Geen Kenmerken Beheer** - Upgrade naar Business voor kenmerken
- ✅ **Basis Analytics** - Gesprekken en gebruiksstatistieken
- ✅ **Nederlandse Interface** - Volledig vertaald

**Beperkingen:**
- ❌ **Geen Product Quiz** - Upgrade prompt getoond
- ❌ **Geen Documenten Upload** - Upgrade naar Enterprise voor document management
- ❌ **Geen Custom Post Types** - Alleen post, page, product
- ❌ **Geen WooCommerce Bulk Sync** - Alleen individuele handmatige sync

### 🚀 Business Tier (€299/jaar) - Voor Groeiende Bedrijven

**Alle Basic functionaliteiten +**
- ✅ **Volledig Kenmerken Beheer** - Toevoegen, bewerken en beheren van kenmerken
- ✅ **Product Quiz/Skin Test** - Gepersonaliseerde productaanbevelingen
- ❌ **Geen Custom Post Types** - Alleen standaard WP types (post, page, product)
- ✅ **WooCommerce Volledig** - Bulk sync, unlimited producten, custom fields
- ✅ **FAQ Uitbreiding** - Maximaal 100 FAQ items
- ✅ **API Integraties** - Toegang tot geavanceerde API functionaliteiten
- ✅ **Privacy & GDPR Tools** - Data anonimisering en compliance tools
- ✅ **Multilingual Support** - Meertalige interface ondersteuning
- ✅ **Priority Support** - Snellere ondersteuning en hulp

### 🏆 Enterprise Tier (€599/jaar) - Voor Grote Ondernemingen

**Alle Business functionaliteiten +**
- ✅ **Document Management** - Upload en beheer van product documenten (onbeperkt)
- ✅ **Custom Post Types** - Volledige Content Sources met alle CPT's, meta fields en taxonomie filters
- ✅ **Onbeperkte FAQ** - Geen limiet op aantal FAQ items
- ✅ **Advanced Analytics** - Uitgebreide rapportage en inzichten
- ✅ **API Monitoring & Rate Limiting** - Real-time API tracking en bescherming

### 📈 Hoe Upgraden?

**Automatische Upgrade Prompts:**
Wanneer je premium functionaliteiten probeert te gebruiken, krijg je automatisch een upgrade prompt met:
- Duidelijke uitleg van de voordelen
- Directe link naar upgrade pagina
- Vergelijking van je huidige vs gewenste tier

**Handmatig Upgraden:**
1. Ga naar **AI Chatbot > Licentie** in je WordPress admin
2. Klik op **Upgrade** naast je huidige tier
3. Kies je gewenste tier en volg de instructies
4. Voer je nieuwe licentie key in wanneer je deze ontvangt

**Naadloze Overgang:**
- Alle bestaande data blijft behouden
- Nieuwe functionaliteiten worden direct beschikbaar
- Geen technische configuratie nodig

### 💡 Welke Tier is Geschikt voor Mij?

**Basic Tier (€99/jaar) - Perfect voor:**
- 💰 **Kleine webshops** die net beginnen
- 🚀 **Startups** met beperkt budget  
- 🛍️ **Basis e-commerce** - Tot 50 producten
- 🤖 **Eenvoudige AI chatbot** voor productadvies

**Business Tier (€299/jaar) - Perfect voor:**
- 📈 **Groeiende e-commerce bedrijven**
- 🎨 **Elk type bedrijf** - Beauty, fashion, tech, food, etc.
- 🌍 **Meertalige websites**
- 📋 **Uitgebreide productcatalogi**
- 🎓 **Gepersonaliseerde product quizzes**

**Enterprise Tier (€599/jaar) - Perfect voor:**
- 🏢 **Grote bedrijven** die alles uit de kast willen halen
- 📊 **Uitgebreide analytics** en rapportage nodig hebben
- 📋 **Document management** en onbeperkte content willen uploaden
- 🏆 **Premium ervaring** zonder limitaties

---

## Licentievalidatie en grace‑periode

Deze plugin gebruikt een online licentie‑activatie/validatie via de WordPress‑gebaseerde License Manager. Om continuïteit te garanderen bij tijdelijke storingen is er een grace‑periode op de client-site:

- Standaard grace‑periode: 48 uur (aanpasbaar via `aipc_license_grace_seconds`).
- Validatiefrequentie: dagelijkse achtergrondvalidatie via WP‑Cron; on‑demand validatie ongeveer elke 24 uur (instelbaar via `aipc_license_stale_after_seconds`).
- Gedrag:
  - Als de server bereikbaar is en de licentie bevestigt, wordt de lokale cache ververst (tier, features, verloopdatum, status, timestamps).
  - Als de server onbereikbaar is (netwerkfout) maar de laatste geldige validatie binnen de grace‑periode valt, blijft de plugin normaal werken met de gecachte gegevens.
  - Als de server expliciet aangeeft dat de licentie ongeldig/verlopen/geschorst is, wordt géén grace toegepast en worden premium functies lokaal uitgeschakeld.
- Harde limiet: een maximale aaneengesloten grace (standaard 7 dagen) via `aipc_license_max_consecutive_grace_seconds`.

Beschikbare filters:
- `aipc_license_grace_seconds` (int): standaard 48*3600.
- `aipc_license_stale_after_seconds` (int): standaard 24*3600.
- `aipc_license_max_consecutive_grace_seconds` (int): standaard 7*24*3600.

Configuratie:
- Stel de licentieserver‑URL in op de client‑site via de optie `aipc_license_server_url` (bijv. `wp option update aipc_license_server_url "https://licenses.example.com"`). Zonder deze URL wordt activatie geblokkeerd (geen offline activatie).

Notities:
- Deactivatie vereist de activation_token die bij activatie is uitgegeven. Dit voorkomt ongeautoriseerde deactivaties.
- Domeinen worden genormaliseerd (lowercase, zonder `www.`) om dubbele activaties per site te voorkomen.

---

## 🏷️ Kenmerken & Ingredients Management

### 🎯 Wat zijn Kenmerken?

Kenmerken (ook wel Ingredients genoemd) zijn een krachtige feature waarmee je product eigenschappen, materialen, ingrediënten en specificaties kunt beheren. Deze informatie helpt de chatbot om intelligente en relevante productaanbevelingen te doen.

### 📋 Hoe werkt het?

**Voor productadvies websites:**
- **Ingrediënten**: hyaluronzuur, retinol, vitamine C
- **Geschikt voor**: droge huid, anti-aging, 25+ jaar
- **Categorie**: hydraterende, anti-aging, beschermende

**Voor andere bedrijven:**
- **Eigenschappen**: biologisch, glutenvrij, waterproof, duurzaam
- **Geschikt voor**: vegan, outdoor, reizen, kantoor
- **Voordelen**: energieverhoging, focus, comfort

### 🔐 Licentie Vereisten

| Functie | Basic | Business+ |
|---------|-------|----------|
| **Kenmerken bekijken** | ✅ (read-only) | ✅ |
| **Kenmerken toevoegen** | ❌ | ✅ |
| **Kenmerken bewerken** | ❌ | ✅ |
| **Kenmerken verwijderen** | ❌ | ✅ |
| **Chatbot gebruikt kenmerken** | ✅ | ✅ |

**Basic tier**: Kan bestaande kenmerken bekijken en de chatbot gebruikt ze voor aanbevelingen, maar geen bewerkingen mogelijk.

**Business+ tier**: Volledige controle - toevoegen, bewerken, verwijderen van alle kenmerken.

### ⚙️ Waar te vinden?

**Locatie**: WP Admin → AI Chatbot → Kenmerken

### 💡 Praktisch Voorbeeld

**Stap 1: Kenmerk toevoegen** (Business+ required)
```
Naam: Hyaluronzuur
Beschrijving: Hydrateert de huid intensief
Categorie: Hydraterend
Voordelen: Vochtinbrenging, Anti-aging
Geschikt voor: Droge huid, Gevoelige huid
```

**Stap 2: Chatbot gebruik**
```
👤 "Ik heb een droge huid, wat raad je aan?"

🤖 "Voor droge huid raad ik producten aan met hyaluronzuur. 
Hyaluronzuur hydrateert de huid intensief en is perfect 
geschikt voor droge en gevoelige huid. Hier zijn enkele 
producten met dit ingrediënt..."
```

---

## Contentbronnen (WooCommerce + Custom Post Types)

Je kunt instellen welke content de chatbot als context mag gebruiken:
- WooCommerce‑productbron (schakelaar) voor productspecifieke context wanneer WooCommerce actief is.
- Elke publieke Custom Post Type (CPT), bijv. vacatures, projecten, events, artikelen, etc.

Waar configureren: WP Admin → AI Chatbot → Instellingen → Content Sources

WooCommerce
- Optie "WooCommerce‑productbron gebruiken". Wanneer ingeschakeld (en WooCommerce actief is) komt de productcontext uit WooCommerce en wordt de generieke 'product' CPT‑bron overgeslagen om dubbele items te voorkomen.

CPT‑bronnen
- Per publiek post type kun je instellen:
  - In/uitschakelen
  - Velden: titel, excerpt, content, link
  - Meta keys (comma‑separated): bijv. locatie, salaris
  - Max items (standaard 5) en Max chars per item (standaard 400)
  - Sortering (datum/titel, ASC/DESC)
  - Taxonomie‑filters per CPT (term‑slugs, comma‑separated)
  - Optionele Label Template (zie hieronder)

Label Template
- Maak per type een template voor hoe elk item in de prompt‑context wordt weergegeven.
- Placeholders:
  - {title}, {excerpt}, {content}, {link}
  - {meta:veldnaam} bijv. {meta:locatie}, {meta:salaris}
- Excerpt/content worden automatisch ingekort tot de ingestelde Max chars.

Taxonomie‑filters
- Per CPT‑taxonomie kun je resultaten beperken door term‑slugs comma‑gescheiden op te geven.
- Voorbeeld: voor een 'vacature' CPT met taxonomieën regio/dienstverband kun je instellen:
  - regio: amsterdam, rotterdam
  - dienstverband: fulltime

Vacature‑preset
- Als er een 'vacature' CPT bestaat en die nog niet is geconfigureerd, wordt automatisch een zinnige preset aangemaakt:
  - enabled: true
  - fields: title, excerpt, link
  - meta_keys: locatie, salaris
  - max_items: 10, max_chars: 400, orderby: date DESC

Caching
- CPT‑queries worden 5 minuten gecachet per post type en gebruikersvraag (transients) om database‑ en tokengebruik te beperken.

Developer hooks
- `aipc_cpt_query_args` (array $args, array $cfg, string $userMsg): pas de WP_Query‑args aan per bron.
- `aipc_cpt_context_item` (string $item, int $post_id, array $cfg): wijzig de gerenderde contextregel per post.

Compatibiliteit
- Als de WooCommerce‑bron is ingeschakeld, wordt de generieke 'product' CPT‑bron genegeerd om dubbele productregels in de prompt te voorkomen.

---

## Werken zonder AI API‑key (fallback‑modus)

De chatbot kan ook zonder AI‑provider API‑key werken. In deze fallback‑modus worden geen vrije LLM‑antwoorden gegenereerd, maar geeft de bot nog steeds nuttige reacties op basis van je lokale data en de begeleide flows.

Wat blijft werken
- Huidtest / Productquiz: volledig functioneel, geen API‑key nodig.
- FAQ: vragen die overeenkomen met je FAQ‑items worden direct beantwoord.
- Content Sources: geconfigureerde WooCommerce‑producten en/of geselecteerde CPT's (bijv. vacatures) en geüploade Documenten worden als gestructureerde context getoond (titels, samenvattingen, links, geselecteerde meta).
- Gesprekken & Analytics: conversaties worden opgeslagen; token usage/kosten blijven 0.

Wat niet beschikbaar is
- Generatieve antwoorden: vrije AI‑antwoorden, parafraseren of Q&A buiten expliciete matches zijn uitgeschakeld zonder key.

Gedrag in fallback‑modus
- De bot stelt deterministische antwoorden samen uit beschikbare lokale bronnen (WooCommerce/CPT/Documenten/FAQ). Als er niets relevants is, toont hij je fallback‑bericht en vraagt hij om verduidelijking.
- Je kunt in Instellingen de welkoms-/fallback‑berichten aanpassen, zodat de UX duidelijk blijft zolang er geen API‑key is.

Overschakelen naar volledige AI
- Zodra je een geldige API‑key en model instelt, gebruikt de bot automatisch generatieve antwoorden, met jouw Content Sources als context.

Kosten & token‑analytics
- In fallback worden geen provider‑calls gedaan; token usage is 0 en er worden geen kosten opgebouwd. Na het toevoegen van een key wordt usage per verzoek gelogd op basis van de usage‑velden van de provider.

Een intelligente AI chatbot plugin voor WordPress die productkennis en ingrediëntenkennis combineert met ChatGPT+ functionaliteit. Perfect voor schoonheids- en verzorgingswebsites.

## 🚀 Functies

### AI Chatbot Functionaliteit
- **ChatGPT+ Integratie** - Gebruikt OpenAI's geavanceerde AI modellen
- **Productkennis** - Intelligente aanbevelingen gebaseerd op je producten
- **Ingrediëntenkennis** - Uitleg over ingrediënten en hun voordelen
- **Persoonlijke Adviezen** - Aanbevelingen op basis van huidtype en behoeften
- **Productvergelijkingen** - Objectieve vergelijkingen tussen producten
- **Natuurlijke Gesprekken** - Menselijke, behulpzame interactie

### Moderne Interface
- **Responsive Design** - Werkt perfect op alle apparaten
- **3 Thema's** - Modern, Minimaal, en Kleurrijk
- **4 Posities** - Rechtsonder, Linksonder, Rechtsboven, Linksboven
- **Shortcode Support** - Plaats de chatbot overal op je website
- **Smooth Animaties** - Professionele gebruikerservaring

### Admin Panel
- **Dashboard** - Overzicht van statistieken en recente gesprekken
- **Productbeheer** - Voeg, bewerk en beheer je producten
- **Ingrediëntenbeheer** - Beheer ingrediënten en hun eigenschappen
- **Documentbeheer** - Upload en beheer productdocumenten als kennisbank (Enterprise)
- **WooCommerce Sync** - Automatische synchronisatie met WooCommerce (optioneel)
- **Instellingen** - Configureer de chatbot naar wens
- **API Test** - Test je OpenAI verbinding

## 📦 Installatie

### Stap 1: Plugin Uploaden
1. Upload de `ai-product-chatbot` map naar `/wp-content/plugins/`
2. Activeer de plugin via het 'Plugins' menu in WordPress

### Stap 2: OpenAI API Key Configureren
1. Ga naar **AI Chatbot > Instellingen** in je WordPress admin
2. Voer je OpenAI API key in (krijg er een op [platform.openai.com](https://platform.openai.com/api-keys))
3. Kies je gewenste AI model (GPT-4 aanbevolen)
4. Sla de instellingen op

### Stap 3: Producten Toevoegen
1. Ga naar **AI Chatbot > Producten**
2. Voeg je producten toe met:
   - Productnaam en beschrijving
   - Ingrediënten lijst
   - Geschikte huidtypes
   - Prijs en afbeelding URL

### Stap 4: Ingrediënten Toevoegen
1. Ga naar **AI Chatbot > Ingrediënten**
2. Voeg ingrediënten toe met:
   - Naam en beschrijving
   - Voordelen en eigenschappen
   - Geschikte huidtypes
   - Categorie (Hydraterend, Anti-aging, etc.)

### Stap 5: Documenten Uploaden (Enterprise)
1. Ga naar **AI Chatbot > Documenten**
2. Upload productdocumenten zoals:
   - Product brochures (PDF)
   - Ingrediënten gidsen (PDF/DOC)
   - Product specificaties (TXT/MD)
   - Training materialen (PDF/DOC)

### Stap 6: WooCommerce Integratie (Optioneel)
1. Installeer en activeer WooCommerce
2. Ga naar **AI Chatbot > Instellingen**
3. Schakel **WooCommerce Integratie** in
4. Configureer **Custom Fields** voor product eigenschappen en geschiktheid
5. Ga naar **AI Chatbot > WooCommerce Sync** voor beheer

## 🎯 Gebruik

### Shortcode
Plaats de chatbot op specifieke pagina's:
```
[aipc_chatbot]
```

Met opties:
```
[aipc_chatbot title="Mijn Assistent" theme="colorful"]
[aipc_chatbot welcome_message="Hoe kan ik je helpen?"]
```

### Automatische Weergave
De chatbot verschijnt automatisch op alle pagina's (behalve admin). Configureer de positie en thema in de instellingen.

### Voorbeeldvragen
Gebruikers kunnen vragen stellen zoals:
- "Ik heb een droge huid, welke producten raad je aan?"
- "Wat is het verschil tussen deze twee crèmes?"
- "Kun je me meer vertellen over hyaluronzuur?"
- "Welke ingrediënten zijn goed voor een gevoelige huid?"
- "Kun je me meer vertellen over jullie nieuwe productlijn?"
- "Wat staat er in jullie productbrochure over anti-aging?" (Enterprise)
- "Wat kost deze crème en is het op voorraad?" (WooCommerce)
- "Kun je me helpen kiezen tussen deze twee producten?" (WooCommerce)

## ⚙️ Configuratie

### Chatbot Instellingen
- **Titel**: Aangepaste naam voor de chatbot
- **Welkomstbericht**: Eerste bericht dat gebruikers zien
- **Positie**: Waar de chatbot knop verschijnt
- **Thema**: Visuele stijl van de chatbot

### OpenAI Instellingen
- **API Key**: Je OpenAI API sleutel
- **Model**: GPT-4 (aanbevolen), GPT-3.5 Turbo, of GPT-4 Turbo
- **Max Tokens**: Maximum aantal woorden in antwoorden
- **Temperature**: Creativiteit van antwoorden (0.1-1.0)

### Feature Instellingen
- **Visual Product Quiz Builder**: Interactieve drag-and-drop quiz builder voor gepersonaliseerde productaanbevelingen (Business+ licentie vereist)
- **Smart Product Recommendations**: Geavanceerde productaanbevelingen op basis van gebruikersinput

### WooCommerce Instellingen (Optioneel)
- **WooCommerce Integratie**: In/uitschakelen van e-commerce functionaliteit
- **Automatische Sync**: Real-time synchronisatie van producten
- **Shopping Cart**: Cart functionaliteit in chatbot antwoorden
- **Custom Fields**: Mapping van product eigenschappen en geschiktheid
- **Sync Beheer**: Bulk sync en clear functionaliteit

### Product Structuur
Elk product bevat:
- **Basis Info**: Naam, beschrijving, prijs
- **Ingrediënten**: Lijst van actieve ingrediënten
- **Huidtypes**: Voor welke huidtypes geschikt
- **Media**: Afbeelding en product URL

### Ingrediënt Structuur
Elk ingrediënt bevat:
- **Basis Info**: Naam, beschrijving, categorie
- **Voordelen**: Lijst van huidvoordelen
- **Huidtypes**: Voor welke huidtypes geschikt
- **Categorie**: Hydraterend, Anti-aging, etc.

## 🔧 Technische Details

### 🏗️ Licentie Implementatie

**Licentie Klassen:**
```php
AIPC_License::getInstance()->has_feature('product_quiz') // Was: custom_skin_test
AIPC_License::getInstance()->has_feature('woocommerce_full') // Was: woocommerce_sync
AIPC_License::getInstance()->has_feature('api_integrations') // Was: business_types
AIPC_License::getInstance()->get_current_tier() // 'basic', 'business', 'enterprise'
AIPC_License::getInstance()->is_active() // true/false met grace periode
```

**Feature Gates per Pagina:**
- **FAQ**: `aipc_max_faq_items` optie bepaalt limiet (20 basic, 100 business, onbeperkt enterprise)
- **Kenmerken**: `has_feature('ingredients_readonly')` voor read-only, `has_feature('ingredients_full')` voor volledig beheer
- **Documenten**: `has_feature('document_upload')` voor upload functionaliteit (Enterprise only)
- **Analytics**: `has_feature('advanced_analytics')` voor geavanceerde metrics
- **Product Quiz**: `has_feature('product_quiz')` voor skin test/product quiz
- **WooCommerce**: `has_feature('woocommerce_basic')` vs `has_feature('woocommerce_full')`
- **Content Sources**: `has_feature('content_sources_full')` voor Custom Post Types

**Grace Periode Systeem:**
- Standaard 48 uur grace bij server problemen
- Maximum 7 dagen aaneengesloten grace
- Dagelijkse achtergrond validatie via WP-Cron
- Lokale cache voor snelle feature checks

**Feature Gates per Pagina:**
```php
// Kenmerken pagina toegang op basis van licentie
if (!$license->has_feature('ingredients_readonly')) {
    // Geen toegang tot kenmerken pagina
    return;
}
```

**Automatic Upgrade Prompts:**
Wanneer gebruikers premium features proberen te gebruiken:
```php
if (!$license->has_feature($required_feature)) {
    return $this->show_upgrade_message($target_tier);
}
```

### 🚀 Performance Optimalisaties (v1.0.0)

**Caching Systeem:**
- **WooCommerce Sync Status**: 5 minuten cache voor database queries
- **Product Lists**: 2 minuten cache voor WooCommerce product data
- **Automatische Cache Invalidatie**: Bij product wijzigingen
- **Debug Logging**: Conditioneel (alleen met WP_DEBUG actief)

**Database Optimalisaties:**
- Verminderde duplicate queries door intelligent caching
- Batch processing voor grote product catalogi
- Optimalized database writes met request-level caching

**Admin Interface:**
- Snellere pagina loads door gecachte data
- Verminderde server load bij admin navigatie
- Real-time cache invalidatie bij content wijzigingen

### Vereisten
- WordPress 5.0+
- PHP 7.4+
- OpenAI API account
- HTTPS website (aanbevolen)

### Database Tabellen
- `wp_aipc_products` - Producten database (inclusief WooCommerce koppeling)
- `wp_aipc_ingredients` - Ingrediënten database
- `wp_aipc_documents` - Documenten kennisbank
- `wp_aipc_conversations` - Chat gesprekken
- `wp_aipc_analytics` - Gebruiksstatistieken
- `wp_aipc_api_monitoring` - API monitoring, rate limiting, en error tracking (Enterprise)
- `wp_aipc_faq` - FAQ items database
- `wp_aipc_token_usage` - Token gebruik en kosten tracking

### Bestandsstructuur
```
ai-product-chatbot/
├── ai-product-chatbot.php          # Hoofdplugin bestand
├── includes/
│   ├── class-aipc-openai-handler.php
│   ├── class-aipc-product-manager.php
│   ├── class-aipc-document-manager.php
│   ├── class-aipc-woocommerce-integration.php
│   ├── class-aipc-chatbot-frontend.php
│   ├── class-aipc-api-monitor.php
│   ├── class-aipc-license.php
│   └── class-aipc-database.php
├── admin/
│   ├── dashboard.php
│   ├── products.php
│   ├── ingredients.php
│   ├── documents.php
│   ├── woocommerce.php
│   ├── analytics.php
│   ├── api-monitoring.php
│   ├── faq.php
│   ├── license-compare.php
│   ├── quiz-builder.php
│   ├── quiz-json-editor.php
│   └── settings.php
├── assets/
│   ├── css/
│   │   ├── chatbot.css
│   │   ├── admin.css
│   │   └── quiz-builder.css
│   └── js/
│       ├── chatbot.js
│       ├── admin.js
│       └── quiz-builder.js
└── languages/                      # Vertalingen
```

## 🎨 Aanpassingen

### CSS Aanpassingen
Pas de chatbot styling aan via:
```css
/* In je theme's style.css */
.aipc-chatbot {
    /* Jouw aangepaste stijlen */
}
```

### JavaScript Hooks
Gebruik de beschikbare functies:
```javascript
// Open chatbot
openAIPCChatbot();

// Sluit chatbot
closeAIPCChatbot();

// Stuur bericht
sendAIPCMessage('Hallo chatbot!');
```

### PHP Filters
```php
// Pas chatbot gedrag aan
add_filter('aipc_system_prompt', function($prompt) {
    return $prompt . ' Jouw extra instructies.';
});
```

## 🚀 Smart Product Recommendations

### 🎯 Wat zijn Smart Product Recommendations?

Smart Product Recommendations is een feature die gebruikers helpt meer relevante producten te ontdekken via intelligente aanbevelingen, **onafhankelijk van** de Product Quiz functionaliteit.

### ⚙️ Hoe Werkt Het?

#### **1. "Toon Meer Producten" Functionaliteit**
**Trigger commando's**:
- `"toon meer producten"`
- `"meer producten"`  
- `"more products"`

**Werking**:
- Haalt eerder uitgevoerde Product Quiz resultaten op
- Gebruikt slim paginering systeem:
  - **Eerste keer**: Producten 5-14 (10 producten)
  - **Tweede keer**: Producten 15-24 (10 producten)
  - **Etc.**: Blijft doorgaan tot alle relevante producten zijn getoond
- Reset automatisch wanneer alle producten zijn getoond

#### **2. Fallback Mode Ondersteuning**
Wanneer de chatbot draait **zonder API key**:
- Gebruikt lokale productdatabase voor aanbevelingen
- Toont maximaal 3 producten als preview
- Eenvoudige keyword-based matching

#### **3. Intelligente Tekstrespones**
Beïnvloedt chatbot antwoorden in:
- **Begroetingen**: *"Ik kan je helpen met productaanbevelingen"*
- **Product vragen**: Verbeterde antwoorden met aanbevelingen
- **Help commando's**: Productaanbevelingen in hulp opties

### 📝 Praktijk Voorbeeld

**Scenario: Gebruiker na Product Quiz**
1. 👤 Gebruiker voltooit Product Quiz
2. 🤖 Chatbot toont **5 aanbevolen producten**
3. 👤 Gebruiker: *"toon meer producten"*
4. 🤖 Chatbot toont **10 aanvullende producten** (paginering)
5. 👤 Gebruiker: *"meer producten"*
6. 🤖 Chatbot toont **volgende 10 producten**
7. 👤 Gebruiker: *"toon meer producten"*
8. 🤖 Chatbot: *"Dat waren alle producten die passen bij je voorkeuren!"*

### ⚙️ Configuratie

**Locatie**: WP Admin → AI Chatbot → Instellingen → Features

**Instelling**: `Smart Product Recommendations`
- ✅ **Aan (standaard)**: Volledige functionaliteit beschikbaar
- ❌ **Uit**: "Toon meer producten" commando's worden genegeerd

### 🔄 Aan vs Uit

| Feature | Aan (✅) | Uit (❌) |
|---------|------------|----------|
| **"Toon meer producten"** | Werkt normaal | Genegeerd |
| **Paginering systeem** | Actief | Uitgeschakeld |
| **Fallback aanbevelingen** | Beschikbaar | Beperkt |
| **Tekstuele verwijzingen** | Volledig | Nog steeds aanwezig |

### 💡 Wanneer Uit te Schakelen?

**Redenen om uit te schakelen**:
- Je wilt **niet** dat gebruikers makkelijk door extra producten bladeren
- Beperkte productcatalogus waar paginering niet nodig is
- Custom implementatie van "meer producten" functionaliteit

**Aanbeveling**: **Houd het aan** - het verbetert de gebruikerservaring zonder negatieve bijwerkingen.

## 🎨 Visual Quiz Builder (Business+)

### 🎯 Wat is de Visual Quiz Builder?

De Visual Quiz Builder is een geavanceerde drag-and-drop interface waarmee je complexe, visuele product quizzes kunt maken zonder code te schrijven. Perfect voor gepersonaliseerde productaanbevelingen.

### ✨ Hoofdfuncties

#### 🖱️ **Drag & Drop Interface**
- **Visuele blokken**: Sleep vraag- en uitslagblokken op het canvas
- **Verbindingslijnen**: Visuele connecties tussen vragen en uitkomsten
- **Real-time preview**: Zie direct hoe je quiz eruitziet
- **Zoom & Pan**: Navigeer door grote, complexe quizzes

#### 🔗 **Slimme Verbindingen**
- **Automatische routing**: Verbind antwoorden aan vervolgvragen of uitkomsten
- **Visuele feedback**: Duidelijke lijnen tonen de quiz flow
- **Validatie**: Controle op incomplete verbindingen
- **Flexible structuur**: Ondersteunt complexe vertakkingslogica

#### 📦 **Product Mapping**
- **WooCommerce integratie**: Directe productpicker uit je webshop
- **Bulk selectie**: Meerdere producten per uitkomst
- **Product preview**: Afbeeldingen, namen en prijzen in de builder
- **Real-time sync**: Automatische updates bij productwijzigingen

### 🛠️ **Praktisch Gebruik**

#### **Stap 1: Quiz Ontwerpen**
1. Ga naar **AI Chatbot → Quiz Builder**
2. Sleep **Vraag blokken** op het canvas
3. Voeg antwoordopties toe per vraag
4. Sleep **Uitslag blokken** voor eindresultaten

#### **Stap 2: Verbindingen Maken** 
1. Selecteer een vraagblok
2. Koppel elk antwoord aan een vervolgvraag of uitslag
3. Zie direct de verbindingslijnen verschijnen
4. Test de flow met de **Test Quiz** functie

#### **Stap 3: Producten Toewijzen**
1. Selecteer een uitslagblok
2. Klik **Product Toevoegen**
3. Kies producten uit je WooCommerce catalog
4. Zie direct productafbeeldingen in het blok

### 📤 **Export & Import Systeem**

#### **Export Opties**
- **📋 Volledige JSON Export**: Alle data inclusief productinformatie - ideaal voor backup
- **⚙️ Quiz Structuur JSON**: Alleen de quiz logica - voor andere systemen
- **📊 CSV Export**: Product mappings in spreadsheet formaat
- **📄 Leesbare Export**: Menselijk leesbare documentatie

#### **Import Functionaliteit**
- **🔄 Merge Mode**: Voeg quiz toe aan bestaande blokken
- **♻️ Replace Mode**: Vervang huidige quiz compleet
- **✅ Validatie**: Controle op dataformaat en integriteit
- **👁️ Preview**: Zie wat geïmporteerd wordt voordat je bevestigt
- **📁 Drag & Drop**: Sleep bestanden direct in de interface

### 🎯 **Gebruik Cases**

#### **💄 Beauty/Skincare Quiz**
```
📋 Quiz Flow:
"Wat is je leeftijd?" 
├─ "18-25" → "Wat is je huidtype?"
├─ "25-35" → "Heb je specifieke huidproblemen?"
└─ "35+" → "Zoek je anti-aging producten?"

🎯 Uitkomsten:
├─ "Jonge huid routine" → 3 producten voor jonge huid
├─ "Anti-acne pakket" → 4 producten tegen puistjes  
└─ "Anti-aging set" → 5 producten tegen veroudering
```

#### **👗 Fashion Quiz**
```
📋 Quiz Flow:
"Wat is je stijl?" 
├─ "Casual" → "Welke maat heb je?"
├─ "Business" → "Welke kleuren draag je graag?"
└─ "Feest" → "Voor welke gelegenheid?"

🎯 Uitkomsten:
├─ "Casual outfit" → Jeans, T-shirt, Sneakers
├─ "Business look" → Blazer, Overhemd, Broek
└─ "Party dress" → Jurk, Pumps, Accessoires
```

### ⚙️ **Geavanceerde Features**

#### **🔍 Zoom & Navigatie**
- **Zoom controls**: In/uitzoomen met precisie
- **Fit to screen**: Automatisch de beste weergave
- **Pan functionaliteit**: Sleep om door grote quizzes te navigeren
- **Viewport state**: Behoudt zoom/positie bij opslaan

#### **🧹 Beheer Tools**
- **Wis alles**: Veilige reset met waarschuwingen voor grote quizzes
- **Bulk operations**: Efficiënt werken met veel blokken
- **Auto-validatie**: Realtime feedback op quiz completeness
- **Debug mode**: Gedetailleerde logging voor troubleshooting

#### **🔄 Legacy Compatibility**
- **JSON Editor**: Read-only weergave van oude quiz format
- **Automatische conversie**: Van visual naar JSON format
- **Backwards compatibility**: Oude quizzes blijven werken
- **Migratie hulp**: Eenvoudige overgang naar visual builder

### 📋 **Praktisch Voorbeeld: Complete Setup**

#### **1. Nieuwe Quiz Maken**
```
1. Ga naar AI Chatbot → Quiz Builder
2. Klik "Voeg Vraag Toe" 
3. Typ: "Voor wie zoek je producten?"
4. Voeg opties toe: "Voor mezelf", "Voor mijn partner"
5. Sleep een tweede vraag naar rechts
6. Verbind de antwoorden aan de nieuwe vraag
```

#### **2. Uitkomsten Configureren**
```
1. Sleep "Uitslag blok" naar beneden
2. Typ label: "Perfecte match voor jou!"
3. Voeg beschrijving toe over de aanbeveling
4. Klik "Product Toevoegen"
5. Selecteer relevante producten uit WooCommerce
6. Zie direct productafbeeldingen in het blok
```

#### **3. Testen & Publiceren**
```
1. Klik "Test Quiz" om de flow te controleren
2. Loop door alle paden om alles te verifiëren
3. Export als backup: "Export Quiz" → "Volledige JSON"
4. Klik "Quiz Opslaan" om live te zetten
5. Test de quiz op je website via de chatbot
```

### 🔐 **Licentie Vereisten**

| Functie | Basic | Business+ |
|---------|-------|----------|
| **Visual Product Quiz Builder** | ❌ | ✅ |
| **Drag & Drop Interface** | ❌ | ✅ |
| **Product Mapping & Export/Import** | ❌ | ✅ |
| **Legacy JSON Editor** | ✅ (Read-only) | ✅ (Read-only) |

**Basic tier**: Kan alleen bestaande quiz resultaten bekijken via read-only JSON editor.

**Business+ tier**: Volledige Visual Quiz Builder met alle functies.

### 🎓 **Tips & Best Practices**

#### **📐 Quiz Structuur**
- **Start simpel**: Begin met 2-3 vragen en bouw uit
- **Logische flow**: Zorg dat vragen op elkaar voortbouwen  
- **Duidelijke labels**: Gebruik beschrijvende namen voor uitkomsten
- **Balanceer opties**: 2-4 antwoorden per vraag werkt het best

#### **🎯 Product Toewijzing**
- **Relevante producten**: Kies producten die echt passen bij de uitkomst
- **Variatie**: 3-5 producten per uitkomst geeft keuzemogelijkheden
- **Prijsrange**: Mix duurdere en goedkopere opties
- **Voorraad**: Controleer regelmatig of producten nog beschikbaar zijn

#### **💾 Backup & Veiligheid**
- **Regelmatige exports**: Maak backups van complexe quizzes
- **Test voor live**: Gebruik altijd "Test Quiz" voor publicatie
- **Stapsgewijze updates**: Bouw grote wijzigingen stap voor stap op
- **Import testing**: Test import functie eerst met kleine bestanden

---

## 🔒 Privacy & Beveiliging

### GDPR Compliant
- Geen opslag van persoonlijke gegevens
- Gesprekken worden lokaal opgeslagen
- Gebruiker kan gegevens verwijderen
- Transparante privacy policy
- **IP Anonimisering** - Automatische anonimisering van IP adressen (Business+)
- **Data Maskering** - E-mail en telefoonnummer maskering (Business+)
- **Handmatige Cleanup** - Directe verwijdering van oude data (Business+)

### Beveiliging
- Nonce verificatie voor alle AJAX calls
- Input sanitization en validatie
- Capability checks voor admin functies
- Secure API communicatie

## 📊 API Monitoring & Rate Limiting (Enterprise)

### Real-time Monitoring
- **API Call Tracking** - Alle OpenAI/OpenRouter calls worden gelogd
- **Response Time Monitoring** - Performance tracking en alerting
- **Cost Tracking** - Real-time token gebruik en kosten berekening
- **Error Analysis** - Gedetailleerde error logs voor debugging

### Rate Limiting & Bescherming
- **IP-based Rate Limiting** - Configureerbaar (1-100 requests/minuut)
- **Abuse Protection** - Automatische bescherming tegen API misbruik
- **Retry Logic** - Intelligente retry met exponential backoff
- **Graceful Fallbacks** - FAQ/WooCommerce antwoorden bij API problemen

### Analytics Dashboard
- **7-daagse & 30-daagse Overzichten** - Succes rates, kosten, response tijden
- **Hourly Usage Charts** - Visuele grafieken van API gebruik
- **Recent Errors** - Real-time error monitoring en debugging
- **Manual Cleanup** - Handmatige data cleanup functies

### Privacy Features
- **IP Anonimisering** - IPv4: 192.168.1.123 → 192.168.1.0
- **Configurable Retention** - 1-365 dagen data bewaring
- **Automatic Cleanup** - Dagelijkse cron jobs voor oude data
- **Manual Cleanup** - Backup opties voor als cron jobs falen

## 📊 Analytics & Monitoring

### Beschikbare Statistieken
- Totaal aantal gesprekken
- Meest gestelde vragen
- Product populariteit
- Gebruikerstevredenheid

### Logging
- API call logs (optioneel)
- Error tracking
- Performance monitoring

## 🛍 WooCommerce Integratie

### 🏁 Basis Features
- **✓ Automatische sync** bij product wijzigingen
- **✓ Individuele handmatige sync** per product (Sync/Unsync knoppen)
- **✓ Shopping cart integratie**
- **✓ Real-time prijs en voorraad info**
- **✓ Fallback naar Product Tags & Categories**

### 💼 Business+ Features  
- **✓ Alle basis features +**
- **✓ Bulk "Sync Alle Producten"** voor grote catalogi
- **✓ Custom Fields configuratie** voor geavanceerde product data
- **✓ Bulk cleanup tools** (duplicaten, wis alle data)

### Automatische Product Sync (🏁 Basis)
- **Real-time synchronisatie** van WooCommerce producten
- **Automatische updates** bij prijs- en voorraadwijzigingen
- **Individuele handmatige sync** per product met Sync/Unsync knoppen
- **Product status handling** (published/unpublished)
- **Fallback naar Product Tags & Categories** (geen custom fields nodig)

### E-commerce Features
- **Prijs informatie** in chatbot antwoorden
- **Voorraad status** real-time beschikbaar
- **Product vergelijkingen** met actuele data
- **Directe product links** naar WooCommerce
- **Shopping cart integratie** (optioneel)

### Admin Beheer
- **WooCommerce Sync pagina** voor volledig beheer (🏁 Basis)
- **Sync status dashboard** met percentages (🏁 Basis)
- **Individuele product** sync status monitoring (🏁 Basis)
- **Custom field configuratie** voor data mapping (💼 Business+)
- **Bulk "Sync Alle Producten"** functionaliteit (💼 Business+)
- **Bulk "Wis Sync Data"** en cleanup tools (💼 Business+)

### Custom Fields Configuratie

🔒 **Business+ Licentie Vereist**: Deze functionaliteit vereist een Business of Enterprise licentie voor WooCommerce synchronisatie.

#### 🎯 **Wat zijn Custom Fields?**
WooCommerce Custom Fields laten je extra productdata opslaan die de chatbot kan gebruiken voor intelligente aanbevelingen.

#### ⚙️ **Configuratie**
**Locatie**: AI Chatbot → Instellingen → WooCommerce Integratie

1. **Eigenschappen Custom Field** (standaard: `_ingredients`)
   - Voor product eigenschappen, materialen, ingrediënten, specificaties
   - **Fallback**: Product Tags

2. **Geschikt Voor Custom Field** (standaard: `_skin_types`)
   - Voor doelgroep, geschiktheid, leeftijdsgroepen, gebruik
   - **Fallback**: Product Categories

#### 📝 **Praktisch Voorbeeld: Kledingwinkel**

**Stap 1: Instellingen aanpassen**
```
Eigenschappen Custom Field: _materials
Geschikt Voor Custom Field: _target_group
```

**Stap 2: WooCommerce Product - "Premium T-shirt"**
```
Custom Field: _materials
Waarde: "100% biologisch katoen, OEKO-TEX gecertificeerd"

Custom Field: _target_group  
Waarde: "heren, casual, zomer"
```

**Stap 3: Chatbot Resultaat**
```
👤 Gebruiker: "Heb je shirts van biologisch katoen?"

🤖 Chatbot: "Ja! Ik raad de Premium T-shirt aan. Deze is gemaakt 
van 100% biologisch katoen en OEKO-TEX gecertificeerd. 
Perfect geschikt voor heren, casual en zomer. Prijs: €29,95"
```

#### 🎨 **Meer Voorbeelden per Branche**

**💄 Cosmetica/Beauty:**
```
Eigenschappen: "hyaluronzuur, retinol, vitamine C"
Geschikt Voor: "droge huid, anti-aging, 25+ jaar"
```

**🍽️ Voeding/Supplements:**
```
Eigenschappen: "biologisch, glutenvrij, fair trade"
Geschikt Voor: "vegan, keto, diabetisch"
```

**💻 Elektronica:**
```
Eigenschappen: "Bluetooth 5.0, waterproof, 24h batterij"
Geschikt Voor: "gaming, kantoor, reizen"
```

**🧸 Baby/Kind:**
```
Eigenschappen: "BPA-vrij, wasbaar, veilig plastic"
Geschikt Voor: "0-12 maanden, ontwikkelingsstimulatie"
```

#### 🔄 **Fallback Systeem**
Als custom fields leeg zijn, gebruikt het systeem automatisch:
- **Eigenschappen**: WooCommerce Product **Tags**
- **Geschikt Voor**: WooCommerce Product **Categories**

**Voordeel**: Werkt ook zonder extra configuratie!

#### ⚙️ **Setup Stappen**
1. **Pas field namen aan** in AI Chatbot → Instellingen (optioneel)
2. **Voeg custom fields toe** in WooCommerce → Product → Custom Fields
3. **Synchroniseer producten** via AI Chatbot → WooCommerce
4. **Test de chatbot** met product-gerelateerde vragen

## 🤖 ChatGPT+ Integratie Opties

### Optie 1: Custom GPT (Aanbevolen)
1. **Maak een Custom GPT** in ChatGPT+
2. **Upload je productdocumenten** (PDF, DOC, TXT)
3. **Train de AI** op jouw specifieke producten
4. **Gebruik de Custom GPT API** in de plugin
5. **Voordelen**: Volledige controle, geen document upload nodig

### Optie 2: Document Upload (Enterprise Implementatie)
1. **Upload documenten** via AI Chatbot > Documenten (Enterprise only)
2. **Automatische tekst extractie** uit PDF/DOC bestanden
3. **Intelligente zoekfunctie** in document content
4. **Contextuele antwoorden** gebaseerd op documenten
5. **Voordelen**: Lokale controle, geen externe afhankelijkheden

### Optie 3: Hybrid Approach
- **Combineer beide methoden** voor maximale flexibiliteit
- **Database producten** voor gestructureerde data
- **Document content** voor uitgebreide informatie (Enterprise)
- **Custom GPT** voor geavanceerde AI functionaliteit

## 🚀 Toekomstige Uitbreidingen

### Geplande Features
- **Multi-language Support** - Meertalige chatbot
- **Voice Interface** - Spraakherkenning en -synthese
- **Advanced Analytics** - Uitgebreide rapportage
- **Integration APIs** - Koppeling met andere systemen
- **Custom AI Training** - Specifieke training voor jouw merk

### Integraties
- WooCommerce product sync
- Mailchimp email marketing
- Google Analytics tracking
- Facebook Messenger integration

## 🆘 Ondersteuning

### Veelgestelde Vragen
**Q: Werkt de chatbot zonder internet?**
A: Nee, de chatbot heeft een internetverbinding nodig voor OpenAI API calls.

**Q: Kan ik de chatbot aanpassen aan mijn merk?**
A: Ja, via CSS aanpassingen en de beschikbare thema's.

**Q: Hoeveel kost de OpenAI API?**
A: Prijzen variëren per model. GPT-3.5 Turbo is goedkoper dan GPT-4.

**Q: Kan ik de chatbot uitschakelen op bepaalde pagina's?**
A: Ja, gebruik de shortcode alleen op gewenste pagina's.

**Q: Werkt de plugin zonder WooCommerce?**
A: Ja, de plugin werkt volledig zonder WooCommerce. WooCommerce integratie is optioneel.

**Q: Hoe werkt de WooCommerce synchronisatie?**
A: Producten worden automatisch gesynchroniseerd bij wijzigingen (basis versie). Daarnaast kun je individuele producten handmatig synchroniseren via Sync/Unsync knoppen (basis versie). Bulk "Sync Alle Producten" vereist Business+ licentie.

**Q: Kan ik custom fields gebruiken voor product eigenschappen?**
A: Ja, je kunt custom field namen configureren in de instellingen. Zie de WooCommerce Custom Fields sectie voor uitgebreide voorbeelden.

**Q: Worden prijzen real-time bijgewerkt?**
A: Ja, prijs- en voorraadwijzigingen worden automatisch gesynchroniseerd.

### Troubleshooting
1. **Chatbot verschijnt niet**: Controleer of de plugin actief is
2. **API errors**: Controleer je OpenAI API key en credits
3. **Styling problemen**: Controleer op CSS conflicten
4. **JavaScript errors**: Controleer de browser console
5. **WooCommerce sync werkt niet**: Controleer of WooCommerce actief is en "Automatische Sync" ingeschakeld in Instellingen
6. **Individuele producten syncen niet**: Gebruik de handmatige Sync/Unsync knoppen per product
7. **Prijzen worden niet bijgewerkt**: Controleer "Automatische Sync" instelling in AI Chatbot → Instellingen → WooCommerce
8. **Bulk sync werkt niet**: Deze functie vereist Business+ licentie
9. **Admin pagina's laden langzaam**: Cache is geoptimaliseerd (v1.0.0) - herstart WP cache indien nodig
10. **Debug logs vullen zich snel**: Debug logging is nu conditioneel - zet WP_DEBUG uit in productie
11. **Quiz Builder niet beschikbaar**: Deze functie vereist Business+ licentie - upgrade je account
12. **Drag & Drop werkt niet**: Controleer of JavaScript actief is en geen browser errors in de console
13. **Verbindingslijnen verschijnen niet**: Ververs de pagina of gebruik "Zoom Fit" om alles in beeld te krijgen
14. **Export/Import werkt niet**: Controleer bestandsformaat en of het een geldig quiz bestand is
15. **Producten laden niet in Quiz Builder**: Controleer WooCommerce integratie en of producten gesynchroniseerd zijn

### Contact
Voor ondersteuning of vragen:
- Email: support@example.com
- Documentatie: [Link naar uitgebreide docs]
- GitHub: [Link naar repository]

## 🔄 Licentie Quick Reference

### 🚑 Feature Migration Path

**Van Basic naar Business:**
- Unlock Product Quiz/Skin Test
- Volledige Ingrediënten database toegang
- FAQ uitbreiding naar 100 items
- Alle business types beschikbaar

**Van Business naar Enterprise:**
- Document management & upload functionaliteit
- Onbeperkte FAQ items
- Advanced analytics en rapportage
- API monitoring en rate limiting

### 🎯 Tier Beslissingsboom

```
Heb je meer dan 20 FAQ items nodig? → Business+
Wil je Product Quiz functionaliteit? → Business+
Heb je Ingrediënten management nodig? → Business+
Wil je document upload en management? → Enterprise
Heb je advanced analytics nodig? → Enterprise
```

### 📊 Licentie Limieten Overzicht

| Resource | Basic | Business | Enterprise |
|----------|-------|----------|------------|
| FAQ Items | 20 | 100 | Onbeperkt |
| WooCommerce Sync | 50 producten | Onbeperkt | Onbeperkt |
| Ingrediënten Beheer | Read-only | Volledig | Volledig |
| Document Upload | ❌ | ❌ | ✅ |
| Custom Post Types | ❌ | ✅ | ✅ |
| Advanced Analytics | ❌ | ❌ | ✅ |
| API Monitoring | ❌ | ❌ | ✅ |
| Support Level | Community | Priority | Priority |

---

## 📝 Licentie

Deze plugin is gelicenseerd onder de GPL v2 of later.

## 🙏 Credits

- OpenAI voor de AI technologie
- WordPress community voor de basis
- Alle contributors en testers

---

**Versie**: 1.0.0  
**Laatste update**: December 2024  
**Compatibiliteit**: WordPress 5.0+, PHP 7.4+