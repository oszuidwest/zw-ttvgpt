# ZW Tekst TV GPT

WordPress plugin voor het automatisch genereren van Tekst TV samenvattingen met OpenAI GPT.

## Features

- **AI-gestuurde samenvattingen**: Genereert automatisch Nederlandse samenvattingen met OpenAI GPT
- **Typing animatie**: ChatGPT-stijl typing effect voor gegenereerde content  
- **ACF integratie**: Directe knop in ACF velden voor eenvoudig gebruik
- **Rate limiting**: 10 requests per minuut bescherming
- **Multi-editor ondersteuning**: Werkt met TinyMCE, Gutenberg en textarea editors

## Vereisten

- WordPress 6.0+
- PHP 8.2+
- Advanced Custom Fields (ACF)
- OpenAI API key

## Installatie

1. Upload naar `/wp-content/plugins/zw-ttvgpt/`
2. Activeer de plugin
3. Ga naar Instellingen > ZW Tekst TV GPT
4. Voer je OpenAI API key in

## Configuratie

**API Instellingen**:
- OpenAI API key (verplicht)
- Model: Standaard GPT-4o, ondersteunt custom models
- Woordlimiet: 50-500 woorden

**Debug Modus**: Uit voor productie, aan voor troubleshooting (logt naar PHP error log)

## Gebruik

1. Open een post in de editor
2. Gebruik de "Genereer Samenvatting" knop in de ACF meta box of onder het ACF veld
3. De samenvatting wordt automatisch gegenereerd met typing animatie
4. Geselecteerde regio's worden automatisch toegevoegd

## Development

```bash
# Setup
composer install

# Code quality
composer test               # Alle checks (PHP + JS)
composer phpcs              # PHP CodeSniffer  
composer phpstan            # PHPStan level max
composer lint:js            # ESLint strict

# Fixes
composer fix               # Auto-fix PHP + JS issues
```

**Code Standards**: WordPress coding standards, PHPStan level max, ESLint strict mode

## Changelog

### 0.9.0
- Beta release met verbeterde timing en animaties
- Optimized typing speeds en loading message flow
- Production-ready code quality (PHPStan max, strict linting)
- Enhanced error handling en type safety

## Licentie

GPL v3+ - Ontwikkeld door [Streekomroep ZuidWest](https://www.zuidwesttv.nl)