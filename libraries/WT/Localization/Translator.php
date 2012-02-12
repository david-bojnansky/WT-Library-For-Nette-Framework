<?php

/**
 * This file is part of WT Library.
 * 
 * WT Library is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software 
 * Foundation, either version 3 of the License, or (at your option) any later version.
 * 
 * WT Library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR 
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with WT Library. If not, see <http://www.gnu.org/licenses/>.
 * 
 * @author    Dávid Bojnanský <david.bojnansky@gmail.com>
 * @copyright Copyright (c) 2012 Dávid Bojnanský
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License
 * @link      http://www.github.com/bojno/ Author's profile
 * @link      http://www.github.com/bojno/WT-Library-For-Nette-Framework/ Source repository of this file
 * @link      http://www.github.com/bojno/WT-Library-For-Nette-Framework/blob/master/WT/Localization/Translator.php Source code of this file
 */
namespace WT\Localization; use \Nette,
                               \Nette\DirectoryNotFoundException,
                               \Nette\FileNotFoundException,
                               \Nette\InvalidArgumentException,
                               \Nette\IOException;

/**
 * Translator based on the Gettext's engine (but not native).
 * 
 * @version 1.0.0 Release Candidate 1
 */
final class Translator implements Nette\Localization\ITranslator {
    
    /**
     * All storable data in cache are stored in this property.
     * 
     * @var array
     */
    private $data = array();
    
    
    
    /**
     * An array of executed parts of this class which cannot be executed again.
     * 
     * @var array
     */
    private $executedParts = array('cleaner' => false, 'constructor' => false);

    
    
    /**
     * An array of functions which calculate plural form.
     * 
     * @var array
     */
    private $calculators = array();

    
    
