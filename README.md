PredisLock
==========

PredisLock is small class that implements a locking pattern using Redis.  As the name suggests, PredisLock is for use with [Predis][predis].

Description
-----------

* The class uses the [setnx][setnx] command to attempt to acquire a lock.  In fact the class is based on the pattern described on the [setnx command page][setnx].
* The class handles deadlocks by using a timeout.  When the calling code attempts to acquire the lock it must specify what the timeout will be.  After the timeout expires a new lock can be acquired.
* Built in retry. By default acquire() will reattempt to acquire the lock 100 times, waiting 1/10th second between each attempt.  You can change this behaviour by passing arguments to acquire().

Usage
-----

### Simple Usage  
    <?php
    use PredisLock\Lock;
    $lock = new Lock('myLockName');
    
    if ($lock->acquire()) {
        // we have acquired the lock
        // do something.
        $lock->release();
    } else {
        // the lock was not acquired.
    }
    
### Acquire function signature
    <?php
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
    


[predis]: https://github.com/nrk/predis
[setnx]: http://redis.io/commands/setnx