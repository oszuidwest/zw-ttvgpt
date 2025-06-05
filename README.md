# ZW Tekst TV GPT

WordPress plugin voor het automatisch genereren van Tekst TV samenvattingen met behulp van OpenAI's GPT modellen.

## Beschrijving

ZW Tekst TV GPT is een WordPress plugin ontwikkeld voor Streekomroep ZuidWest. De plugin integreert met OpenAI's GPT modellen om automatisch korte, duidelijke samenvattingen te genereren van nieuwsartikelen voor gebruik op Tekst TV (kabelkrant).

## Vereisten

- WordPress 6.0 of hoger
- PHP 8.2 of hoger
- Advanced Custom Fields (ACF) plugin
- Een geldige OpenAI API key

## Installatie

1. Download de plugin bestanden
2. Upload de `zw-ttvgpt` map naar `/wp-content/plugins/`
3. Activeer de plugin via het WordPress admin menu
4. Ga naar Instellingen > ZW Tekst TV GPT om je API key in te stellen

## Configuratie

### API Instellingen

1. **OpenAI API Key**: Verkrijg een API key van [OpenAI](https://platform.openai.com/)
2. **Model**: Voer de naam van het gewenste GPT model in (standaard: GPT-4o, ondersteunt ook custom models)
3. **Woordlimiet**: Stel het maximum aantal woorden in voor samenvattingen (50-500)

### Debug Instellingen

**Debug Modus**: Schakel debug logging in voor troubleshooting.
- **Uit (standaard)**: Alleen errors worden gelogd (zonder details)
- **Aan**: Debug berichten en volledige error details worden gelogd

Alle berichten gaan naar de standaard PHP error log, niet naar WordPress debug.log.

### ACF Velden

De plugin werkt met de volgende ACF velden:
- `field_5f21a06d22c58`: Tekst TV samenvatting veld
- `field_66ad2a3105371`: GPT-gegenereerd marker veld (verborgen)

## Gebruik

### In de Post Editor

1. Open een bestaand artikel of maak een nieuwe post
2. In de zijbalk vind je de "Tekst TV Samenvatting Generator" meta box
3. Klik op "Genereer Samenvatting" om een samenvatting te maken
4. De gegenereerde samenvatting wordt getoond en kan worden aangepast

### Directe ACF Integratie

De plugin voegt automatisch een "Genereer Samenvatting" knop toe onder het ACF Tekst TV veld. Deze knop:
- Genereert een samenvatting op basis van de post content
- Voegt geselecteerde regio's toe aan het begin van de samenvatting
- Slaat de samenvatting direct op in het ACF veld met een typing animatie

## Features

- **Automatische samenvatting generatie**: Gebruikt OpenAI's GPT modellen
- **Regio integratie**: Voegt automatisch geselecteerde regio's toe
- **Rate limiting**: Voorkomt overmatig API gebruik (10 requests per minuut)
- **Debug modus**: Optionele uitgebreide logging (standaard uit voor betere performance)
- **Multisite ondersteuning**: Werkt op WordPress multisite installaties

## Development

### Code Standaarden

Dit project volgt WordPress coding standards. Voer de volgende commando's uit:

```bash
# Installeer dependencies
composer install

# Controleer code standaarden
composer phpcs

# Fix automatisch code standaard issues
composer phpcbf

# Voer PHPStan analyse uit
composer phpstan

# Voer alle tests uit
composer test
```

### Structuur

```
zw-ttvgpt/
├── assets/              # JavaScript en CSS bestanden
├── includes/            # PHP classes
│   ├── class-admin.php
│   ├── class-api-handler.php
│   ├── class-logger.php
│   └── class-summary-generator.php
├── zw-ttvgpt.php       # Hoofd plugin bestand
└── uninstall.php       # Cleanup bij verwijdering
```

## Filters en Hooks

De plugin biedt geen publieke filters of hooks in deze versie.

## Changelog

### 1.0.0
- Eerste release
- Basis functionaliteit voor samenvatting generatie
- Integratie met ACF velden
- Rate limiting en debug logging

## Licentie

GPL v3 of later - zie LICENSE bestand voor details.

## Auteur

Ontwikkeld door [Streekomroep ZuidWest](https://www.zuidwesttv.nl)

## Support

Voor vragen of problemen, neem contact op met de technische dienst van ZuidWest TV.