    /**
     * Constructs an object in establishing a new instance of this class.
     * 
     * @param  string $directory Path to the directory which contains binary-safe MO files (one at least) with translation data. Required.
     * @param  string $primaryLocale According to this locale will be parsed messages which have not created translations yet. The locale must exist. Required.
     * @param  string $defaultLocale According to this locale will be translated messages which have created translations. The locale must exist. Required.
     * @param  \Nette\Caching\IStorage $storage Instance of cache storage where will be stored the loaded storable data. Required.
     * @param  array $dependencies In dependency to this array of dependencies are the loaded storable data stored in cache. Optional.
     * @param  boolean $save Indicates whether store the loaded storable data in cache. Optional.
     * @throws \Nette\DirectoryNotFoundException
     * @throws \Nette\FileNotFoundException
     * @throws \Nette\InvalidArgumentException
     * @throws \Nette\InvalidStateException
     * @throws \Nette\IOException
     */
    function __construct($directory, $primaryLocale, $defaultLocale, Nette\Caching\IStorage $storage, array $dependencies = null, $save = true) {
        
        if ($this->executedParts['constructor'] || !$this->executedParts['constructor'] = true)
            
            // vyvolať chybovú výnimku ak konštruktor tejto triedy už bol zavolaný
            throw new Nette\InvalidStateException("Constructor of class '{$this}' has already been called.");
        
        // ošetriť typ hodnoty danej premennej
        $directory = (string) $directory;
            
        // odstrániť adresárový separátor na pravej strane cesty
        $directory = rtrim($directory, "\x2F");
        $directory = rtrim($directory, "\x5C");
        
        // pridať adresárový separátor na pravú stranu cesty
        $directory .= DIRECTORY_SEPARATOR;
        
        // vytvoriť inštanciu kešovacieho objektu
        $cache = new Nette\Caching\Cache($storage, 'WT.Localization.Translator');
        
        // vypočítať unikátne ID pre aktuálnu inštanciu tejto triedy
        $id = md5($directory);
        
        if ($cache[$id]) {

            // nastaviť získané dáta z kešu do objektovej vlastnosti
            $this->data = $cache[$id];
            
            foreach ($this->data['plurals'] as $locale => $plural) ###
                
                // pridať kalkulačku množného čísla do zoskupenia kalkulačiek
                $this->calculators[$locale] = create_function('$count', $plural['pattern']); ###
            
            // zastaviť ďalšie spracovanie
            return;
        }

        if (!is_dir($directory))

            // vyvolať chybovú výnimku ak zadaný adresár neexistuje
            throw new DirectoryNotFoundException("Directory '{$directory}' does not exist.");

        if (!is_readable($directory))

            // vyvolať chybovú výnimku ak zadaný adresár nie je čitateľný
            throw new DirectoryNotFoundException("Directory '{$directory}' is not readable.");

        // vytvoriť masku pre požadované MO súbory
        $mask = DIRECTORY_SEPARATOR . '[a-z][a-z]' . DIRECTORY_SEPARATOR . '[A-Z][A-Z].mo';

        // nájsť všetky súbory, ktoré spĺňajú zadaný tvar masky
        $files = Nette\Utils\Finder::findFiles($mask)->from($directory);

        if (!$files)

            // vyvolať chybovú výnimku ak zadaný adresár neobsahuje žiadny MO súbor s prekladovými dátami
            throw new FileNotFoundException("Directory '{$directory}' does not contain any MO file with translation data.");
        
        // vytvoriť zoskupenie pre dáta
        $data = array('id' => $id);   

        foreach ($files as $file) {

            if (!preg_match("/[\\\\\\/][a-z]{2}[\\\\\\/][A-Z]{2}\\.mo$/D", $file))

                // vyvolať chybovú výnimku ak menná konvencia súboru nemá správny formát
                throw new FileNotFoundException("Name convention of file '{$file}' has incorrect format.");

            if (!is_readable($file))

                // vyvolať chybovú výnimku ak súbor nie je čitateľný
                throw new FileNotFoundException("File '{$file}' is not readable.");

            if (20 > filesize($file))    
            
                // vyvolať chybovú výnimku ak je súbor poškodený
                throw new IOException("File '{$file}' is broken.");

            // otvoriť súbor bezpečnou cestou
            $fileResource = fopen("safe://{$file}", 'rb'); ###
            
            // rozbaliť dáta z binárneho reťazca
            $header = unpack('L1magic/L1revision/L1count/L1sourceOffset/L1targetOffset', fread($fileResource, 20)); ###

            // premeniť hlavičku na premenné
            extract($header); ###

            // zmeniť desatinné číslo na hexadecimálne
            $magic = dechex($magic);

            if ('950412de' != $magic && 'ffffffff950412de' != $magic || 0 != $revision)

                // vyvolať chybovú výnimku ak je súbor poškodený
                throw new IOException("File '{$file}' is broken.");

            // vytvoriť lokalizáciu pre aktuálnu iteráciu
            $locale = basename(dirname($file)) . "\x2D" . $file->getBasename('.mo');

            // pridať lokalizáciu do zoskupenia lokalizácii v dátach
            $data['locales']["{$file}"] = $locale;

            // vytvoriť zoskupenie pre preklady
            $data['translations'][$locale] = array();    
                
            // vynásobiť dvojkou
            $count *= 2;

            // preskočiť na zdrojového ukazovateľa
            fseek($fileResource, $sourceOffset); ###

            // rozbaliť dáta z binárneho reťazca
            $messages = unpack("\x4C{$count}", fread($fileResource, 4 * $count)); ###

            // preskočiť na cieľového ukazovateľa
            fseek($fileResource, $targetOffset); ###

            // rozbaliť dáta z binárneho reťazca
            $translations = unpack("\x4C{$count}", fread($fileResource, 4 * $count)); ###

            // vydeliť dvojkou
            $count /= 2;
            
            for ($iteration = 0 ; $iteration < $count ; $iteration++) {

                // urobiť základné výpočty
                $length = 1 + $iteration * 2;
                $offset = 2 + $iteration * 2;

                // nastaviť potrebné premenné
                $message     = null;
                $translation = null;

                if ($messages[$length]) { ###

                    // preskočiť na určitého ukazovateľa
                    fseek($fileResource, $messages[$offset]); ###

                    // získať správu zo súboru
                    $message = fread($fileResource, $messages[$length]); ###
                }

                if ($translations[$length]) { ###

                    // preskočiť na určitého ukazovateľa
                    fseek($fileResource, $translations[$offset]); ###

                    // získať preklad zo súboru
                    $translation = fread($fileResource, $translations[$length]); ###

                    if (1 <= $iteration && $message) {

                        // pridať preklad do zoskupenia prekladov v dátach
                        $data['translations'][$locale][$message] = $this->parseText($translation, $locale, $data);

                        // pokračovať ďalšou iteráciou
                        continue;
                    }
                }

                if (1 <= $iteration)

                    // pokračovať ďalšou iteráciou
                    continue;

                if ($message || !$translation || false === strpos($translation, "\x3A"))

                    // vyvolať chybovú výnimku ak je súbor poškodený
                    throw new IOException("File '{$file}' is broken.");

                // odstrániť nadbytočné znaky z prekladu
                $translation = trim($translation);

                // rozdeliť preklad na riadky
                $rows = explode("\n", $translation);

                foreach ($rows as $row) {

                    if (false === strpos($row, "\x3A"))

                        // pokračovať ďalšou iteráciou
                        continue;

                    // rozdeliť riadok na dve časti
                    $pairs = explode("\x3A", $row, 2);

                    // odstrániť nadbytočné znaky z každej časti
                    $pairs[0] = trim($pairs[0]); ###
                    $pairs[1] = trim($pairs[1]); ###

                    // pridať pár do zoskupenia vlastností v dátach
                    $data['properties'][$locale][$pairs[0]] = $pairs[1]; ###
                }

                if (!array_key_exists('Plural-Forms', $data['properties'][$locale])) ###

                    // vyvolať chybovú výnimku ak je súbor poškodený
                    throw new IOException("File '{$file}' is broken.");

                // vytvoriť regulárny výraz pre množné číslo
                $pattern = "/^nplurals\\s*=\\s*([1-6])\\s*;\\s*plural\\s*=\\s*([<(?:\\s%\\d|n!=&)>]+)$/AD";

                if (!preg_match($pattern, $data['properties'][$locale]['Plural-Forms'], $matches)) ###

                    // vyvolať chybovú výnimku ak hodnota tej vlastnosti má nesprávny formát
                    throw new IOException("Value of property 'Plural-Forms' has incorrect format ({$locale}).");

                // pridať počet do zoskupenia plurálov v dátach
                $data['plurals'][$locale]['count'] = (integer) $matches[1]; ###

                // nahradiť medzeru a písmeno n 
                $matches[2] = str_replace(array("\x20", "\x6E"), array(null, '$count'), $matches[2]); ###

                // pridať kód do zoskupenia plurálov v dátach
                $data['plurals'][$locale]['pattern'] = "\$count=(int)\$count;return(int)({$matches[2]});"; ###
            }
            
            // zatvoriť otvorený súbor
            fclose($fileResource); ###
        }
        
        // ošetriť typy hodnôt daných premenných
        $primaryLocale = (string) $primaryLocale;
        $defaultLocale = (string) $defaultLocale;
        
        if (!in_array($primaryLocale, $data['locales'])) ###
            
            // vyvolať chybovú výnimku ak lokalizácia neexistuje
            throw new InvalidArgumentException("Locale '{$primaryLocale}' does not exist.");
        
        if (!in_array($defaultLocale, $data['locales'])) ###
            
            // throw an error exception if the locale does not exist
            throw new InvalidArgumentException("Locale '{$defaultLocale}' does not exist.");
        
        // pridať primárnu a predvolenú lokalizáciu do zoskupenia dát
        $data['primaryLocale'] = $primaryLocale;
        $data['defaultLocale'] = $defaultLocale;
        
        foreach ($data['plurals'] as $locale => $plural) ###

            // pridať kalkulačku množného čísla do zoskupenia kalkulačiek
            $this->calculators[$locale] = create_function('$count', $plural['pattern']); ###
        
        // pridať nazbierané dáta do objektovej vlastnosti
        $this->data = $data;
        
        if ($save)
            
            // uložiť dáta do kešu
            $cache->save($id, $data, $dependencies);
    }

    
    
