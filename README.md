# Tekst TV GPT WordPress Plugin

De Tekst TV GPT-plugin voor WordPress gebruikt OpenAI's GPT-modellen om automatisch korte versies van artikelen te maken. Deze kunnen gebruikt worden voor een tekst tv-uitzending ('kabelkrant') of teletekst.

![preview](https://github.com/oszuidwest/teksttvgpt/assets/6742496/f6c84ab1-edca-4245-bdbd-70c83d6a3e12)

## Kenmerken
- **AJAX-aangedreven samenvattingsgeneratie:** Genereert samenvattingen en plaatst deze automatisch in het juiste veld.
- **Integratie met OpenAI API:** Maakt gebruik van een van OpenAI's vrij te kiezen taalmodellen.
- **Foutafhandeling:** Handelt fouten netjes af, zoals te kote berichten of ontbrekende API-sleutels.

## Installatie en configuratie
1. Upload de pluginbestanden naar de `/wp-content/plugins/` directory.
2. Activeer de plugin via het 'Plugins' scherm in WordPress.
3. Ga in WordPress naar *Instellingen* > *Tekst TV GPT* en vul de API Key, het aantal woorden en het model (`gpt3.5-turbo`, `gpt-4` of `gpt-4-1106-preview` (dit is GPT 4 Turbo) in)

### Modelselectie
De beste resultaten worden met GPT 4 behaald. Dit model is echter traag (20 seconden voor één samenvatting). De kwaliteit van GPT 3.5 is lager, maar de snelheid is veel acceptabeler. GPT 4 Tubo is op moment van schrijven ongetest.
 
## Gebruik
- Zorg ervoor dat de OpenAI API-sleutel correct is geconfigureerd in de plugininstellingen.
- In de WordPress-editor ziet u een "Genereer Samenvatting" knop. Door op deze knop te klikken, wordt het proces van samenvattingsgeneratie geactiveerd.
- De gegenereerde samenvatting wordt weergegeven in een speciaal tekstveld.

## Vereisten
- Gebruik van het ZuidWest TV WordPress-thema.
- Classic Editor geactiveerd in WordPress. Block Editor wordt niet ondersteund en is niet getest.
- Een geldige OpenAI API-sleutel.
- WordPress 6.0 of hoger met PHP 7.4 of hoger.

_Deze plug-in is gemaakt als 'first party addon' voor het WordPress-thema van ZuidWest TV. Dit thema is [gratis te downloaden]([url](https://github.com/oszuidwest/streekomroep-wp)). Met kleine aanpassingen zou de plugin met ieder thema kunnen werken._

## Licentie
Deze plugin is gelicenseerd onder de [LICENTIE](LICENSE) die in de repository is opgenomen.
