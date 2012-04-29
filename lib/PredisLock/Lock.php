<?php

/*
 * This file is part of the PredisLock package.
 *
 * (c) Mark Fullbrook <mark.fullbrook@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PredisLock;

use Predis\Client;

/**
 * This class implements the algorithm described in http://redis.io/commands/setnx.
 */
class Lock
{
    /**
     * @var Predis\Client
     */
    protected static $defaultClient;
    
    /**
     * @var string
     */
    protected static $lockPrefix = 'lock:';
    
    /**
     * @var Predis\Client
     */
    protected $client;
    
    /**
     * @var string
     */
    protected $name;
    
    /**
     * @var boolean
     */
    protected $killExpiredProcesses;
    
    /**
     * @var integer The time when this lock expires
     */
    protected $expires;
    
    
    /**
     * Set a default Predis client
     */
    public static function setDefaultClient(Client $client)
    {
        self::$defaultClient = $client;
    }
    
    /**
     * Change the default lock prefix
     */
    public static function setLockPrefix($prefix)
    {
        self::$lockPrefix = $prefix;
    }
    
    /**
     * Class constructor, sets the lock name
     */
    public function __construct($name, $killExpiredProcesses = false)
    {
        $this->name = $name;
        $this->killExpiredProcesses = $killExpiredProcesses;
        
        if (isset(self::$defaultClient)) {
            $this->client = self::$defaultClient;
        }
    }
    
    /**
     * Set a Predis client to use
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }
    
    /**
     * Setter for killExpiredProcesses
     */
    public function setKillExpiredProcesses($value = true)
    {
        $this->killExpiredProcesses = true;
    }
    
    /**
     * Attempt to aquire a lock
     *
     * @param int The duration for which a lock will remain valid
     * @param int The number of attempts that will be made to acquire the lock
     * @param int The duration (in micro seconds) between each attempt
     *
     * @return boolean True if the lock was acquired
     */
    public function acquire($timeout = 60, $retryAttempts = 100, $retryWaitUsec = 100000)
    {
        $timeout       = abs($timeout);
        $retryAttempts = abs($retryAttempts);
        $retryWaitUsec = abs($retryWaitUsec);
        
        $key = $this->getKey();
        $acquired = false;
        $attempts = 0;
        
        do {
            $value = $this->getLockExpirationValue($timeout);
            
            if ($this->client->setnx($key, $value)) {
                // lock acquired
                $acquired = true;
            } else {
                // failed to acquire. If current value has expired attempt to get the lock
                $currentValue = $this->client->get($key);
                
                // has the current lock expired
                if ($this->hasLockValueExpired($currentValue)) {
                    $this->killExpiredProcess($currentValue);
                    
                    $getsetResult = $this->client->getset($key, $value);
                    if ($this->hasLockValueExpired($getsetResult)) {
                        // still expired therefore lock acquired
                        $acquired = true;
                    }
                }
            }
            
            // sleep then try again
            usleep($retryWaitUsec);
            $attempts++;
        } while ($attempts < $retryAttempts && !$acquired);
        
        return $acquired ? $this->acquired($timeout) : false;
    }
    
    /**
     * Record into this object the expiry time
     *
     * @return boolean Always returns true
     */
    protected function acquired($timeout)
    {
        $this->expires = time() + $timeout + 1;
        return true;
    }
    
    /**
     * Release the lock
     *
     * @throws RuntimeException If a lock is not held
     */
    public function release()
    {
        if (!$this->isLocked()) {
            throw new \RuntimeException('Attempting to release a lock that is not held');
        }
        
        // check the expiry has not been reached before deleting the lock
        if (time() < $this->expires) {
            $this->client->del($this->getKey());
            unset($this->expires);
        } else {
            trigger_error(sprintf('A PredisLock was not released before the timeout. Class: %s Lock Name: %s', get_class($this), $this->name), E_USER_WARNING);
        }
    }
    
    /**
     * Do we have a lock?
     * @return boolean
     */
    public function isLocked()
    {
        return isset($this->expires);
    }
    
    /**
     * Get the key used for the lock
     *
     * @return string
     */
    protected function getKey()
    {
        return self::$lockPrefix . $this->name;
    }
    
    /**
     * Get the lock expiration value. Add the timeout to the current time.
     * Process ID also included
     *
     * @param int The duration (in seconds) when this lock will timeout
     *
     * @return string
     */
    protected function getLockExpirationValue($timeout)
    {
        return sprintf('%d:%d', time() + $timeout + 1, posix_getpid());
    }
    
    /**
     * Determine if a lock value has expired
     *
     * @param string The lock value
     *
     * @return boolean
     */
    protected function hasLockValueExpired($value)
    {
        $parts = explode(':', $value);
        return time() > $parts[0];
    }
    
    /**
     * Kill the expired process (if flag set to true)
     *
     * @param string The lock value
     */
    public function killExpiredProcess($expiredLockValue)
    {
        if ($this->killExpiredProcesses) {
            list(, $pid) = explode(':', $expiredLockValue);
            exec("kill $pid");
        }
    }
}