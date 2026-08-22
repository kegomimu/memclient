<?php

namespace MClient;

interface MemcachedInterface
{
    /**
     * Memcached set command is used to set a new value to a new or existing key.
     * @param string $key
     * @param int|string $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function set($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached add command stores data, but only if the server does not
     * already hold data for this key. Returns false if the key already exists.
     * @param string $key
     * @param int|string $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function add($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached replace command stores data, but only if the server *does*
     * already hold data for this key. Returns false if the key doesn't exist.
     * @param string $key
     * @param int|string $value
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function replace($key, $value, $exptime = 0, $flags = 0);

    /**
     * Memcached append command adds data to an existing key, after the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|string $value
     * @return bool
     */
    public function append($key, $value);

    /**
     * Memcached prepend command adds data to an existing key, before the
     * existing data. The existing flags and exptime are left untouched.
     * @param string $key
     * @param int|string $value
     * @return bool
     */
    public function prepend($key, $value);

    /**
     * Memcached cas (check-and-set) command stores data, but only if no one
     * else has updated the key since it was last fetched with gets()/gats().
     * Returns false if the CAS value is stale (EXISTS) or the key is
     * missing (NOT_FOUND).
     * @param string $key
     * @param int|string $value
     * @param int $casUnique The CAS token obtained from gets()/gats()
     * @param int $exptime
     * @param int $flags
     * @return bool
     */
    public function cas($key, $value, $casUnique, $exptime = 0, $flags = 0);

    /**
     * Memcached get command is used to get the value stored at key.
     * If the key does not exist in Memcached, then it returns null.
     * $key param must be string or an array that contains multiple keys.
     *
     * If asynchronous mode is enabled, it returns true on successful request,
     * and you can retrieve the values by using the receive() method
     * @param string|array $key
     * @return mixed
     */
    public function get($key);

    /**
     * Memcached gets command works like get(), but each returned value is
     * wrapped together with its CAS unique token, e.g.
     * ["value" => "...", "cas" => 123]. Needed to later perform a safe
     * conditional update with cas().
     * @param string|array $key
     * @return mixed
     */
    public function gets($key);

    /**
     * Memcached gat (get and touch) command fetches the value(s) stored at
     * key while also updating their expiration time, in a single round trip.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     */
    public function gat($exptime, $key);

    /**
     * Memcached gats command works like gat(), but also returns the CAS
     * unique token for each item, like gets() does.
     * @param int $exptime
     * @param string|array $key
     * @return mixed
     */
    public function gats($exptime, $key);

    /**
     * Memcached delete command is used to delete an existing key from the Memcached server.
     * Returns true if $key is deleted or not found
     * @param string $key
     * @return bool
     */
    public function delete($key);

    /**
     * Memcached incr command increments the numeric value stored at key by
     * $value. The key must already hold a value that is a decimal
     * representation of a 64-bit unsigned integer. Returns the new value,
     * or false if the key doesn't exist, isn't numeric, or another error occurs.
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * Memcached decr command decrements the numeric value stored at key by
     * $value. Decrementing below zero clamps the result to zero. Returns
     * the new value, or false if the key doesn't exist, isn't numeric, or
     * another error occurs.
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * Memcached touch command updates the expiration time of an existing
     * item without fetching it. Returns false if the key doesn't exist.
     * @param string $key
     * @param int $exptime
     * @return bool
     */
    public function touch($key, $exptime);

    /**
     * Memcached flush_all command invalidates all existing items
     * immediately, or after $delay seconds.
     * @param int $delay
     * @return bool
     */
    public function flushAll($delay = 0);

    /**
     * Memcached stats command returns general-purpose statistics and
     * settings as an associative array. This command is always
     * synchronous, regardless of async() mode.
     * @param string|null $type Optional stats sub-command (e.g. "items", "slabs")
     * @return array
     */
    public function stats($type = null);

    /**
     * Memcached version command returns the version string reported by the
     * server. This command is always synchronous, regardless of async() mode.
     * @return string|null
     */
    public function version();

    /**
     * Memcached verbosity command sets the verbosity level of the server's
     * logging output.
     * @param int $level
     * @return bool
     */
    public function verbosity($level);

    /**
     * Memcached quit command gracefully closes the connection. The client
     * instance cannot be used for further requests afterwards.
     * @return void
     */
    public function quit();

    /**
     * Perform requests asynchronously
     * @param $bool
     * @return void
     */
    public function async($bool);

    /**
     * Retrieves values called by get()/gets()/gat()/gats() in asynchronous mode.
     * Note: don't mix get()/gat() calls with gets()/gats() calls within the
     * same asynchronous batch — receive() parses the whole batch according
     * to whichever of the two flavors was issued last.
     * @return string|array
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

    /** @var resource */
    private $connection;

    /** @var bool */
    private $asynchronous = false;

    /** @var string */
    private $noreply = "";

    /** @var int */
    private $asyncRequestsCount = 0;

    /** @var string "plain" or "cas", tracks which parser receive() should use */
    private $asyncRetrievalMode = "plain";