    /**
     * Gets the whole name of this class.
     * 
     * @return string Returns the whole name of this class always.
     */
    function __toString() {
        
        // vrátiť celé meno tejto triedy
        return get_class($this);
    }

    
    
    /**
     * Gets an array of all available plural form calculators.
     * 
     * @return array Returns an array of all available plural form calculators always.
     */
    function getAllAvailableCalculators() {
        
        // vrátiť zoskupenie všetkých dostupných kalkulačiek
        return $this->calculators;
    }
    
    
    
    /**
     * Gets an array of all available locales.
     * 
     * @param  boolean $flip If this is set to FALSE, it returns an array in '/path/to/the/xx/YY.mo => xx-YY' format, flipped otherwise. Optional.
     * @return array Returns an array of all available locales always.
     */
    function getAllAvailableLocales($flip = false) {
        
        // vrátiť zoskupenie všetkých dostupných lokalizácii
        return !$flip ? $this->data['locales'] : array_flip($this->data['locales']); ###
    }
    
    
    
    /**
     * Gets an array of all available properties.
     * 
     * @return array Returns an array of all available properties always.
     */
    function getAllAvailableProperties() {
        
        // vrátiť zoskupenie všetkých dostupných vlastností
        return $this->data['properties']; ###
    }
    
    
    
