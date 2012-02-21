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
 * @link      http://www.github.com/bojno/ Author's profile.
 * @link      http://www.github.com/bojno/WT-Library-For-Nette-Framework/ Source repository of this file.
 * @link      http://www.github.com/bojno/WT-Library-For-Nette-Framework/blob/master/libraries/WT/Security/DataEncryptor.php Source code of this file.
 */
namespace WT\Security; use \Nette\DirectoryNotFoundException,
                           \Nette\InvalidArgumentException, 
                           \Nette\NotSupportedException,
                           \Nette\UnexpectedValueException;

/**
 * Data encryptor based on the Hash and Mcrypt's engine.
 * 
 * @version 1.0.0 Release Candidate 1
 */
final class DataEncryptor {

    /**
     * The initialization vector from a random source.
     * 
     * @var string
     */
    private $iv;
    
    
        
    /**
     * The initialization key.
     * 
     * @var string
     */
    private $key;
    
    
    
    /**
     * The encryption descriptor.
     * 
     * @var resource
     */
    private $module;
    
    
    
    /**
     * Constructs an object in establishing a new instance of this class.
     * 
     * Available options:
     * 
     *     - string  $algo       see the parameter {@link http://php.net/hash-hmac           $algo}                 optional  'SHA512'
     *     - string  $cipher     see the parameter {@link http://php.net/mcrypt-module-open  $algorithm}            optional  'MCRYPT_RIJNDAEL_256'
     *     - string  $cipherDir  see the parameter {@link http://php.net/mcrypt-module-open  $algorithm_directory}  optional   null
     *     - string  $key        see the parameter {@link http://php.net/mcrypt-generic-init $key}                  optional   null
     *     - string  $mode       see the parameter {@link http://php.net/mcrypt-module-open  $mode}                 optional  'MCRYPT_MODE_ECB'
     *     - string  $modeDir    see the parameter {@link http://php.net/mcrypt-module-open  $mode_directory}       optional   null
     *     - string  $salt       see the parameter {@link http://php.net/hash-hmac           $key}                  optional  '8zrPlK5sKm9dLHCBMKQZUmtZQdThF...'
     *     - string  $src        see the parameter {@link http://php.net/mcrypt-create-iv    $source}               optional  'MCRYPT_RAND'
     * 
     * See the Mcrypt's {@link http://php.net/mcrypt.ciphers ciphers} and predefined {@link http://php.net/mcrypt.constants constants}.
     * 
     * @param  array $opts An array of the options to configure the current object instance. Optional.
     * @throws \Nette\DirectoryNotFoundException
     * @throws \Nette\InvalidArgumentException
     * @throws \Nette\NotSupportedException
     * @throws \Nette\UnexpectedValueException
     */
    function __construct(array $opts = null) {
        
        // zrušiť pôvodný objekt
        $this->__destruct();
        
        if (!extension_loaded('hash'))
            
            // vyvolať chybovú výnimku, ak rozšírenie nie je načítané
            throw new NotSupportedException("PHP extension 'Hash' is not loaded.");
        
        if (!extension_loaded('mcrypt'))
            
            // vyvolať chybovú výnimku, ak rozšírenie nie je načítané
            throw new NotSupportedException("PHP extension 'Mcrypt' is not loaded.");
        
        // nastaviť hodnoty v závislosti na zoskupení s možnosťami
        $algo      = !empty($opts['algo'])      ? (string) $opts['algo']      : 'SHA512';
        $cipher    = !empty($opts['cipher'])    ? (string) $opts['cipher']    : 'MCRYPT_RIJNDAEL_256';
        $cipherDir = !empty($opts['cipherDir']) ? (string) $opts['cipherDir'] :  null;
        $key       = !empty($opts['key'])       ? (string) $opts['key']       :  null;
        $mode      = !empty($opts['mode'])      ? (string) $opts['mode']      : 'MCRYPT_MODE_ECB';
        $modeDir   = !empty($opts['modeDir'])   ? (string) $opts['modeDir']   :  null;
        $salt      = !empty($opts['salt'])      ? (string) $opts['salt']      : '8zrPlK5sKm9dLHCBMKQZUmtZQdThF9thTHSt7fi87BEkm4kgySFE4qCi2ywnPJq1';
        $src       = !empty($opts['src'])       ? (string) $opts['src']       : 'MCRYPT_RAND';
        
        // získať zoznam dostupných algoritmov
        $algos = hash_algos();
        
        if (!in_array(strtolower($algo), $algos))
            
            // vyvolať chybovú výnimku, ak algoritmus neexistuje
            throw new InvalidArgumentException("Algorithm '{$algo}' does not exist.");
        
        if (!defined($cipher))
            
            // vyvolať chybovú výnimku, ak konštanta nie je definovaná
            throw new InvalidArgumentException("Constant '{$cipher}' is undefined.");    
            
        if ($cipherDir && !is_dir($cipherDir))
            
            // vyvolať chybovú výnimku, ak zadaný adresár neexistuje
            throw new DirectoryNotFoundException("Directory '{$cipherDir}' does not exist.");
         
        if ($cipherDir && !is_readable($cipherDir))
            
            // vyvolať chybovú výnimku, ak zadaný adresár nie je čitateľný
            throw new DirectoryNotFoundException("Directory '{$cipherDir}' is not readable."); // nie som si istý, či daný adresár musí byť čitateľný
            
        if (!$key)
            
            for ($iteration = 1 ; 8 >= $iteration ; $iteration++) {
            
                // vytvoriť náhodné číslo
                $number = mt_rand();
                
                // vytvoriť náhodný 64 znakový kľúč
                $key .= sprintf('%08x', $number);
            }
                
        if (64 > strlen($key))
            
            // vyvolať chybovú výnimku, ak kľúč má menej ako 64 znakov
            throw new InvalidArgumentException('Key must not have less than 64 characters.');
                    
        if (!defined($mode))
            
            // vyvolať chybovú výnimku, ak konštanta nie je definovaná
            throw new InvalidArgumentException("Constant '{$mode}' is undefined.");
            
        if ($modeDir && !is_dir($modeDir))
            
            // vyvolať chybovú výnimku, ak zadaný adresár neexistuje
            throw new DirectoryNotFoundException("Directory '{$modeDir}' does not exist.");
         
        if ($modeDir && !is_readable($modeDir))
            
            // vyvolať chybovú výnimku, ak zadaný adresár nie je čitateľný
            throw new DirectoryNotFoundException("Directory '{$modeDir}' is not readable."); // nie som si istý, či daný adresár musí byť čitateľný
        
        if (64 > strlen($salt))
            
            // vyvolať chybovú výnimku, ak soľ má menej ako 64 znakov
            throw new InvalidArgumentException('Salt must not have less than 64 characters.');
            
        if (!defined($src))
            
            // vyvolať chybovú výnimku, ak konštanta nie je definovaná
            throw new InvalidArgumentException("Constant '{$src}' is undefined.");
            
        // otvoriť šifrovací modul
        $this->module = @mcrypt_module_open(constant($cipher), $cipherDir, constant($mode), $modeDir);
        
        if (false === $this->module)
            
            // vyvolať chybovú výnimku, ak sa nepodarilo otvoriť šifrovací modul
            throw new UnexpectedValueException('Failed to open the encryption module.');
        
        // vytvoriť inicializačný vektor
        $this->iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($this->module), constant($src));
        