    /**
     * @param string $host
     * @param int $port
     * @throws \Exception
     */
    public function __construct($host = "127.0.0.1", $port = 11211)
    {
        $connection = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $ok = socket_set_option($connection, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 1, "usec" => 0]);
        $ok = socket_connect($connection, $host, $port) && $ok && $connection !== false;
        if (!$ok) {
            throw new \Exception(socket_last_error($connection));
        }
        $this->connection = $connection;
    }

    public function __destruct()
    {
        if ($this->connection !== null) {
            socket_close($this->connection);
        }
    }

    /**
     * Memcached set command is used to set a new value to a new or existing key.
     * @param string $key
     * @param int|string $value
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
     * @param int|string $value
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
     * @param int|string $value
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
     * @param int|string $value
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
     * @param int|string $value
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
     * @param int|string $value
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
     * $key param must be string or an array that contains multiple keys.
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
        return $this->retrieve("gets", $key, true);
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
        return $this->retrieve("gat {$exptime}", $key);
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
        return $this->retrieve("gats {$exptime}", $key, true);
    }

    /**
     * Memcached delete command is used to delete an existing key from the Memcached server.
     * Returns true if $key is deleted or not found
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        $ok = $this->request("delete {$key}{$this->noreply}\r\n");
        return $this->asynchronous ? $ok : preg_match("/(DELETED|NOT_FOUND)/", $this->getResponse()) == 1;
    }

    /**
     * Memcached incr command increments the numeric value stored at key by
     * $value. The key must already hold a value that is a decimal
     * representation of a 64-bit unsigned integer. Returns the new value,
     * or false if the key doesn't exist, isn't numeric, or another error occurs.
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
     * another error occurs.
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
     * item without fetching it. Returns false if the key doesn't exist.
     * @param string $key
     * @param int $exptime
     * @return bool
     */
    public function touch($key, $exptime)
    {
        if (!$this->isValidKey($key)) {
            return false;
        }
        $ok = $this->request("touch {$key} {$exptime}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return $ok;
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
        $ok = $this->request("flush_all {$delay}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return $ok;
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
        $argument = $type !== null ? " {$type}" : "";
        $this->request("stats{$argument}\r\n");
        $response = $this->getResponse(8192, 200);
        $stats = [];
        if (preg_match_all("/STAT (\S+) (.*)\r\n/", $response, $matches)) {
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
        return preg_match("/^VERSION\s+(\S+)/", $response, $matches) ? $matches[1] : null;
    }

    /**
     * Memcached verbosity command sets the verbosity level of the server's
     * logging output.
     * @param int $level
     * @return bool
     */
    public function verbosity($level)
    {
        $ok = $this->request("verbosity {$level}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return $ok;
        }
        return trim($this->getResponse()) === "OK";
    }

    /**
     * Memcached quit command gracefully closes the connection. The client
     * instance cannot be used for further requests afterwards.
     * @return void
     */
    public function quit()
    {
        $this->request("quit\r\n");
        socket_close($this->connection);
        $this->connection = null;
    }

    /**
     * @param $bool
     */
    public function async($bool)
    {
        $this->asynchronous = $bool;
        $this->noreply = $bool ? " noreply" : "";
        $bool ? socket_set_nonblock($this->connection) : socket_set_block($this->connection);
    }

    /**
     * Retrieves values called by get()/gets()/gat()/gats() in asynchronous mode.
     * Note: don't mix get()/gat() calls with gets()/gats() calls within the
     * same asynchronous batch — receive() parses the whole batch according
     * to whichever of the two flavors was issued last.
     * @return string|array
     */
    public function receive()
    {
        $length = (1024 + 30) * $this->asyncRequestsCount;
        $timeout = $this->asyncRequestsCount * 50;
        $this->asyncRequestsCount = 0;
        $response = $this->getResponse($length, $timeout);
        return $this->asyncRetrievalMode === "cas"
            ? $this->parseResponseWithCas($response)
            : $this->parseResponse($response);
    }

    /**
     * Shared implementation for the storage commands: set, add, replace,
     * append, prepend and cas.
     * @param string $command
     * @param string $key
     * @param int|string $value
     * @param int $exptime
     * @param int $flags
     * @param int|null $casUnique Only used for the "cas" command
     * @return bool
     */
    private function store($command, $key, $value, $exptime = 0, $flags = 0, $casUnique = null)
    {
        if (!$this->isValidKey($key) || trim((string)$value) === "") {
            return false;
        }
        $value = (string)$value;
        $bytes = strlen($value);
        $cas = $casUnique !== null ? " {$casUnique}" : "";
        $ok = $this->request("{$command} {$key} {$flags} {$exptime} {$bytes}{$cas}{$this->noreply}\r\n{$value}\r\n");
        if ($this->asynchronous) {
            return $ok;
        }
        return trim($this->getResponse()) === "STORED";
    }

    /**
     * Shared implementation for the retrieval commands: get, gets, gat and gats.
     * @param string $command Command, including any leading arguments (e.g. "gat 30")
     * @param string|array $key
     * @param bool $withCas Whether the response carries a CAS unique value per item
     * @return mixed
     */
    private function retrieve($command, $key, $withCas = false)
    {
        $length = $withCas ? 1024 + 50 : 1024 + 30;
        $keys = [];
        if (is_array($key)) {
            $keys = $key;
            $length = $length * count($key);
            $key = implode(" ", $key);
        }
        $ok = $this->request("{$command} {$key}\r\n");
        if ($this->asynchronous) {
            $this->asyncRetrievalMode = $withCas ? "cas" : "plain";
            $this->asyncRequestsCount++;
            return $ok;
        }
        $response = $this->getResponse($length);
        $response = $withCas ? $this->parseResponseWithCas($response) : $this->parseResponse($response);
        if (is_array($response)) {
            if (!empty($keys) && is_array($keys)) {
                $result = array_intersect_key($response, array_flip($keys));
                return !empty($result) ? $result : null;
            }
            return array_key_exists($key, $response) ? $response[$key] : null;
        }
        return $response;
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
        if (!$this->isValidKey($key) || !is_numeric($value) || $value < 0) {
            return false;
        }
        $value = (int)$value;
        $ok = $this->request("{$command} {$key} {$value}{$this->noreply}\r\n");
        if ($this->asynchronous) {
            return $ok;
        }
        $response = trim($this->getResponse(64, 10, self::NUMERIC_TERMINATOR));
        if ($response === "" || !ctype_digit($response)) {
            return false;
        }
        return (int)$response;
    }

    /**
     * Validates a key against Memcached's constraints: must be non-empty,
     * contain no whitespace or control characters, and not exceed 250 bytes.
     * @param string $key
     * @return bool
     */
    private function isValidKey($key)
    {
        return is_string($key) && strlen($key) > 0 && strlen($key) <= 250 && preg_match("/^\S+$/ui", $key) === 1;
    }

    /**
     * Sends command to the Memcached server
     * @param $cmd
     * @return bool
     */
    private function request($cmd)
    {
        return (bool)socket_write($this->connection, $cmd);
    }


    /**
     * @param int $response_length
     * @param int $timeoutInMs
     * @param string $terminator Regex used to detect the end of the response
     * @return string
     */
    private function getResponse($response_length = 1024, $timeoutInMs = 10, $terminator = self::DEFAULT_TERMINATOR)
    {
        $response = "";
        while (true) {
            $read = [$this->connection];
            $write = null;
            $except = null;
            $numOfChanges = socket_select($read, $write, $except, 0);
            if ($numOfChanges === 0) {
                if ($timeoutInMs <= 0) {
                    break;
                }
                usleep(10 * 1000);
                $timeoutInMs -= 10;
                continue;
            } elseif ($numOfChanges > 0) {
                $value = socket_read($this->connection, $response_length);
                if ($value === false || $value === "") {
                    break;
                }
                $response .= $value;
                if (preg_match($terminator, $value)) {
                    break;
                }
            }
        }
        return $response;
    }

    /**
     * @param string $response
     * @return string|array
     */
    private function parseResponse($response)
    {
        $matches = [];
        preg_match_all("/\s*VALUE (?<keys>\S+) \d+ \d+[ \d]*\s+/", $response, $matches);
        $values = preg_split("/(\s*VALUE \S+ \d+ \d+[ \d]*|(\\r\\n)?(END|NOT_FOUND|ERROR))\s+/", $response);
        // Only strip split artifacts (empty strings); a legitimate stored
        // value of "0" is falsy in PHP but must not be discarded here.
        $values = array_filter($values, function ($value) {
            return $value !== "";
        });
        $values = array_values($values);
        if (!empty($values)) {
            if (count($values) === 1) {
                return array_shift($values);
            }
            $result = [];
            for ($i = 0; $i < count($values); $i++) {
                $result[$matches["keys"][$i]] = $values[$i];
            }
            return $result;
        }
        return null;
    }

    /**
     * Like parseResponse(), but for gets()/gats() responses: extracts the
     * CAS unique value alongside each item's value.
     * @param string $response
     * @return array|null
     */
    private function parseResponseWithCas($response)
    {
        $matches = [];
        preg_match_all("/\s*VALUE (?<keys>\S+) \d+ \d+ (?<cas>\d+)\s+/", $response, $matches);
        $values = preg_split("/(\s*VALUE \S+ \d+ \d+ \d+|(\\r\\n)?(END|NOT_FOUND|ERROR))\s+/", $response);
        // Only strip split artifacts (empty strings); a legitimate stored
        // value of "0" is falsy in PHP but must not be discarded here.
        $values = array_filter($values, function ($value) {
            return $value !== "";
        });
        $values = array_values($values);
        if (empty($values)) {
            return null;
        }
        $result = [];
        for ($i = 0; $i < count($values); $i++) {
            $result[$matches["keys"][$i]] = [
                "value" => $values[$i],
                "cas" => (int)$matches["cas"][$i],
            ];
        }
        return $result;
    }
}