    /**
     * Gets all available translations.
     * 
     * @return array Returns an array of all available translations always.
     */
    function getAllAvailableTranslations() {
        
        // vrátiť zoskupenie všetkých dostupných prekladov
        return $this->data['translations']; ###
    }
    
    
    
    /**
     * Gets the plural form calculator.
     * 
     * @param  string|void $locale The locale from which you want to load the plural form calculator. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @param  mixed $default The default value which is returned if the locale does not exist and if the second parameter is set to FALSE. Optional.
     * @return mixed Returns the plural form calculator if the locale exists, value of the third parameter otherwise if the second parameter is set to FALSE.
     * @throws \Nette\InvalidArgumentException
     */
    function getCalculator($locale = null, $throws = true, $default = null) {
        
        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if (!$locale)
            
            // nastaviť predvolenú lokalizáciu
            $locale = $this->getDefaultLocale();
        
        elseif (!$this->hasLocale($locale, $throws))
               
            // vrátiť predvolenú hodnotu
            return $default;
        
        // získať všetky dostupné kalkulačky
        $calculators = $this->getAllAvailableCalculators();
        
        // vrátiť požadovanú kalkulačku
        return $calculators[$locale]; ###
    }
    
    
    
    /**
     * Gets an unique ID (MD5 hash of the specified directory) of the current object's instance.
     * 
     * @return string Returns an unique ID (MD5 hash of the specified directory) of the current object's instance always.
     */
    function getId() {
        
        // vrátiť unikátne ID
        return $this->data['id']; ###
    }
    
    
    
    /**
     * Gets the default locale.
     * 
     * @return string Returns the default locale always.
     */
    function getDefaultLocale() {
        
        // vrátiť predvolenú lokalizáciu
        return $this->data['defaultLocale']; ###
    }
    
    
    
    /**
     * Gets the primary locale.
     * 
     * @return string Returns the primary locale always.
     */
    function getPrimaryLocale() {
        
        // vrátiť primárnu lokalizáciu
        return $this->data['primaryLocale']; ###
    }
    
    

