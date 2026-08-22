<?php

namespace MClient;

/**
 * Thrown for transport-level failures (unable to connect, socket write/read
 * failures, timeouts, the connection being closed unexpectedly) and for
 * protocol-level errors reported by the server itself (ERROR, CLIENT_ERROR,
 * SERVER_ERROR).
 *
 * Invalid *input* to an operation (a malformed key, a non-numeric increment
 * value, an out-of-range flag, ...) is deliberately not treated as
 * exceptional: those methods simply return false/null, exactly as they did
 * before, so existing calling code keeps working unchanged. This exception
 * is reserved for things a well-formed call cannot anticipate or recover
 * from on its own.
 */
class MemcachedException extends \RuntimeException
{
}

/**
 * Pure PHP Memcached client.
 *
 * Implements the Memcached ASCII protocol as documented at
 * https://github.com/memcached/memcached/blob/master/doc/protocol.txt
 * and is compatible with current Memcached releases (1.6.x).
 *
 * Error handling follows one rule throughout: a transport or protocol
 * failure (the connection drops, a write/read fails, the server takes
 * longer than the configured timeout to respond, or the server reports
 * ERROR/CLIENT_ERROR/SERVER_ERROR) throws a MemcachedException. Everything
 * else — a key that doesn't exist, a CAS conflict, an add() against an
 * existing key, a malformed key or value passed in by the caller — is a
 * normal, anticipated outcome and is reported via the method's return
 * value (false/null), never an exception.
 */
