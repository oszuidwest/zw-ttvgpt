<?php
/*
Plugin Name: Tekst TV GPT
Description: Maakt met OpenAI's GPT een samenvatting van een artikel voor op Tekst TV en plaatst dit in het juiste ACF-veld
Version: 0.5
Author: Raymon Mens
*/

use ZuidWest\TekstTVGPT\OptionsPage;
use ZuidWest\TekstTVGPT\Plugin;

require_once 'src/OptionsPage.php';
require_once 'src/Plugin.php';

if (is_admin()) {
    new OptionsPage();
}

new Plugin();
