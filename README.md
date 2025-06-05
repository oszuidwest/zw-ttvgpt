# Tekst TV GPT

De Tekst TV GPT-plugin voor WordPress gebruikt OpenAI's GPT-modellen om automatisch korte versies van artikelen te maken. Deze kunnen gebruikt worden voor een tekst tv-uitzending ('kabelkrant') of teletekst.

![preview](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken
- **AJAX-aangedreven samenvattingsgeneratie:** Genereert samenvattingen en plaatst deze automatisch in het juiste veld.
- **Integratie met OpenAI API:** Maakt gebruik van een van OpenAI's vrij te kiezen taalmodellen.
- **Foutafhandeling:** Handelt fouten netjes af, zoals te korte berichten of ontbrekende API-sleutels.

## Installatie en configuratie
1. Upload de pluginbestanden naar de `/wp-content/plugins/` directory.
2. Activeer de plugin via het _Plugins_ scherm in WordPress.
3. Ga in WordPress naar *Instellingen* > *Tekst TV GPT* en vul de API Key, het aantal woorden en het model (`gpt-3.5-turbo`, `gpt-4o` of `gpt-4-turbo` in.

### Modelselectie
De beste resultaten worden met de GPT 4-varianten behaald. Deze modellen zijn echter trager dan 3.5. De kwaliteit van GPT 3.5 is lager, maar de snelheid is veel acceptabeler. Het beste resultaat wordt met een fine-tuned model behaald.
 
## Gebruik
- Zorg ervoor dat de OpenAI API-sleutel correct is geconfigureerd in de plugininstellingen.
- In de WordPress-editor staat onder het veld voor de tekst tv-versie van een bericht een "Genereer" knop. Door op deze knop te klikken, wordt het proces van samenvattingsgeneratie geactiveerd.
- De gegenereerde samenvatting wordt weergegeven in een speciaal tekstveld.

## Vereisten
- Gebruik van het ZuidWest TV WordPress-thema.
- Classic Editor geactiveerd in WordPress. Block Editor wordt niet ondersteund en is niet getest.
- Een geldige OpenAI API-sleutel.
- WordPress 6.4 of hoger met PHP 8.2 of hoger.

_Deze plug-in is gemaakt als 'first party addon' voor het WordPress-thema van ZuidWest TV. Dit thema is [gratis te downloaden](https://github.com/oszuidwest/streekomroep-wp). Met kleine aanpassingen zou de plugin echter met ieder thema kunnen werken._

## Licentie
Deze plugin is gelicenseerd onder de [LICENTIE](LICENSE) die in de repository is opgenomen.
