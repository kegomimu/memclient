#### MClient - pure php memcached client

[![Build Status](https://travis-ci.org/aidarkolbaev/memclient.svg?branch=master)](https://travis-ci.org/aidarkolbaev/memclient)

A dependency-free, pure PHP client for [Memcached](https://memcached.org/), implementing the
full ASCII protocol (storage, retrieval, CAS, increment/decrement, touch, stats and more) as
supported by current Memcached releases.

#### Getting started

With Composer:

> [!CAUTION]
> Not supported

Or without Composer, just include the single file directly:

```php
require "MClient/Memcached.php";
```

```php
$memcached = new \MClient\Memcached();

// In seconds
$expiration = 30;

// to store data
$memcached->set("key", "value", $expiration);

// store only if the key doesn't already exist
$memcached->add("key", "value", $expiration);

// store only if the key already exists
$memcached->replace("key", "new value", $expiration);

// append/prepend to an existing value
$memcached->append("key", " - appended");
$memcached->prepend("key", "prepended - ");

// to retrieve data
$memcached->get("key");

// retrieve multiple keys at once
$memcached->get(["key1", "key2"]);

// retrieve a value together with its CAS unique token
$memcached->gets("key");

// store data only if it hasn't been modified since it was fetched with gets()
$result = $memcached->gets("key");
$memcached->cas("key", "new value", $result["cas"], $expiration);

// fetch and simultaneously update a key's expiration time
$memcached->gat($expiration, "key");
$memcached->gats($expiration, "key");

// update just the expiration time, without fetching
$memcached->touch("key", $expiration);

// to delete data
$memcached->delete("key");

// increment/decrement a numeric value
$memcached->increment("counter");
$memcached->increment("counter", 5);
$memcached->decrement("counter");
$memcached->decrement("counter", 5);

// invalidate all items
$memcached->flushAll();

// server statistics as an associative array
$memcached->stats();

// server version string
$memcached->version();

// set the server's logging verbosity level
$memcached->verbosity(1);

// to enable asynchronous mode
$memcached->async(true);

// Retrieves data called by get()/gets()/gat()/gats() in asynchronous mode
$memcached->receive();

// gracefully close the connection
$memcached->quit();
```

#### Notes

- `receive()` parses a batch of pending asynchronous requests according to whichever retrieval
  flavor (`get()`/`gat()` vs. `gets()`/`gats()`) was issued last — don't mix the two flavors
  within the same asynchronous batch.
- `stats()` and `version()` always perform a synchronous round trip, regardless of `async()` mode.