interface MemcachedInterface
{
    /**
     * Memcached set command is used to set a new value to a new or existing key.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool False if $key/$value/$exptime/$flags are invalid.
     * @throws MemcachedException
     */
    public function set($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached add command stores data, but only if the server does not
     * already hold data for this key. Returns false if the key already exists.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     * @throws MemcachedException
     */
    public function add($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached replace command stores data, but only if the server *does*
     * already hold data for this key. Returns false if the key doesn't exist.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     * @throws MemcachedException
     */
    public function replace($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached append command adds data to an existing key, after the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|float|string|bool $value
     * @return bool
     * @throws MemcachedException
     */
    public function append($key, $value);

    /**
     * Memcached prepend command adds data to an existing key, before the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|float|string|bool $value
     * @return bool
     * @throws MemcachedException
     */
    public function prepend($key, $value);

    /**
     * Memcached cas (check-and-set) command stores data, but only if no one
     * else has updated the key since it was last fetched with gets()/gats().
     * Returns false if the CAS value is stale (EXISTS) or the key is
     * missing (NOT_FOUND).
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $casUnique The CAS token obtained from gets()/gats()
     * @param int $exptime
     * @param int $flags
     * @return bool
     * @throws MemcachedException
     */
    public function cas($key, $value, $casUnique, $exptime = 0, $flags = 0);

    /**
     * Memcached get command is used to get the value stored at key.
     * If the key does not exist in Memcached, then it returns null.
     * $key param must be string or an array that contains multiple keys;
     * if any key is malformed, null is returned without contacting the
     * server.
     *
     * If asynchronous mode is enabled, it returns true on successful request,
     * and you can retrieve the values by using the receive() method
     * @param string|array $key
     * @return mixed
     * @throws MemcachedException
     */
    public function get($key);

    /**
     * Memcached gets command works like get(), but each returned value is
     * wrapped together with its CAS unique token, e.g.
     * ["value" => "...", "cas" => 123]. Needed to later perform a safe
     * conditional update with cas().
     * @param string|array $key
     * @return mixed
     * @throws MemcachedException
     */
    public function gets($key);

    /**
     * Memcached gat (get and touch) command fetches the value(s) stored at
     * key while also updating their expiration time, in a single round trip.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     * @throws MemcachedException
     */
    public function gat($exptime, $key);

    /**
     * Memcached gats command works like gat(), but also returns the CAS
     * unique token for each item, like gets() does.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     * @throws MemcachedException
     */
    public function gats($exptime, $key);

    /**
     * Memcached delete command is used to delete an existing key from the Memcached server.
     * Returns true if $key is deleted or not found, false if $key is malformed.
     * @param string $key
     * @return bool
     * @throws MemcachedException
     */
    public function delete($key);

    /**
     * Memcached incr command increments the numeric value stored at key by
     * $value. The key must already hold a value that is a decimal
     * representation of a 64-bit unsigned integer. Returns the new value,
     * or false if the key doesn't exist, isn't numeric, or $key/$value are invalid.
     * @param string $key
     * @param int $value
     * @return int|bool
     * @throws MemcachedException
     */
    public function increment($key, $value = 1);

    /**
     * Memcached decr command decrements the numeric value stored at key by
     * $value. Decrementing below zero clamps the result to zero. Returns
     * the new value, or false if the key doesn't exist, isn't numeric, or
     * $key/$value are invalid.
     * @param string $key
     * @param int $value
     * @return int|bool
     * @throws MemcachedException
     */
    public function decrement($key, $value = 1);

    /**
     * Memcached touch command updates the expiration time of an existing
     * item without fetching it. Returns false if the key doesn't exist or
     * $key/$exptime are invalid.
     * @param string $key
     * @param int $exptime
     * @return bool
     * @throws MemcachedException
     */
    public function touch($key, $exptime);

    /**
     * Memcached flush_all command invalidates all existing items
     * immediately, or after $delay seconds.
     * @param int $delay
     * @return bool False if $delay is invalid.
     * @throws MemcachedException
     */
    public function flushAll($delay = 0);

    /**
     * Memcached stats command returns general-purpose statistics and
     * settings as an associative array. This command is always
     * synchronous, regardless of async() mode.
     * @param string|null $type Optional stats sub-command (e.g. "items", "slabs")
     * @return array Empty if $type is invalid.
     * @throws MemcachedException
     */
    public function stats($type = null);

    /**
     * Memcached version command returns the version string reported by the
     * server. This command is always synchronous, regardless of async() mode.
     * @return string|null
     * @throws MemcachedException
     */
    public function version();

    /**
     * Memcached verbosity command sets the verbosity level of the server's
     * logging output.
     * @param int $level
     * @return bool False if $level is invalid.
     * @throws MemcachedException
     */
    public function verbosity($level);

    /**
     * Memcached quit command gracefully closes the connection. The client
     * instance cannot be used for further requests afterwards. Safe to call
     * more than once. The local socket is always released, even if sending
     * the "quit" command itself fails.
     * @return void
     */
    public function quit();

    /**
     * Perform requests asynchronously
     * @param $bool
     * @return void
     * @throws MemcachedException
     */
    public function async($bool);

    /**
     * Retrieves the values requested by get()/gets()/gat()/gats() while in
     * asynchronous mode. get()/gat() and gets()/gats() calls may be freely
     * mixed within the same asynchronous batch — each item's shape (a bare
     * value, or a ["value" => ..., "cas" => ...] pair) reflects whichever
     * command was used to request it.
     * @return mixed A single item if exactly one was requested, an
     *   associative array keyed by memcached key if several were, or null
     *   if nothing is pending / nothing was found.
     * @throws MemcachedException
     */
    public function receive();
}

class Memcached implements MemcachedInterface
{
    /** Detects the end of a synchronous single-line/multi-line response. */
    private const DEFAULT_TERMINATOR = "/^.*(STORED|NOT_STORED|EXISTS|NOT_FOUND|DELETED|TOUCHED"
        . "|OK|END|VERSION\s\S+|(CLIENT_|SERVER_)?ERROR.*)\r\n$/mu";

    /** Detects the end of an incr/decr response: a bare number, or an error. */
    private const NUMERIC_TERMINATOR = "/^.*(\d+|NOT_FOUND|(CLIENT_|SERVER_)?ERROR.*)\r\n$/mu";

    /** Default per-read socket buffer size, and the basis for retrieval read-ahead sizing. */
    private const READ_BUFFER_SIZE = 4096;

    /** Cadence, in microseconds, of the poll loop used while waiting for socket I/O. */
    private const POLL_INTERVAL_US = 10000;

    /** Memcached's own limit: keys may not exceed 250 bytes. */
    private const MAX_KEY_LENGTH = 250;

    /** @var resource|\Socket|null Null once quit() has been called. */
    private $connection;

    /** @var bool */
    private $asynchronous = false;

    /** @var string */
    private $noreply = "";

    /** @var int */
    private $asyncRequestsCount = 0;

    /** @var int Milliseconds allowed for connecting and for each response. */
    private $timeoutMs;

    /**
     * @param string $host
     * @param int $port
     * @param int $timeoutMs Maximum time, in milliseconds, to wait while
     *   connecting and while waiting for each server response.
     * @throws \InvalidArgumentException if $host, $port or $timeoutMs are invalid
     * @throws MemcachedException if the connection cannot be established
     */
    public function __construct($host = "127.0.0.1", $port = 11211, $timeoutMs = 1000)
    {
        if (!is_string($host) || $host === "") {
            throw new \InvalidArgumentException("Memcached host must be a non-empty string.");
        }
        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new \InvalidArgumentException("Memcached port must be an integer between 1 and 65535.");
        }
        if (!is_int($timeoutMs) || $timeoutMs < 1) {
            throw new \InvalidArgumentException("Timeout must be a positive integer number of milliseconds.");
        }
        $this->timeoutMs = $timeoutMs;
        $this->connection = $this->connect($host, $port, $timeoutMs);
    }

    public function __destruct()
    {
        if ($this->connection !== null) {
            @socket_close($this->connection);
        }
    }

    /**
     * Memcached set command is used to set a new value to a new or existing key.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function set($key, $value, $exptime = 0, $flags = 0)
    {
        return $this->store("set", $key, $value, $exptime, $flags);
    }

    /**
     * Memcached add command stores data, but only if the server does not
     * already hold data for this key. Returns false if the key already exists.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function add($key, $value, $exptime = 0, $flags = 0)
    {
        return $this->store("add", $key, $value, $exptime, $flags);
    }

    /**
     * Memcached replace command stores data, but only if the server *does*
     * already hold data for this key. Returns false if the key doesn't exist.
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function replace($key, $value, $exptime = 0, $flags = 0)
    {
        return $this->store("replace", $key, $value, $exptime, $flags);
    }

    /**
     * Memcached append command adds data to an existing key, after the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|float|string|bool $value
     * @return bool
     */
    public function append($key, $value)
    {
        return $this->store("append", $key, $value);
    }

    /**
     * Memcached prepend command adds data to an existing key, before the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|float|string|bool $value
     * @return bool
     */
    public function prepend($key, $value)
    {
        return $this->store("prepend", $key, $value);
    }

    /**
     * Memcached cas (check-and-set) command stores data, but only if no one
     * else has updated the key since it was last fetched with gets()/gats().
     * Returns false if the CAS value is stale (EXISTS) or the key is
     * missing (NOT_FOUND).
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $casUnique The CAS token obtained from gets()/gats()
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function cas($key, $value, $casUnique, $exptime = 0, $flags = 0)
    {
        return $this->store("cas", $key, $value, $exptime, $flags, $casUnique);
    }

    /**
     * Memcached get command is used to get the value stored at key.
     * If the key does not exist in Memcached, then it returns null.
     * $key param must be string or an array that contains multiple keys;
     * if any key is malformed, null is returned without contacting the
     * server.
     *
     * If asynchronous mode is enabled, it returns true on successful request,
     * and you can retrieve the values by using the receive() method
     * @param string|array $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->retrieve("get", $key);
    }

    /**
     * Memcached gets command works like get(), but each returned value is
     * wrapped together with its CAS unique token, e.g.
     * ["value" => "...", "cas" => 123]. Needed to later perform a safe
     * conditional update with cas().
     * @param string|array $key
     * @return mixed
     */
    public function gets($key)
    {
        return $this->retrieve("gets", $key);
    }

    /**
     * Memcached gat (get and touch) command fetches the value(s) stored at
     * key while also updating their expiration time, in a single round trip.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     */
    public function gat($exptime, $key)
    {
        $exptime = $this->filterInt($exptime);
        return $exptime === null ? null : $this->retrieve("gat {$exptime}", $key);
    }

    /**
     * Memcached gats command works like gat(), but also returns the CAS
     * unique token for each item, like gets() does.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     */
    public function gats($exptime, $key)
    {
        $exptime = $this->filterInt($exptime);
        return $exptime === null ? null : $this->retrieve("gats {$exptime}", $key);
    }

    /**
     * Memcached delete command is used to delete an existing key from the Memcached server.
     * Returns true if $key is deleted or not found, false if $key is malformed.
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        if (!$this->isValidKey($key)) {
            return false;
        }
        $this->request("delete {$key}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        return in_array(trim($this->getResponse()), ["DELETED", "NOT_FOUND"], true);
    }

    /**
     * Memcached incr command increments the numeric value stored at key by
     * $value. The key must already hold a value that is a decimal
     * representation of a 64-bit unsigned integer. Returns the new value,
     * or false if the key doesn't exist, isn't numeric, or $key/$value are invalid.
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->incrementOrDecrement("incr", $key, $value);
    }

    /**
     * Memcached decr command decrements the numeric value stored at key by
     * $value. Decrementing below zero clamps the result to zero. Returns
     * the new value, or false if the key doesn't exist, isn't numeric, or
     * $key/$value are invalid.
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->incrementOrDecrement("decr", $key, $value);
    }

    /**
     * Memcached touch command updates the expiration time of an existing
     * item without fetching it. Returns false if the key doesn't exist or
     * $key/$exptime are invalid.
     * @param string $key
     * @param int $exptime
     * @return bool
     */
    public function touch($key, $exptime)
    {
        $exptime = $this->filterInt($exptime);
        if (!$this->isValidKey($key) || $exptime === null) {
            return false;
        }
        $this->request("touch {$key} {$exptime}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        return trim($this->getResponse()) === "TOUCHED";
    }

    /**
     * Memcached flush_all command invalidates all existing items
     * immediately, or after $delay seconds.
     * @param int $delay
     * @return bool
     */
    public function flushAll($delay = 0)
    {
        $delay = $this->filterInt($delay, 0);
        if ($delay === null) {
            return false;
        }
        $this->request("flush_all {$delay}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        return trim($this->getResponse()) === "OK";
    }

    /**
     * Memcached stats command returns general-purpose statistics and
     * settings as an associative array. This command is always
     * synchronous, regardless of async() mode.
     * @param string|null $type Optional stats sub-command (e.g. "items", "slabs")
     * @return array
     */
    public function stats($type = null)
    {
        if ($type !== null && (!is_string($type) || preg_match('/^[a-zA-Z0-9_]+$/', $type) !== 1)) {
            return [];
        }
        $argument = $type !== null ? " {$type}" : "";
        $this->request("stats{$argument}\r\n");
        $response = $this->getResponse(self::READ_BUFFER_SIZE * 4, max($this->timeoutMs, 500));
        $stats = [];
        if (preg_match_all('/STAT (\S+) (.*)\r\n/', $response, $matches)) {
            $stats = array_combine($matches[1], $matches[2]);
        }
        return $stats;
    }

    /**
     * Memcached version command returns the version string reported by the
     * server. This command is always synchronous, regardless of async() mode.
     * @return string|null
     */
    public function version()
    {
        $this->request("version\r\n");
        $response = trim($this->getResponse());
        return preg_match('/^VERSION\s+(\S+)/', $response, $matches) ? $matches[1] : null;
    }

    /**
     * Memcached verbosity command sets the verbosity level of the server's
     * logging output.
     * @param int $level
     * @return bool
     */
    public function verbosity($level)
    {
        $level = $this->filterInt($level, 0);
        if ($level === null) {
            return false;
        }
        $this->request("verbosity {$level}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        return trim($this->getResponse()) === "OK";
    }

    /**
     * Memcached quit command gracefully closes the connection. The client
     * instance cannot be used for further requests afterwards. Safe to call
     * more than once. The local socket is always released, even if sending
     * the "quit" command itself fails.
     * @return void
     */
    public function quit()
    {
        if ($this->connection === null) {
            return;
        }
        $connection = $this->connection;
        $this->connection = null;
        try {
            $written = strlen("quit\r\n");
            while ($written > 0) {
                $result = @socket_write($connection, "quit\r\n");
                if ($result === false) {
                    break;
                }
                $written -= $result;
            }
        } finally {
            @socket_close($connection);
        }
    }

    /**
     * @param $bool
     */
    public function async($bool)
    {
        if ($this->connection === null) {
            throw new MemcachedException("Cannot change mode: the connection has been closed.");
        }
        $asynchronous = (bool)$bool;
        $changed = $asynchronous
            ? @socket_set_nonblock($this->connection)
            : @socket_set_block($this->connection);
        if ($changed === false) {
            $mode = $asynchronous ? "non-blocking" : "blocking";
            throw new MemcachedException(
                "Unable to switch the socket to {$mode} mode: "
                . socket_strerror(socket_last_error($this->connection))
            );
        }
        $this->asynchronous = $asynchronous;
        $this->noreply = $asynchronous ? " noreply" : "";
    }

    /**
     * Retrieves the values requested by get()/gets()/gat()/gats() while in
     * asynchronous mode. get()/gat() and gets()/gats() calls may be freely
     * mixed within the same asynchronous batch — each item's shape (a bare
     * value, or a ["value" => ..., "cas" => ...] pair) reflects whichever
     * command was used to request it.
     * @return mixed A single item if exactly one was requested, an
     *   associative array keyed by memcached key if several were, or null
     *   if nothing is pending / nothing was found.
     */
    public function receive()
    {
        if ($this->asyncRequestsCount === 0) {
            return null;
        }
        $count = $this->asyncRequestsCount;
        $timeout = max($this->timeoutMs, $count * 50);
        $this->asyncRequestsCount = 0;
        $items = $this->readItems($count, $timeout);
        if (empty($items)) {
            return null;
        }
        return count($items) === 1 ? array_shift($items) : $items;
    }

    /**
     * Opens the TCP connection to the server. Connecting is done via a
     * non-blocking socket so the attempt itself is bounded by $timeoutMs,
     * rather than relying on the OS's (often very long) default TCP
     * connect timeout.
     * @param string $host
     * @param int $port
     * @param int $timeoutMs
     * @return resource|\Socket
     * @throws MemcachedException
     */
    private function connect($host, $port, $timeoutMs)
    {
        $connection = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($connection === false) {
            throw new MemcachedException("Unable to create socket: " . socket_strerror(socket_last_error()));
        }

        if (!@socket_set_nonblock($connection)) {
            $error = socket_strerror(socket_last_error($connection));
            socket_close($connection);
            throw new MemcachedException("Unable to prepare socket for connecting: {$error}");
        }
        $connected = @socket_connect($connection, $host, $port);
        if ($connected === false) {
            $errno = socket_last_error($connection);
            $inProgress = [SOCKET_EINPROGRESS, SOCKET_EALREADY, SOCKET_EWOULDBLOCK];
            if (!in_array($errno, $inProgress, true)) {
                $error = socket_strerror($errno);
                socket_close($connection);
                throw new MemcachedException("Unable to connect to Memcached server at {$host}:{$port}: {$error}");
            }
            $write = [$connection];
            $read = null;
            $except = null;
            $ready = @socket_select($read, $write, $except, intdiv($timeoutMs, 1000), ($timeoutMs % 1000) * 1000);
            if ($ready === false) {
                $error = socket_strerror(socket_last_error($connection));
                socket_close($connection);
                throw new MemcachedException("Unable to connect to Memcached server at {$host}:{$port}: {$error}");
            }
            if ($ready === 0) {
                socket_close($connection);
                throw new MemcachedException(
                    "Timed out after {$timeoutMs}ms while connecting to Memcached server at {$host}:{$port}."
                );
            }
            $socketError = socket_get_option($connection, SOL_SOCKET, SO_ERROR);
            if ($socketError !== 0) {
                socket_close($connection);
                throw new MemcachedException(
                    "Unable to connect to Memcached server at {$host}:{$port}: " . socket_strerror($socketError)
                );
            }
        }
        if (!@socket_set_block($connection)) {
            $error = socket_strerror(socket_last_error($connection));
            socket_close($connection);
            throw new MemcachedException("Unable to finish connecting: {$error}");
        }

        $optionSet = @socket_set_option($connection, SOL_SOCKET, SO_RCVTIMEO, [
            "sec" => intdiv($timeoutMs, 1000),
            "usec" => ($timeoutMs % 1000) * 1000,
        ]);
        if (!$optionSet) {
            $error = socket_strerror(socket_last_error($connection));
            socket_close($connection);
            throw new MemcachedException("Unable to configure the socket timeout: {$error}");
        }

        return $connection;
    }

    /**
     * Shared implementation for the storage commands: set, add, replace,
     * append, prepend and cas.
     * @param string $command
     * @param string $key
     * @param int|float|string|bool $value
     * @param int $exptime
     * @param int $flags
     * @param int|null $casUnique Only used for the "cas" command
     * @return bool
     */
    private function store($command, $key, $value, $exptime = 0, $flags = 0, $casUnique = null)
    {
        if (!$this->isValidKey($key) || !is_scalar($value)) {
            return false;
        }
        $value = (string)$value;
        if (trim($value) === "") {
            return false;
        }
        $exptime = $this->filterInt($exptime);
        $flags = $this->filterInt($flags, 0, 4294967295);
        if ($exptime === null || $flags === null) {
            return false;
        }
        $cas = "";
        if ($casUnique !== null) {
            $casUnique = $this->filterInt($casUnique, 0);
            if ($casUnique === null) {
                return false;
            }
            $cas = " {$casUnique}";
        }
        $bytes = strlen($value);
        $this->request("{$command} {$key} {$flags} {$exptime} {$bytes}{$cas}{$this->noreply}\r\n{$value}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        return trim($this->getResponse()) === "STORED";
    }

    /**
     * Shared implementation for the retrieval commands: get, gets, gat and gats.
     * @param string $command Command, including any leading arguments (e.g. "gat 30")
     * @param string|array $key
     * @return mixed
     */
    private function retrieve($command, $key)
    {
        $keys = [];
        if (is_array($key)) {
            if (empty($key)) {
                return null;
            }
            foreach ($key as $singleKey) {
                if (!$this->isValidKey($singleKey)) {
                    return null;
                }
            }
            $keys = $key;
            $key = implode(" ", $key);
        } elseif (!$this->isValidKey($key)) {
            return null;
        }
        $this->request("{$command} {$key}\r\n");
        if ($this->asynchronous) {
            $this->asyncRequestsCount++;
            return true;
        }
        $items = $this->readItems(1, $this->timeoutMs);
        if (empty($items)) {
            return null;
        }
        if (!empty($keys)) {
            $result = array_intersect_key($items, array_flip($keys));
            return !empty($result) ? $result : null;
        }
        return array_key_exists($key, $items) ? $items[$key] : null;
    }

    /**
     * Shared implementation for the incr/decr commands.
     * @param string $command
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    private function incrementOrDecrement($command, $key, $value)
    {
        $value = $this->filterInt($value, 0);
        if (!$this->isValidKey($key) || $value === null) {
            return false;
        }
        $this->request("{$command} {$key} {$value}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return true;
        }
        // Protocol errors are handled locally, not via the shared
        // checkForProtocolError() throw: the server returns a CLIENT_ERROR
        // when the stored value isn't a valid decimal counter, which is a
        // normal, documented outcome of this command, not a failure.
        $response = trim($this->getResponse(null, null, self::NUMERIC_TERMINATOR, false));
        if (ctype_digit($response)) {
            return (int)$response;
        }
        if ($response === "NOT_FOUND" || strpos($response, "CLIENT_ERROR") === 0) {
            return false;
        }
        throw new MemcachedException("Memcached server reported an error - {$response}");
    }

    /**
     * Validates a key against Memcached's constraints: must be non-empty,
     * contain no whitespace or control characters, and not exceed 250 bytes.
     * @param mixed $key
     * @return bool
     */
    private function isValidKey($key)
    {
        return is_string($key) && strlen($key) > 0 && strlen($key) <= self::MAX_KEY_LENGTH
            && preg_match('/^\S+$/ui', $key) === 1;
    }

    /**
     * Validates that $value is a plain integer (or a numeric string made up
     * only of digits and an optional leading "-") within the given bounds,
     * and returns it as a real int. Every numeric argument that ends up
     * interpolated into a raw protocol command line is passed through this
     * first, so malformed input can never inject extra characters (such as
     * "\r\n", which would otherwise let a caller smuggle additional
     * commands into the connection) into the command stream.
     * @param mixed $value
     * @param int|null $min
     * @param int|null $max
     * @return int|null Null if $value is not a valid integer within range.
     */
    private function filterInt($value, $min = null, $max = null)
    {
        if (is_int($value)) {
            $intValue = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $intValue = (int)$value;
        } else {
            return null;
        }
        if ($min !== null && $intValue < $min) {
            return null;
        }
        if ($max !== null && $intValue > $max) {
            return null;
        }
        return $intValue;
    }

    /**
     * Sends a command to the Memcached server, retrying partial writes
     * until the whole command has been sent.
     * @param string $cmd
     * @return void
     * @throws MemcachedException if the command cannot be written
     */
    private function request($cmd)
    {
        if ($this->connection === null) {
            throw new MemcachedException("Cannot send command: the connection has been closed.");
        }
        $length = strlen($cmd);
        $written = 0;
        while ($written < $length) {
            $result = @socket_write($this->connection, substr($cmd, $written));
            if ($result === false) {
                $errno = socket_last_error($this->connection);
                if ($errno === SOCKET_EAGAIN || $errno === SOCKET_EWOULDBLOCK) {
                    usleep(self::POLL_INTERVAL_US);
                    continue;
                }
                throw new MemcachedException(
                    "Failed to write to the Memcached socket: " . socket_strerror($errno),
                    $errno
                );
            }
            $written += $result;
        }
    }

    /**
     * Reads a synchronous response from the socket until $terminator
     * matches, the connection is lost, or $timeoutInMs elapses.
     * @param int|null $responseLength Per-read socket buffer size; defaults to READ_BUFFER_SIZE.
     * @param int|null $timeoutInMs Defaults to the connection's configured timeout.
     * @param string $terminator Regex used to detect the end of the response.
     * @param bool $throwOnProtocolError Set to false for the rare command where an
     *   ERROR/CLIENT_ERROR/SERVER_ERROR response is itself a documented, normal
     *   outcome that the caller handles directly (see incrementOrDecrement()).
     * @return string
     * @throws MemcachedException on timeout, connection loss, or (when $throwOnProtocolError
     *   is true) a protocol-level error response
     */
    private function getResponse(
        $responseLength = null,
        $timeoutInMs = null,
        $terminator = self::DEFAULT_TERMINATOR,
        $throwOnProtocolError = true
    ) {
        if ($this->connection === null) {
            throw new MemcachedException("Cannot read response: the connection has been closed.");
        }
        $responseLength = $responseLength ?? self::READ_BUFFER_SIZE;
        $timeoutInMs = $timeoutInMs ?? $this->timeoutMs;
        $response = "";
        while (true) {
            $read = [$this->connection];
            $write = null;
            $except = null;
            $numOfChanges = @socket_select($read, $write, $except, 0);
            if ($numOfChanges === false) {
                throw new MemcachedException(
                    "socket_select() failed: " . socket_strerror(socket_last_error($this->connection))
                );
            }
            if ($numOfChanges === 0) {
                if ($timeoutInMs <= 0) {
                    throw new MemcachedException(
                        $response === ""
                            ? "Timed out waiting for a response from the Memcached server."
                            : "Timed out waiting for the end of the Memcached server's response."
                    );
                }
                usleep(self::POLL_INTERVAL_US);
                $timeoutInMs -= self::POLL_INTERVAL_US / 1000;
                continue;
            }
            $value = @socket_read($this->connection, $responseLength);
            if ($value === false) {
                throw new MemcachedException(
                    "Failed to read from the Memcached socket: " . socket_strerror(socket_last_error($this->connection))
                );
            }
            if ($value === "") {
                throw new MemcachedException("Connection to the Memcached server was closed unexpectedly.");
            }
            $response .= $value;
            if (preg_match($terminator, $value)) {
                break;
            }
        }
        return $throwOnProtocolError ? $this->checkForProtocolError($response) : $response;
    }

    /**
     * A genuine protocol-level error (ERROR / CLIENT_ERROR / SERVER_ERROR)
     * is always the very first thing the server sends for a given command —
     * it's never preceded by partial VALUE/STAT output — so checking only
     * the start of the response cannot be confused by a stored value whose
     * bytes happen to contain one of these words.
     * @param string $response
     * @return string The same response, unchanged, if it isn't an error.
     * @throws MemcachedException if the response is a protocol-level error
     */
    private function checkForProtocolError($response)
    {
        if (preg_match('/^(ERROR|CLIENT_ERROR|SERVER_ERROR)(?: (.*?))?\r\n/', $response, $matches)) {
            $detail = isset($matches[2]) ? trim($matches[2]) : "";
            $message = $detail !== "" ? "{$matches[1]}: {$detail}" : $matches[1];
            throw new MemcachedException("Memcached server reported an error - {$message}");
        }
        return $response;
    }

    /**
     * Reads and parses the response(s) for $count pending get/gets/gat/gats
     * commands into an associative array keyed by item key. Each value is
     * either the raw stored value (get/gat), or an array
     * ["value" => ..., "cas" => ...] when the server included a CAS unique
     * token (gets/gats) — the wire format is self-describing, so both
     * flavors can appear in the same result.
     *
     * Each retrieval command produces its own independent "...END\r\n"
     * response, and pipelined commands' responses are not guaranteed to
     * arrive together in a single socket read, so this keeps reading until
     * exactly $count "END" markers have been seen — never fewer, even if an
     * early read happens to end right after the first one.
     *
     * Item boundaries are located using the byte length declared in each
     * "VALUE" header, not by searching for delimiter keywords, so stored
     * data that happens to contain text such as "END" or "VALUE" is never
     * misinterpreted as protocol framing.
     * @param int $count
     * @param int $timeoutInMs
     * @return array
     * @throws MemcachedException
     */
    private function readItems($count, $timeoutInMs)
    {
        if ($this->connection === null) {
            throw new MemcachedException("Cannot read response: the connection has been closed.");
        }
        $response = "";
        $offset = 0;
        $items = [];
        $completed = 0;
        while ($completed < $count) {
            while (($eol = strpos($response, "\r\n", $offset)) !== false) {
                $line = substr($response, $offset, $eol - $offset);
                if ($line === "END") {
                    $offset = $eol + 2;
                    $completed++;
                    if ($completed >= $count) {
                        break;
                    }
                    continue;
                }
                if (preg_match('/^VALUE (\S+) (\d+) (\d+)(?: (\d+))?$/', $line, $matches) === 1) {
                    $bytes = (int)$matches[3];
                    if (strlen($response) < $eol + 2 + $bytes + 2) {
                        break; // this item's data block hasn't fully arrived yet
                    }
                    $key = $matches[1];
                    $cas = isset($matches[4]) ? (int)$matches[4] : null;
                    $value = substr($response, $eol + 2, $bytes);
                    $items[$key] = $cas !== null ? ["value" => $value, "cas" => $cas] : $value;
                    $offset = $eol + 2 + $bytes + 2;
                    continue;
                }
                $this->checkForProtocolError(substr($response, $offset));
                throw new MemcachedException("Unexpected response from the Memcached server: \"{$line}\"");
            }
            if ($completed >= $count) {
                break;
            }
            $read = [$this->connection];
            $write = null;
            $except = null;
            $numOfChanges = @socket_select($read, $write, $except, 0);
            if ($numOfChanges === false) {
                throw new MemcachedException(
                    "socket_select() failed: " . socket_strerror(socket_last_error($this->connection))
                );
            }
            if ($numOfChanges === 0) {
                if ($timeoutInMs <= 0) {
                    throw new MemcachedException(
                        "Timed out waiting for the end of the Memcached server's response."
                    );
                }
                usleep(self::POLL_INTERVAL_US);
                $timeoutInMs -= self::POLL_INTERVAL_US / 1000;
                continue;
            }
            $chunk = @socket_read($this->connection, self::READ_BUFFER_SIZE);
            if ($chunk === false) {
                throw new MemcachedException(
                    "Failed to read from the Memcached socket: " . socket_strerror(socket_last_error($this->connection))
                );
            }
            if ($chunk === "") {
                throw new MemcachedException("Connection to the Memcached server was closed unexpectedly.");
            }
            $response .= $chunk;
        }
        return $items;
    }
}
