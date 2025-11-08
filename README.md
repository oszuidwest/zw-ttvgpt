# ZuidWest TV Tekst TV GPT

[![Code Quality](https://github.com/oszuidwest/teksttvgpt/actions/workflows/test.yml/badge.svg)](https://github.com/oszuidwest/teksttvgpt/actions/workflows/test.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)

WordPress-plugin die OpenAI's GPT-modellen gebruikt om automatisch korte samenvattingen van artikelen te genereren voor tekst-tv-uitzendingen ('kabelkrant') of teletekst.

![preview](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken

- AI-gestuurde samenvattingen met OpenAI GPT-modellen
- AJAX-interface voor realtime generatie
- Werkt met zowel de Block Editor (Gutenberg) als de Classic Editor
- Auditfunctionaliteit met overzicht van alle samenvattingen en diff-weergave
- Uitgebreide foutafhandeling en validatie
- Integratie met WordPress-admin en ACF

## Installatie en configuratie

### Vereisten
- WordPress 6.0 of hoger
- PHP 8.2 of hoger
- Advanced Custom Fields (ACF) plugin
- OpenAI API-sleutel

### Installatie
1. Upload de pluginbestanden naar de map `/wp-content/plugins/zw-ttvgpt/`
2. Activeer de plugin via het scherm _Plugins_ in WordPress
3. Ga naar **Instellingen** → **Tekst TV GPT** en configureer de volgende instellingen:
   - OpenAI API-sleutel
   - AI-model (aanbevolen: `gpt-4.1-mini`)
   - Maximaal aantal woorden (standaard: 100)
   - Debugmodus (optioneel)

### Modelselectie
| Model | Kwaliteit | Snelheid | Kosten | Context | Aanbeveling |
|-------|-----------|----------|--------|---------|-------------|
| `gpt-4.1` | Uitstekend+ | Hoog | Laag-Gemiddeld | 1M tokens | **Nieuwste (Jan 2025)** |
| `gpt-4.1-mini` | Uitstekend | Zeer hoog | Zeer laag | 1M tokens | **Aanbevolen** |
| `gpt-4.1-nano` | Hoog | Zeer hoog | Zeer laag | 1M tokens | Snelste optie |
| `gpt-4o` | Uitstekend | Hoog | Gemiddeld | 128K tokens | Stabiel |
| `gpt-4o-mini` | Hoog | Zeer hoog | Laag | 128K tokens | Budget |

De **2025-modellen** bieden tot 1 miljoen tokens context, 26-83% lagere kosten en aanzienlijke kwaliteitsverbeteringen voor tekstsamenvattingen.

## Gebruik

### Samenvattingen genereren
1. Open een bericht in de WordPress-editor
2. Scroll naar het ACF-veld voor de tekst-tv-samenvatting
3. Klik op de knop **Genereer**
4. Wacht terwijl de AI de samenvatting genereert
5. Controleer en bewerk de samenvatting indien nodig
6. Sla het bericht op

### Auditoverzicht
- Ga naar **Tools** → **Tekst TV Audit** voor een volledig overzicht van alle samenvattingen
- Filter op type: Handmatig, AI (onbewerkt) of AI (bewerkt)
- Filter op wijzigingspercentage: Laag (≤20%), Gemiddeld (21-50%) of Hoog (>50%)
- Bekijk de diff-weergave door op **Bekijk diff** te klikken

### Debuggen
Schakel debugmodus in via **Instellingen** → **Tekst TV GPT** om gedetailleerde informatie te loggen:

**Browser Console** (JavaScript):
- Post ID en geselecteerde regio's
- Exacte content die naar de API wordt gestuurd
- Content lengte in tekens
- Volledige API response

**Server Logs** (PHP):
- API request details (model, word limit)
- Generated summary metadata (word count)
- Error messages met context

Open de browser console en check je PHP error log om de debug output te zien.

## Ontwikkelaars

### Lokale ontwikkeling
```bash
# Clone repository
git clone https://github.com/oszuidwest/teksttvgpt.git
cd teksttvgpt

# Installeer dependencies
composer install
npm install

# Run linting en tests
composer test
npm run lint

# Auto-fix code style issues
composer fix
npm run lint:fix
```

### Codekwaliteit
- **PHP:** WordPress Coding Standards + PHPStan niveau max
- **JavaScript:** WordPress ESLint-standaarden + ESLint 9 flat config
- **CSS:** WordPress Stylelint-standaarden + property ordering
- **CI/CD:** GitHub Actions met automatische tests op PHP 8.2, 8.3 en 8.4

### Architectuur
```
includes/
├── class-admin.php              # WordPress-admininterface
├── class-api-handler.php        # OpenAI API-communicatie
├── class-audit-page.php         # Auditfunctionaliteit
├── class-settings-manager.php   # Plugin-instellingen
├── class-logger.php             # Debuglogging
└── class-helper.php             # Hulpfuncties

assets/
├── admin.js                     # AJAX-interface + typanimaties
├── admin.css                    # Adminstyling
└── audit.css                    # Auditpagina-styling
```

## Licentie

Deze plugin is gelicenseerd onder de [GNU General Public License v3.0](LICENSE).

## Bijdragen

Bijdragen zijn welkom! Zie onze [GitHub repository](https://github.com/oszuidwest/teksttvgpt) voor issues en pull requests.

---

_Deze plugin is ontwikkeld door [Streekomroep ZuidWest](https://www.zuidwesttv.nl). Het bijbehorende WordPress-thema is [gratis beschikbaar](https://github.com/oszuidwest/streekomroep-wp)._
