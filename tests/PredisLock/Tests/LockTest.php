<?php

/*
 * This file is part of the PredisLock package.
 *
 * (c) Mark Fullbrook <mark.fullbrook@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PredisLock\Tests;

use PredisLock\Lock;
use Predis\Client;

/**
 * Expose some protected properties and functions
 */
class TestLock extends Lock
{
    public static function getDefaultClient()
    {
        return self::$defaultClient;
    }
    
    public static function getLockPrefix()
    {
        return self::$lockPrefix;
    }
    
    public $client;
    public $name;
    
    public function getKey()
    {
        return parent::getKey();
    }
    
    public function getLockExpirationValue($timeout)
    {
        return parent::getLockExpirationValue($timeout);
    }
    
    public function hasLockValueExpired($value)
    {
        return parent::hasLockValueExpired($value);
    }
}

class LockTest extends \PHPUnit_Framework_TestCase
{
    const PREFIX = 'PredisLock_Tests:';
    
    protected $client;
    
    public function setUp()
    {
        $this->client = new Client(array(), array('prefix' => self::PREFIX));
    }
    
    public function tearDown()
    {
        // cleanup any keys in Redis
        $keys = $this->client->keys('*');
        foreach ($keys as $key) {
            $this->client->del(substr($key, strlen(self::PREFIX)));
        }
        
        unset($this->client);
    }
    
    /**
     * @covers PredisLock\Lock::setDefaultClient
     */
    public function testSetDefaultClient()
    {
        Lock::setDefaultClient($this->client);
        $lock = new TestLock('test');
        $this->assertEquals($this->client, TestLock::getDefaultClient());
    }

    /**
     * @covers PredisLock\Lock::setLockPrefix
     */
    public function testSetLockPrefix()
    {
        Lock::setLockPrefix('NewPrefix');
        $this->assertEquals('NewPrefix', TestLock::getLockPrefix());
    }

    /**
     * @covers PredisLock\Lock::__construct
     */
    public function testConstructor()
    {
        Lock::setDefaultClient($this->client);
        $lock = new TestLock('test');
        $this->assertEquals($this->client, $lock->client);
        $this->assertEquals('test', $lock->name);
    }
    
    /**
     * @covers PredisLock\Lock::setClient
     */
    public function testSetClient()
    {
        $lock = new TestLock('test');
        $client = new Client();
        $lock->setClient($client);
        $this->assertEquals($client, $lock->client);
    }
    
    /**
     * @covers PredisLock\Lock::getKey
     */
    public function testGetKey()
    {
        Lock::setLockPrefix('TestPrefix:');
        $lock = new TestLock('test');
        $this->assertEquals('TestPrefix:test', $lock->getKey());
    }
    
    /**
     * @covers PredisLock\Lock::getLockExpirationValue
     */
    public function testGetLockExpirationValue()
    {
        $lock = new TestLock('test');
        $timeout = 60;
        $expires = time() + $timeout + 1;
        $pid = posix_getpid();
        $this->assertEquals("$expires:$pid", $lock->getLockExpirationValue($timeout));
    }
    
    /**
     * @covers PredisLock\Lock::hasLockValueExpired
     */
    public function testHasLockValueExpired()
    {
        $lock = new TestLock('test');
        $this->assertTrue($lock->hasLockValueExpired("1334300677:9999"));
        $this->assertFalse($lock->hasLockValueExpired(time().':9999'));
    }
    
    /**
     * @covers PredisLock\Lock::acquire
     * @covers PredisLock\Lock::acquired
     * @covers PredisLock\Lock::isLocked
     */
    public function testAcquireGetsLock()
    {
        $lock = new TestLock('test');
        $lock->acquire(60);
        $this->assertTrue($lock->isLocked());
        
        // check redis
        $key = $lock->getKey();
        $this->assertTrue($this->client->exists($key));
        $this->assertEquals($lock->getLockExpirationValue(60), $this->client->get($key));
    }
    
    /**
     * @covers PredisLock\Lock::acquire
     */
    public function testLockNotAcquired()
    {
        $lock1 = new Lock('test');
        $this->assertTrue($lock1->acquire(5));
        
        $lock2 = new Lock('test');
        $this->assertFalse($lock2->acquire(5, 5, 100));
    }
    
    /**
     * @covers PredisLock\Lock::release
     */
    public function testLockReleased()
    {
        $lock = new TestLock('test');
        $this->assertTrue($lock->acquire());
        $lock->release();
        
        // check redis
        $key = $lock->getKey();
        $this->assertFalse($this->client->exists($key));
    }
    
    /**
     * @covers PredisLock\Lock::acquire
     */
    public function testLockAcquiredFromExpiredLock()
    {
        $lock1 = new Lock('test'); 
        $this->assertTrue($lock1->acquire(1)); // times out after 1 second
        
        echo '(sleeping 3s)';
        sleep(3);
        
        $lock2 = new Lock('test');
        $this->assertTrue($lock2->acquire(1));
    }
    
    /**
     * @covers PredisLock\Lock::release
     * @expectedException RuntimeException
     */
    public function testReleaseThrowsExceptionWhenNotLocked()
    {
        $lock = new Lock('test');
        $lock->release();
    }
    
    /**
     * @covers PredisLock\Lock::release
     * @expectedException PHPUnit_Framework_Error
     * @expectedExceptionMessage A PredisLock was not released before the timeout. Class: PredisLock\Lock Lock Name: test
     */
    public function testReleaseTriggersErrorWhenLockNotReleased()
    {
        $lock = new Lock('test');
        $lock->acquire(1);
        
        echo 'sleeping (2s)';
        sleep(2);
        $lock->release();
    }
    
    /**
     * @covers PredisLock\Lock::acquire
     */
    public function testLockAcquiredWhilstSpinning()
    {
        /**
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception('Unable to fork a child process');
        } elseif ($pid == 0) {
            echo 'child';
            // child process
            $lock = new Lock('test');
            $this->assertTrue($lock->acquire());
            sleep(2);
            $lock->release();
            exit;
        } else {
            echo 'parent';
            sleep(1);
            // parent process
            $lock = new Lock('test');
            $this->assertTrue($lock->acquire());
        }
        */
    }
}