    /**
     * Gets an array of the properties.
     * 
     * @param  string|void $locale The locale for which you want to load the properties. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @param  mixed $default The default value which is returned if the locale does not exist and if the second parameter is set to FALSE. Optional.
     * @return mixed Returns an array of the properties if the locale exists, value of the third parameter otherwise if the second parameter is set to FALSE.
     * @throws \Nette\InvalidArgumentException
     */
    function getProperties($locale = null, $throws = true, $default = null) {
        
        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if (!$locale)
            
            // nastaviť predvolenú lokalizáciu
            $locale = $this->getDefaultLocale();
        
        elseif (!$this->hasLocale($locale, $throws))
               
            // vrátiť predvolenú hodnotu
            return $default;
        
        // získať všetky dostupné vlastnosti
        $properties = $this->getAllAvailableProperties();
        
        // vrátiť zoskupenie požadovaných vlastností
        return $properties[$locale]; ###
    }
    
    
    
    /**
     * Gets the property value.
     * 
     * @param  string $name The property name from which you want to load the value. Required.
     * @param  string|void $locale The locale from which you want to load the value. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the property or locale do not exist. Optional.
     * @param  mixed $default The default value which is returned if the property or locale do not exist and if the third parameter is set to FALSE. Optional.
     * @return mixed Returns the property value if the property exists, value of the fourth parameter otherwise if the third parameter is set to FALSE.
     * @throws \Nette\InvalidArgumentException
     */
    function getProperty($name, $locale = null, $throws = true, $default = null) {

        // ošetriť typy hodnôt daných premenných
        $name   = (string) $name;
        $locale = (string) $locale;
        
        if (!$this->hasProperty($name, $locale, $throws))
            
            // vrátiť predvolenú hodnotu
            return $default;
        
        // získať všetky vlastnosti podľa zadanej lokalizácie
        $properties = $this->getProperties($locale, true); ###
        
        // vrátiť hodnotu požadovanej vlastnosti
        return $properties[$name]; ###
    }
     
    
    
    /**
     * Gets the translation.
     * 
     * @param  string $message The message for which you want to load the translation. Required.
     * @param  string|void $locale The locale for which you want to load the translation. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the message or locale do not exist. Optional.
     * @param  mixed $default The default value which is returned if the message or locale do not exist and if the third parameter is set to FALSE. Optional.
     * @return mixed Returns the translation if the message exists, value of the fourth parameter otherwise if the third parameter is set to FALSE.
     * @throws \Nette\InvalidArgumentException
     */
    function getTranslation($message, $locale = null, $throws = true, $default = null) {
        
        // ošetriť typy hodnôt daných premenných
        $message = (string) $message;
        $locale  = (string) $locale;
        
        if (!$this->hasTranslation($message, $locale, $throws))
            
            // vrátiť predvolenú hodnotu
            return $default;
        
        // získať všetky preklady podľa zadanej lokalizácie
        $translations = $this->getTranslations($locale, true); ###
        
        // vrátiť požadovaný preklad
        return $translations[$message]; ###
    }
    
    
    
    /**
     * Gets an array of the translations.
     * 
     * @param  string|void $locale The locale for which you want to load the translations. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @param  mixed $default The default value which is returned if the locale does not exist and if the second parameter is set to FALSE. Optional.
     * @return mixed Returns an array of the translations if the locale exists, value of the third parameter otherwise if the second parameter is set to FALSE.
     * @throws \Nette\InvalidArgumentException
     */
    function getTranslations($locale = null, $throws = true, $default = null) {

        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if (!$locale)
            
            // nastaviť predvolenú lokalizáciu
            $locale = $this->getDefaultLocale();
        
        elseif (!$this->hasLocale($locale, $throws))
               
            // vrátiť predvolenú hodnotu
            return $default;
        
        // získať všetky dostupné preklady
        $translations = $this->getAllAvailableTranslations();
        
        // vrátiť zoskupenie požadovaných prekladov
        return $translations[$locale]; ###
    }
    
    
    
