PredisLock
==========

PredisLock is small class that implements a locking pattern using Redis.  As the name suggests, PredisLock is for use with [Predis][predis].

Simple Usage
------------
    
    <?php
    use PredisLock\Lock;
    $lock = new Lock('myLockName');
    
    if ($lock->acquire()) {
        // we have acquired the lock
    } else {
        // the lock was not acquired
    }
    


[predis]: https://github.com/nrk/predis