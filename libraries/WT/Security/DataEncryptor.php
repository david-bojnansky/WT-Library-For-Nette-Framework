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
 * @license   http://gnu.org/licenses/gpl-3.0.html GNU General Public License
 * @link      http://github.com/bojno/ Author's profile.
 * @link      http://github.com/bojno/WT-Library-For-Nette-Framework/ Source repository of this file.
 * @link      http://github.com/bojno/WT-Library-For-Nette-Framework/blob/master/libraries/WT/Security/DataEncryptor.php Source code of this file.
 */
namespace WT\Security; use \Nette\DirectoryNotFoundException,
                           \Nette\InvalidArgumentException, 
                           \Nette\NotSupportedException,
                           \Nette\UnexpectedValueException;

/**
 * Data encryptor based on the Hash and Mcrypt's engine.
 * 
 * @version 1.0.0 Release Candidate 2
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
     * The size of the initialization vector of the opened algorithm.
     * 
     * @var integer
     */
    private $size;
    
    
    
    /**
     * Indicates whether transfer the initialization vector with the encrypted data.
     * 
     * @var boolean
     */
    private $transfer;
    
    
    
    /**
     * Constructs an object in establishing a new instance of this class. List of the possible options to configure security:
     * 
     *     string  $algo       see the parameter {@link http://php.net/hash-hmac           $algo}                 optional  'MD5'
     *     string  $cipher     see the parameter {@link http://php.net/mcrypt-module-open  $algorithm}            optional  'MCRYPT_RIJNDAEL_256'
     *     string  $cipherDir  see the parameter {@link http://php.net/mcrypt-module-open  $algorithm_directory}  optional   null
     *     string  $key        see the parameter {@link http://php.net/mcrypt-generic-init $key}                  optional   null
     *     string  $mode       see the parameter {@link http://php.net/mcrypt-module-open  $mode}                 optional  'MCRYPT_MODE_ECB'
     *     string  $modeDir    see the parameter {@link http://php.net/mcrypt-module-open  $mode_directory}       optional   null
     *     string  $salt       see the parameter {@link http://php.net/hash-hmac           $key}                  optional   null
     *     string  $src        see the parameter {@link http://php.net/mcrypt-create-iv    $source}               optional  'MCRYPT_RAND'
     * 
     * See the Mcrypt's {@link http://php.net/mcrypt.ciphers ciphers} and predefined {@link http://php.net/mcrypt.constants constants}.
     * 
     * @param  array $opts An array of the options to configure security. Optional.
     * @return WT\Security\DataEncryptor Returns an instance of this class always.
     * @throws Nette\DirectoryNotFoundException
     * @throws Nette\InvalidArgumentException
     * @throws Nette\NotSupportedException
     * @throws Nette\UnexpectedValueException
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
        $algo      = !empty($opts['algo'])      ? (string) $opts['algo']      : 'MD5';
        $cipher    = !empty($opts['cipher'])    ? (string) $opts['cipher']    : 'MCRYPT_RIJNDAEL_256';
        $cipherDir = !empty($opts['cipherDir']) ? (string) $opts['cipherDir'] :  null;
        $key       = !empty($opts['key'])       ? (string) $opts['key']       :  null;
        $mode      = !empty($opts['mode'])      ? (string) $opts['mode']      : 'MCRYPT_MODE_ECB';
        $modeDir   = !empty($opts['modeDir'])   ? (string) $opts['modeDir']   :  null;
        $salt      = !empty($opts['salt'])      ? (string) $opts['salt']      :  null;
        $src       = !empty($opts['src'])       ? (string) $opts['src']       : 'MCRYPT_RAND';
        
        if (!in_array(strtolower($algo), hash_algos()))
            
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
            
            for ($iteration = 1 ; 8 >= $iteration ; $iteration++)
                
                // vytvoriť náhodný 64 znakový kľúč
                $key .= sprintf('%08x', mt_rand());
                
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
            
        if (!defined($src))
            
            // vyvolať chybovú výnimku, ak konštanta nie je definovaná
            throw new InvalidArgumentException("Constant '{$src}' is undefined.");
            
        // otvoriť šifrovací modul
        $this->module = @mcrypt_module_open(constant($cipher), $cipherDir, constant($mode), $modeDir);
        
        if (false === $this->module)
            
            // vyvolať chybovú výnimku, ak sa nepodarilo otvoriť šifrovací modul
            throw new UnexpectedValueException('Failed to open the encryption module.');
        
        // získať veľkosť inicializačného vektora
        $this->size = mcrypt_enc_get_iv_size($this->module); ###
        
        // vytvoriť inicializačný vektor
        $this->iv = @mcrypt_create_iv($this->size, constant($src));
        
        if (false === $this->iv)
            
            // vyvolať chybovú výnimku, ak sa nepodarilo vytvoriť inicializačný vektor
            throw new UnexpectedValueException('Failed to create the initialization vector.');
        
        // nastaviť logickú hodnotu, či prenášať inicializačný vektor
        $this->transfer = 'MCRYPT_MODE_ECB' != $mode;
        
        // vytvoriť unikátny kľúč pre šifrovanie
        $this->key = substr(hash_hmac($algo, $key, $salt), 0, mcrypt_enc_get_key_size($this->module)); ###
        
        // vrátiť inštanciu tejto triedy
        return $this;
    }
    
    
    
    /**
     * Destructs the current object instance and closes the opened encryption module.
     * 
     * @return WT\Security\DataEncryptor Returns an instance of this class always.
     */
    function __destruct() {

        if (is_resource($this->module)) {

            // zatvoriť otvorený šifrovací modul
            mcrypt_module_close($this->module); ###
            
            // uvoľniť hodnoty objektových vlastností
            $this->iv = $this->key = $this->module = $this->size = $this->transfer = null;
        }
        
        // vrátiť inštanciu tejto triedy
        return $this;
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
     * @param  boolean $strict The second parameter of the Base64 decode function. Optional.
     * @return mixed Returns the decrypted data if the specified data are not empty and they are bigger than size of the initialization vector, NULL otherwise.
     */
    function decrypt($data, $decode = true, $strict = false) {

        // ošetriť typy hodnôt daných premenných
        $data   = (string)  $data;
        $strict = (boolean) $strict;
        
        // dekódovať dáta pred dešifrovaním
        $data = $decode ? base64_decode($data, $strict) : $data;
        
        if (null == $data || $this->transfer && $this->size >= strlen($data))
            
            // vrátiť prázdnu hodnotu
            return;
        
        // nastaviť spoločný inicializačný vektor
        $iv = $this->iv;
        
        if ($this->transfer) {
            
            // získať inicializačný vektor zo zadaných dát
            $iv = substr($data, 0, $this->size);
            
            // získať dáta na dešifrovanie zo zadaných dát
            $data = substr($data, $this->size);
        }
        
        // inicializovať otvorený šifrovací modul
        mcrypt_generic_init($this->module, $this->getKey(), $iv); ###
        
        // dešifrovať požadované dáta
        $data = mdecrypt_generic($this->module, $data); ###
        
        // deinicializovať otvorený šifrovací modul
        mcrypt_generic_deinit($this->module); ###
        
        // vrátiť dešifrované dáta
        return rtrim($data, "\0");
    }
    
    
    
    /**
     * Encrypts the (decrypted) data.
     * 
     * @param  string $data The (decrypted) data which you want to encrypt. Required.
     * @param  boolean $encode Indicates whether encode the (decrypted) data to Base64 string after the encryption. Optional.
     * @return mixed Returns the encrypted data if the specified data are not empty, NULL otherwise.
     */
    function encrypt($data, $encode = true) {
        
        // ošetriť typ hodnoty danej premennej
        $data = (string) $data;

        if (null == $data)
            
            // vrátiť prázdnu hodnotu
            return;
        
        // inicializovať otvorený šifrovací modul
        mcrypt_generic_init($this->module, $this->getKey(), $this->iv); ###
        
        // zašifrovať požadované dáta
        $data = ($this->transfer ? $this->iv : null) . mcrypt_generic($this->module, $data); ###
        
        // deinicializovať otvorený šifrovací modul
        mcrypt_generic_deinit($this->module); ###

        // vrátiť zašifrované dáta
        return $encode ? base64_encode($data) : $data;
    }
    
    
    
    /**
     * Gets an unique key for the encryption. You should keep this key in secret.
     * 
     * @return string Returns an unique key for the encryption always.
     */
    function getKey() {
        
        // vrátiť unikátny kľúč pre šifrovanie
        return $this->key;
    }
}