    /**
     * Checks whether the locale exists.
     * 
     * @param  string $locale The locale which you want to check. Required.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @return boolean Returns TRUE if the locale exists, FALSE otherwise.
     * @throws \Nette\InvalidArgumentException
     */
    function hasLocale($locale, $throws = false) {
        
        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if (in_array($locale, $this->getAllAvailableLocales(false)))
                
            // vrátiť pravdivú hodnotu
            return true;
        
        if (!$throws)
            
            // vrátiť nepravdivú hodnotu
            return false;
        
        // vyvolať chybovú výnimku ak lokalizácia neexistuje
        throw new InvalidArgumentException("Locale '{$locale}' does not exist.");
    }
    
    
    
    /**
     * Checks whether the property exists.
     * 
     * @param  string $name The property name which you want to check. Required.
     * @param  string|void $locale The property name will be checked for this locale. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the property or locale do not exist. Optional.
     * @return boolean Returns TRUE if the property exists, FALSE otherwise.
     * @throws \Nette\InvalidArgumentException
     */
    function hasProperty($name, $locale = null, $throws = false) {

        // ošetriť typy hodnôt daných premenných
        $name   = (string) $name;
        $locale = (string) $locale;
        
        // získať vlastnosti podľa zadanej lokalizácie
        $properties = $this->getProperties($locale, $throws, array());
        
        if (array_key_exists($name, $properties))
            
            // vrátiť pravdivú hodnotu
            return true;
        
        if (!$throws)
            
            // vrátiť nepravdivú hodnotu
            return false;
        
        if (!$locale)
            
            // nastaviť predvolenú lokalizáciu
            $locale = $this->getDefaultLocale();
        
        // vyvolať chybovú výnimku ak vlastnosť neexistuje
        throw new InvalidArgumentException("Property '{$name}' does not exist ({$locale}).");
    }
    
    
    
    /**
     * Checks whether the message has translation.
     * 
     * @param  string $message The message which you want to check. Required.
     * @param  string|void $locale The message will be checked for this locale. Optional.
     * @param  boolean $throws Indicates whether throw an error exception if the translation or locale do not exist. Optional.
     * @return boolean Returns TRUE if the message has translation, FALSE otherwise.
     * @throws \Nette\InvalidArgumentException
     */
    function hasTranslation($message, $locale = null, $throws = false) {

        // ošetriť typy hodnôt daných premenných
        $message = (string) $message;
        $locale  = (string) $locale;
        
        // získať preklady podľa zadanej lokalizácie
        $translations = $this->getTranslations($locale, $throws, array());
        
        if (array_key_exists($message, $translations))
        
            // vrátiť pravdivú hodnotu
            return true;
        
        if (!$throws)
            
            // vrátiť nepravdivú hodnotu
            return false;
        
        if (!$locale)
            
            // nastaviť predvolenú lokalizáciu
            $locale = $this->getDefaultLocale();
        
        // vyvolať chybovú výnimku ak správa zatiaľ nemá preklad
        throw new InvalidArgumentException("Message '{$message}' has not translation yet ({$locale}).");
    }
    
    
    
    /**
     * Checks whether the locale has translations.
     * 
     * @param  string|void $locale The locale for which you want to check it. Required.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @return boolean Returns TRUE if the locale has translations, FALSE otherwise.
     * @throws \Nette\InvalidArgumentException
     */
    function hasTranslations($locale = null, $throws = false) {

        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        // vrátiť logickú hodnotu
        return (boolean) $this->getTranslations($locale, $throws, false);
    }
    
    
    
