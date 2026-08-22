# Memclient

A dependency-free, pure PHP client for [Memcached](https://memcached.org/), originally written by [aidarkolbaev](https://github.com/aidarkolbaev/memclient). Updated and improved.

#### Getting started

Just include the single file directly:

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

#### Error handling

The client follows one rule throughout: a **transport or protocol failure** — the connection
drops, a write or read fails, the server takes longer than the configured timeout to respond, or
the server reports `ERROR`/`CLIENT_ERROR`/`SERVER_ERROR` — throws a `\MClient\MemcachedException`.
Everything else is a normal, anticipated outcome and is reported through the method's return
value, exactly as before: a key that doesn't exist, a CAS conflict, `add()` against an existing
key, `increment()` against a non-numeric value, or a malformed key/value/parameter passed in by
the caller all just return `false`/`null`. Constructing a client with an invalid host, port, or
timeout throws PHP's built-in `\InvalidArgumentException`.

```php
try {
    $memcached = new \MClient\Memcached("cache.internal", 11211, 500); // 500ms timeout
    $memcached->set("key", "value", 30);
} catch (\InvalidArgumentException $e) {
    // Bad constructor arguments (host/port/timeout).
} catch (\MClient\MemcachedException $e) {
    // Couldn't connect, lost the connection, timed out, or the server
    // itself reported an error.
}
```

The constructor's third argument bounds *both* the initial connection attempt and every
subsequent request-response round trip (default: 1000ms). Connecting is done through a
non-blocking socket specifically so a slow or unreachable host can't hang the constructor for the
OS's default (often very long) TCP connect timeout.

The client does not retry or reconnect automatically after a failure — that's left to the caller,
since the right retry/backoff policy is an application-level decision.

#### Notes

- `get()`/`gat()` and `gets()`/`gats()` calls may be freely mixed within the same asynchronous
  batch; `receive()` figures out each item's shape (a bare value, or a
  `["value" => ..., "cas" => ...]` pair) from the response itself.
- `stats()` and `version()` always perform a synchronous round trip, regardless of `async()` mode.
- Every numeric argument that ends up in a raw command line (`exptime`, `flags`, the CAS token,
  `flushAll()`'s delay, `verbosity()`'s level) is strictly validated as a plain integer before
  use, so malformed input can never inject extra commands into the connection.
- Retrieval responses are parsed using the byte length declared in each item's header, not by
  searching for delimiter keywords, so a cached value that happens to contain text like `"END"` or
  `"ERROR"` is never misinterpreted as protocol framing.
