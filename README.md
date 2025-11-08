# ZuidWest TV Tekst TV GPT

[![Code Quality](https://github.com/oszuidwest/teksttvgpt/actions/workflows/test.yml/badge.svg)](https://github.com/oszuidwest/teksttvgpt/actions/workflows/test.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)

WordPress plugin die OpenAI's GPT-modellen gebruikt om automatisch korte versies van artikelen te maken voor tekst tv-uitzendingen ('kabelkrant') of teletekst.

![preview](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken

- AI-gestuurde samenvattingen met OpenAI GPT-modellen
- AJAX-interface voor real-time generatie
- Audit functionaliteit met overzicht van alle samenvattingen en diff-weergave
- Rate limiting (10 requests per minuut per gebruiker)
- Foutafhandeling en validatie
- WordPress admin en ACF integratie

## Installatie en configuratie

### Vereisten
- WordPress 6.0 of hoger
- PHP 8.2 of hoger
- Advanced Custom Fields (ACF) plugin
- OpenAI API-sleutel

**Editor-ondersteuning**: Werkt met zowel de Block Editor (Gutenberg) als de Classic Editor.

### Installatie
1. Upload de pluginbestanden naar de `/wp-content/plugins/zw-ttvgpt/` directory
2. Activeer de plugin via het _Plugins_ scherm in WordPress
3. Ga naar **Instellingen** → **Tekst TV GPT** en configureer:
   - OpenAI API-sleutel
   - AI-model (aanbevolen: `gpt-4.1-mini`)
   - Maximaal aantal woorden (standaard: 100)
   - Debug-modus (optioneel)

### Modelselectie
| Model | Kwaliteit | Snelheid | Kosten | Context | Aanbeveling |
|-------|-----------|----------|--------|---------|-------------|
| `gpt-4.1` | Uitstekend+ | Hoog | Laag-Gemiddeld | 1M tokens | **Nieuwste (Jan 2025)** |
| `gpt-4.1-mini` | Uitstekend | Zeer hoog | Zeer laag | 1M tokens | **Aanbevolen** |
| `gpt-4.1-nano` | Hoog | Zeer hoog | Zeer laag | 1M tokens | Snelste optie |
| `gpt-4o` | Uitstekend | Hoog | Gemiddeld | 128K tokens | Stabiel |
| `gpt-4o-mini` | Hoog | Zeer hoog | Laag | 128K tokens | Budget |

**2025 modellen** bieden tot 1 miljoen tokens context, 26-83% lagere kosten en significante kwaliteitsverbeteringen voor tekst samenvattingen.

## Gebruik

### Samenvattingen genereren
1. Open een bericht in de WordPress-editor
2. Scroll naar het ACF-veld voor de tekst tv-samenvatting
3. Klik op de "Genereer" knop
4. Wacht op de AI-gegenereerde samenvatting
5. Bewerk indien nodig en sla het bericht op

### Audit overzicht
- Ga naar **Tools** → **Tekst TV Audit** voor een overzicht van alle samenvattingen
- Filter op type: Handmatig, AI (onbewerkt), of AI (bewerkt)
- Filter op wijzigingspercentage: Laag (≤20%), Gemiddeld (21-50%), Hoog (>50%)
- Bekijk diff-weergave door op "Bekijk diff" te klikken

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

### Code kwaliteit
- **PHP:** WordPress Coding Standards + PHPStan niveau max
- **JavaScript:** WordPress ESLint standards + ESLint 9 flat config  
- **CSS:** WordPress Stylelint standards + property ordering
- **CI/CD:** GitHub Actions met automatische tests op PHP 8.2, 8.3, 8.4

### Architectuur
```
includes/
├── class-admin.php              # WordPress admin interface
├── class-api-handler.php        # OpenAI API communicatie
├── class-summary-generator.php  # Core samenvatting logica
├── class-audit-page.php         # Audit functionaliteit
├── class-settings-manager.php   # Plugin instellingen
├── class-logger.php             # Debug logging
└── class-helper.php             # Utility functies

assets/
├── admin.js                     # AJAX interface + typing animaties
├── admin.css                    # Admin styling
└── audit.css                    # Audit pagina styling
```

## Licentie

Deze plugin is gelicenseerd onder de [GNU General Public License v3.0](LICENSE).

## Bijdragen

Bijdragen zijn welkom! Zie onze [GitHub repository](https://github.com/oszuidwest/teksttvgpt) voor issues en pull requests.

---

_Deze plugin is ontwikkeld door [Streekomroep ZuidWest](https://www.zuidwesttv.nl) als onderdeel van hun WordPress-thema. Het thema is [gratis beschikbaar](https://github.com/oszuidwest/streekomroep-wp)._