    /**
     * Parses the text on singular or plural form.
     * 
     * @param  string $text The text which you want to parse. Required.
     * @param  string $locale According to this locale will be parsed the text. The locale must exist. Required.
     * @param  array $data An array of the data which must contain info about plural form. Optional.
     * @return string|array Returns either singular form as a string or plural form as an array.
     */
    private function parseText($text, $locale, array $data) {
        
        // ošetriť typy hodnôt daných premenných
        $text   = (string) $text;
        $locale = (string) $locale;
        
        if (!$data)
         
            // nastaviť dáta z objektovej vlastnosti
            $data = $this->data;

        // nastaviť počet možných plurálov
        $count = $data['plurals'][$locale]['count'] - 1; ###

        if (!($count && false !== strpos($text, "\x5B") && false !== strpos($text, "\x7C") && false !== strpos($text, "\x5D")))
            
            // vrátiť pôvodný text
            return $text;

        // vytiahnúť všetky útržky spĺňajúce daný regulárny výraz
        $matches = Nette\Utils\Strings::matchAll($text, "/(?<!\\\\)\\[((?(?![[\\]]).|(?<=\\\\).)+)(?<!\\\\)\\]/");

        // vytvoriť zoskupenie pre texty
        $texts = array();
        
        foreach ($matches as $matches) {

            if (!preg_match("/^(?(?!\\|).|(?<=\\\\).)*(?:(?<!\\\\)\\|(?(?!\\|).|(?<=\\\\).)*){{$count}}$/AD", $matches[1])) ###
                    
                // pokračovať ďalšou iteráciou
                continue;
            
            // rozdeliť reťazec na viac častí
            $parts = preg_split("/(?<!\\\\)\\|/", $matches[1]); ###
            
            foreach ($parts as $iteration => $part) {
                
                // získať text podľa aktuálnej iterácie
                $text = array_key_exists($iteration, $texts) ? $texts[$iteration] : $text;
                
                // nahradiť útržok určitou časťou v danom texte
                $texts[$iteration] = str_replace($matches[0], $part, $text); ###
            }
        }
        
        if (!$texts)
            
            // pridať text do zoskupenia textov
            $texts[] = $text;
        
        foreach ($texts as &$text) {
            
            if (false === strpos($text, '\\'))
                
                // pokračovať ďalšou iteráciou
                continue;
            
            if (false !== strpos($text, ':\\:'))
                
                // nahradiť modifikované spätné lomítko za obyčajné
                $text = str_replace(':\\:', '\\', $text);
            
            if (false === strpos($text, '\\'))
                
                // pokračovať ďalšou iteráciou
                continue;
                
            // nahradiť modifikované značky za obyčajné
            $text = str_replace("\\[", "\x5B", $text);
            $text = str_replace("\\|", "\x7C", $text);
            $text = str_replace("\\]", "\x5D", $text);
        }
        
        if (1 == count($texts))
        
            // získať prvého člena
            $texts = array_shift($texts);
            
        // vrátiť jeden alebo viac textov
        return $texts;
    }
    
    
    
    /**
     * Removes all unused locales. It is useful if you want to free up memory only. It should be used when there are a lot of translation data.
     * 
     * @return \WT\Localization\Translator Returns an instance of this class always.
     */
    function removeAllUnusedLocales() {
        
        if ($this->executedParts['cleaner'] || !$this->executedParts['cleaner'] = true)
            
            // vrátiť inštanciu tejto triedy
            return $this;
        
        // nastaviť primárnu a predvolenú lokalizáciu
        $primaryLocale = $this->getPrimaryLocale();
        $defaultLocale = $this->getDefaultLocale();
        
        foreach ($this->getAllAvailableLocales(false) as $file => $locale) {
            
            if ($primaryLocale == $locale || $defaultLocale == $locale)
                
                // pokračovať ďalšou iteráciou
                continue;
            
            // odstrániť lokalizáciu
            unset($this->data['locales'][$file]);
            
            // odstrániť preklady
            unset($this->data['translations'][$locale]);
            
            // odstrániť vlastnosti
            unset($this->data['properties'][$locale]);
            
            // odstrániť množné číslo
            unset($this->data['plurals'][$locale]);
            
            // odstrániť kalkulačku
            unset($this->calculators[$locale]);
        }
        
        // vrátiť inštanciu tejto triedy
        return $this;
    }
    
    
    