        // vytvoriť unikátny kľúč pre šifrovanie
        $this->key = substr(hash_hmac($algo, $key, $salt), 0, mcrypt_enc_get_key_size($this->module));
    }
    
    
    
    /**
     * Destructs the current object instance and closes the opened encryption module.
     */
    function __destruct() {

        if (is_resource($this->module)) {

            // zatvoriť otvorený šifrovací modul
            mcrypt_module_close($this->module);
            
            // uvoľniť hodnoty objektových vlastností
            $this->iv = $this->key = $this->module = null;
        }
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
     * Decrypts the encrypted data.
     * 
     * @param  string $data The encrypted data which you want to decrypt. Required.
     * @param  boolean $decode Indicates whether decode the encrypted data from Base64 string before the decryption. Optional.
     * @param  string $key The initialization key. If it is empty, then is used the default initialization key. Optional.
     * @return string Returns the decrypted data always.
     * @throws \Nette\InvalidArgumentException
     */
    function decrypt($data, $decode = true, $key = null) {

        // ošetriť typy hodnôt daných premenných
        $data = (string) $data;
        $key  = (string) $key;
        
        if ($key && mcrypt_enc_get_key_size($this->module) < strlen($key))
            
            // vyvolať chybovú výnimku, ak kľúč má viac ako povolený počet znakov
            throw new InvalidArgumentException(sprintf('Key must not have more than %d characters.', mcrypt_enc_get_key_size($this->module)));
            
        // nastaviť kľúč pre inicializáciu šifrovacieho modulu
        $key = $key ?: $this->getKey();
        
        // inicializovať otvorený šifrovací modul
        mcrypt_generic_init($this->module, $key, $this->iv);
        
        // dešifrovať požadované dáta
        $data = mdecrypt_generic($this->module, $decode ? base64_decode($data) : $data);
        
        // deinicializovať otvorený šifrovací modul
        mcrypt_generic_deinit($this->module);
        
        // vrátiť dešifrované dáta
        return rtrim($data, "\0");
    }
    
    
    
    /**
     * Encrypts the (decrypted) data.
     * 
     * @param  string $data The (decrypted) data which you want to encrypt. Required.
     * @param  boolean $encode Indicates whether encode the (decrypted) data to Base64 string after the encryption. Optional.
     * @param  string $key The initialization key. If it is empty, then is used the default initialization key. Optional.
     * @return string Returns the encrypted data always.
     * @throws \Nette\InvalidArgumentException
     */
    function encrypt($data, $encode = true, $key = null) {
        
        // ošetriť typy hodnôt daných premenných
        $data = (string) $data;
        $key  = (string) $key;
        
        if ($key && mcrypt_enc_get_key_size($this->module) < strlen($key))
            
            // vyvolať chybovú výnimku, ak kľúč má viac ako povolený počet znakov
            throw new InvalidArgumentException(sprintf('Key must not have more than %d characters.', mcrypt_enc_get_key_size($this->module)));
            
        // nastaviť kľúč pre inicializáciu šifrovacieho modulu
        $key = $key ?: $this->getKey();
        
        // inicializovať otvorený šifrovací modul
        mcrypt_generic_init($this->module, $key, $this->iv);
        
        // zašifrovať požadované dáta
        $data = mcrypt_generic($this->module, $data);
        
        // deinicializovať otvorený šifrovací modul
        mcrypt_generic_deinit($this->module);

        // vrátiť zašifrované dáta
        return $encode ? base64_encode($data) : $data;
    }
    
    
    
    /**
     * Gets an unique key for the encryption.
     * 
     * @return string Returns an unique key for the encription always.
     */
    function getKey() {
        
        // vrátiť unikátny kľúč pre šifrovanie
        return $this->key;
    }
}
