# ZuidWest TV Tekst TV GPT

[![Code Quality](https://github.com/oszuidwest/zw-ttvgpt/actions/workflows/lint.yml/badge.svg)](https://github.com/oszuidwest/zw-ttvgpt/actions/workflows/lint.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue.svg)](https://wordpress.org)

WordPress-plugin die de GPT-modellen van OpenAI gebruikt om automatisch korte samenvattingen van artikelen te genereren voor tekst-tv-uitzendingen ('kabelkrant') of teletekst.

![preview](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken

- AI-gemaakte samenvattingen met GPT-modellen van OpenAI (GPT-5.2, GPT-5.1 en GPT-4.1 familie)
- Ondersteuning voor fine-tuned modellen (GPT-4.1 familie)
- Automatische detectie en ondersteuning voor de Chat Completions API en Responses API
- Werkt met zowel de Block Editor (Gutenberg) als de Classic Editor
- Auditfunctionaliteit met overzicht van alle samenvattingen en diff-weergave
- Rate limiting (10 requests per minuut per gebruiker)
- Uitgebreide foutafhandeling en validatie met consistente HTTP status codes
- Integratie met WordPress-admin en ACF

## Installatie en configuratie

### Vereisten
- WordPress 6.5 of hoger
- PHP 8.3 of hoger
- Advanced Custom Fields (ACF) plugin
- OpenAI API key

### Installatie
1. Upload de pluginbestanden naar de map `/wp-content/plugins/zw-ttvgpt/`
2. Activeer de plugin via het scherm **Plugins** in WordPress
3. Ga naar **Instellingen** → **Tekst TV GPT** en configureer de volgende instellingen:
   - OpenAI API key
   - AI-model (standaard: `gpt-5.2`)
   - Maximaal aantal woorden (standaard: 100)
   - Debugmodus (optioneel)

### Modelselectie

De plugin ondersteunt **GPT-5.2**, **GPT-5.1** en de **GPT-4.1 familie** van OpenAI:

| Model | Kwaliteit | Snelheid | Kosten | Context | API | Aanbeveling |
|-------|-----------|----------|--------|---------|-----|-------------|
| `gpt-5.2` | Uitstekend++ | Zeer hoog | Laag | 1M tokens | Responses | **Aanbevolen** |
| `gpt-5.1` | Uitstekend+ | Zeer hoog | Laag | 1M tokens | Responses | Vorige flagship |
| `gpt-4.1` | Uitstekend+ | Hoog | Laag-Gemiddeld | 1M tokens | Chat | Beste kwaliteit GPT-4 |
| `gpt-4.1-mini` | Uitstekend | Zeer hoog | Zeer laag | 1M tokens | Chat | GPT-4 budget |
| `gpt-4.1-nano` | Hoog | Zeer hoog | Zeer laag | 1M tokens | Chat | GPT-4 snelste |

**GPT-5.2** (aanbevolen): Nieuwste flagship model met `reasoning_effort='low'` voor hoogwaardige tekstsamenvattingen met goede snelheid. Intelligentere output dan GPT-5.1 via de Responses API.

**GPT-5.1**: Vorige flagship model, nog steeds uitstekend voor samenvattingen.

**GPT-4.1 familie**: Bewezen betrouwbare modellen met uitstekende kwaliteit via de Chat Completions API. Keuze uit standaard, mini (budget), en nano (snelste) varianten.

### Fine-tuned modellen

De plugin ondersteunt fine-tuned modellen van de GPT-4.1 familie. Fine-tuning is **niet** beschikbaar voor GPT-5 modellen.

Ondersteunde formaten:
- `ft:gpt-4.1:my-org:custom-suffix:id`
- `ft:gpt-4.1-mini:my-org:custom-suffix:id`
- `ft:gpt-4.1-nano:my-org:custom-suffix:id`

Selecteer "Fine-tuned model..." in de dropdown op de instellingenpagina en voer je model-ID in.

> **Let op**: Alleen bovenstaande modellen worden ondersteund. Oudere modellen zoals `gpt-5`, `gpt-4o`, en `gpt-4-turbo` werken niet.

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

### Fine-tuning data export
- Ga naar **Tools** → **Fine-tuning Export** om trainingsdata te exporteren
- Exporteer goedgekeurde samenvattingen in JSONL-formaat voor OpenAI fine-tuning

### Debuggen
Schakel debugmodus in via **Instellingen** → **Tekst TV GPT** om gedetailleerde informatie te loggen:

**Browserconsole** (JavaScript):
- Post-ID en geselecteerde regio's
- Exacte content die naar de API wordt gestuurd
- Lengte van de content in tekens
- Volledige API-respons
- Error codes bij fouten

**Serverlogs** (PHP):
- API-requestdetails (model, woordlimiet)
- Gegenereerde samenvattingsmetadata (woordenaantal)
- Foutmeldingen met context

Open de browserconsole en controleer je PHP-foutlog om de debug-output te zien.

## Ontwikkelaars

### Lokale ontwikkeling
```bash
# Clone repository
git clone https://github.com/oszuidwest/zw-ttvgpt.git
cd zw-ttvgpt

# Installeer dependencies
composer install
npm install

# Run linting
vendor/bin/phpcs                    # PHP CodeSniffer
vendor/bin/phpstan analyse          # PHPStan (level 6)
npm run lint                        # Biome (JS/CSS)

# Auto-fix code style issues
vendor/bin/phpcbf                   # PHP auto-fix
npm run lint:fix                    # Biome auto-fix
```

### Codekwaliteit
- **PHP:** WordPress Coding Standards (WPCS) + PHPStan level 6
- **JavaScript/CSS:** Biome voor linting en formatting
- **CI/CD:** GitHub Actions met automatische tests op PHP 8.3 en 8.4

### Architectuur

De plugin gebruikt PSR-4 autoloading met namespace `ZW_TTVGPT_Core`. Belangrijkste componenten:

- **API Layer**: Automatische detectie Chat Completions (GPT-4.1) vs Responses API (GPT-5.x)
- **Admin UI**: Instellingenpagina, audit overzicht met diff-weergave, fine-tuning export
- **Security**: Rate limiting (10/min/user), nonce verificatie, model whitelist validatie

## Licentie

Deze plugin is gelicenseerd onder de [GNU General Public License v3.0](LICENSE).

## Bijdragen

Bijdragen zijn welkom! Zie onze [GitHub repository](https://github.com/oszuidwest/zw-ttvgpt) voor issues en pull requests.

---

_Deze plugin is ontwikkeld door [Streekomroep ZuidWest](https://www.zuidwesttv.nl). Het bijbehorende WordPress-thema is [gratis beschikbaar](https://github.com/oszuidwest/streekomroep-wp)._