    /**
     * Sets the default locale.
     * 
     * @param  string $locale The locale which you want to set as default. Required.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @return \WT\Localization\Translator Returns an instance of this class always.
     * @throws \Nette\InvalidArgumentException
     */
    function setDefaultLocale($locale, $throws = true) {
        
        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if ($this->hasLocale($locale, $throws))
                
            // nastaviť predvolenú lokalizáciu
            $this->data['defaultLocale'] = $locale;
        
        // vrátiť inštanciu tejto triedy
        return $this;
    }
    
    
    
    /**
     * Sets the primary locale.
     * 
     * @param  string $locale The locale which you want to set as primary. Required.
     * @param  boolean $throws Indicates whether throw an error exception if the locale does not exist. Optional.
     * @return \WT\Localization\Translator Returns an instance of this class always.
     * @throws \Nette\InvalidArgumentException
     */
    function setPrimaryLocale($locale, $throws = true) {
        
        // ošetriť typ hodnoty danej premennej
        $locale = (string) $locale;
        
        if ($this->hasLocale($locale, $throws))
                
            // nastaviť primárnu lokalizáciu
            $this->data['primaryLocale'] = $locale;
        
        // vrátiť inštanciu tejto triedy
        return $this;
    }
    
    
    
    /**
     * Translates the given message.
     * 
     * @param  string $message The message which should be translated.
     * @param  mixed $count Determines the plural form. A keyword ':count:' will be replaced with this number if the keyword is used.
     * @return string Returns the translation if the message has one, original parsed message otherwise.
     * @throws \Nette\InvalidArgumentException
     */
    function translate($message, $count = null) {
        
        // ošetriť typ hodnoty danej premennej
        $message = (string) $message;
        
        if (!$message)
            
            // vrátiť prázdny reťazec
            return '';
        
        if (!is_int($count) && !is_float($count))
            
            // ošetriť typ hodnoty danej premennej
            $count = null;
        
        // nastaviť predvolenú a primárnu lokalizáciu
        $defaultLocale = $this->getDefaultLocale();
        $primaryLocale = $this->getPrimaryLocale();
        
        if ($this->hasTranslation($message, $defaultLocale, false)) { ###
                
            // získať požadovaný preklad
            $translation = $this->getTranslation($message, $defaultLocale, true); ###
            
            // nastaviť hlavnú lokalizáciu
            $locale = $defaultLocale;
        }
        elseif ($defaultLocale != $primaryLocale && $this->hasTranslation($message, $primaryLocale, false)) { ###
        
            // získať požadovaný preklad
            $translation = $this->getTranslation($message, $primaryLocale, true); ###
            
            // nastaviť hlavnú lokalizáciu
            $locale = $primaryLocale;
        }    
        else {
            
            // vyparsovať správu podľa primárnej lokalizácie
            $translation = $this->parseText($message, $primaryLocale, $this->data); ###
            
            // nastaviť hlavnú lokalizáciu
            $locale = $primaryLocale;
        }

        if (is_numeric($count) && is_array($translation)) {

            // získať kalkulačku množného čísla
            $calculator = $this->getCalculator($locale, true); ### 

            // vypočítať index
            $index = $calculator($count);

            if (0 > $index || $this->data['plurals'][$locale]['count'] <= $index) ###

                //
                throw new \Exception;

            // nastaviť preklad podľa indexu
            $translation = $translation[$index]; ### 
        }
        elseif (is_array($translation))

            // získať prvého člena
            $translation = array_shift($translation);
        
        // ošetriť typ hodnoty danej premennej
        $translation = (string) $translation;
        
        if (is_numeric($count) && false !== strpos($translation, ':count:'))
                    
            // nahradiť kľúčové slovo počtu zadaným číslom
            $translation = str_replace(':count:', $count, $translation);
        
        if (func_num_args() > 2 && false !== strpos($translation, "\x25")) {
            
            // získať zadané hodnoty jednotlivých argumentov
            $arguments = func_get_args();
            
            // odstrániť prvé dva argumenty
            array_shift($arguments);
            array_shift($arguments);
            
            // vrátiť preklad
            return vsprintf($translation, $arguments);
        }  
            
        // vrátiť preklad
        return $translation;
    }
}
