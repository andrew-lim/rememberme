<?php

/**
Utility class to generate and verify remember me browser cookies.
The cookie value itself is not stored in the database.
Instead a hash of it is stored in a 'rememberme' row along with the creation and expirty date/time.
To use this class you'll need to pass a PDO instance which has access to a database table with a
structure similar to this:

-- MySQL / SQLite
CREATE TABLE rememberme (
    cookiehash VARCHAR(128) PRIMARY KEY,
    userid     VARCHAR(128) NOT NULL,
    createdat  DATETIME     NOT NULL,
    expiresat  DATETIME     NULL
);

-- postgresql
CREATE TABLE rememberme (
    cookiehash VARCHAR(128) PRIMARY KEY,
    userid     VARCHAR(128) NOT NULL,
    createdat  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    expiresat  TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

Author: Andrew Lim (https://github.com/andrew-lim)
*/

namespace AndrewLim\RememberMe;

class RememberMeCookie
{
    /** @var \PDO PDO instance. */
    public $pdo = null;

    /** @var string Cookie name passed to setcookie(). Default value is 'remembermecookie' */
    public $cookiename = "remembermecookie";

    /** @var int Length of random string to be used the cookie value. Default value is 64 */
    public $length = 64;

    /** @var string Table name to store rememberme cookie information. Default value is 'rememberme' */
    public $table = "rememberme";

    /** @var string Algorithm used to hash() the cookie value. Default value is 'sha256' */
    public $algo = 'sha256';

    /** @var int The time the cookie expires passed to setcookie() If 0, the current time + 10 years will be used */
    public $expires = 0;

    /** @var string The path on the server passed to setcookie(). Default value is '/' */
    public $path = "/";

    /** @var string The (sub)domain passed to setcookie(). Default value is an empty string */
    public $domain = "";

    /** @var bool Secure argument passed to setcookie(). Default value is false */
    public $secure = false;

    /** @var bool httponly argument passed to setcookie(). Default value is false */
    public $httponly = false;

    /**
     * Constructor
     * @param \PDO $pdo The PDO instance
     */
    public function __construct($pdo)
    {
        if (!$pdo) {
            throw new \InvalidArgumentException("RememberMeCookie requires a PDO instance");
        }
        $this->pdo = $pdo;
    }

    /**
     * Creates a random string for a cookie value.
     * @param int $length Length of the random string
     * @param string $keyspace Characters to use for the random string
     * @return string The cookie value
     */
    public static function randomString(
        $length = 64,
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ) {
        if (function_exists('random_int')) {
            // https://stackoverflow.com/questions/4356289/php-random-string-generator
            $pieces = [];
            $max = mb_strlen($keyspace, '8bit') - 1;
            for ($i = 0; $i < $length; ++$i) {
                $pieces [] = $keyspace[random_int(0, $max)];
            }
            return implode('', $pieces);
        } else {
            // https://stackoverflow.com/a/28569122/1645045
            return substr(str_shuffle(str_repeat($keyspace, $length)), 0, $length);
        }
    }

    /**
     * Stores a hash of cookievalue in the database then passes cookievalue to setcookie()
     * If cookievalue is null, randomString() will be used to create one
     * @param string $userid      User ID this cookie is for
     * @param string $cookievalue Cookie value. If null, a random string will be used
     * @return \stdClass A stdClass object with 2 properties: cookievalue, cookiehash
     */
    public function create($userid, $cookievalue = null)
    {
        $cookievalue = $cookievalue ? $cookievalue : RememberMeCookie::randomString($this->length);
        $cookiehash  = hash($this->algo, $cookievalue);
        $cookieexpires = null;
        if (!$this->expires) {
            $cookieexpires = time() + (10 * 365 * 24 * 60 * 60); // 10 years
        }
        $createdat = (new \DateTime())->format('Y-m-d H:i:s');
        $expiresat = \DateTime::createFromFormat('U', '' . $cookieexpires)->format('Y-m-d H:i:s');
        $sql = "INSERT INTO $this->table (cookiehash, userid, createdat, expiresat) VALUES (?,?,?,?)";
        $this->pdo->prepare($sql)->execute([$cookiehash, $userid, $createdat, $expiresat]);
        setcookie(
            $this->cookiename,
            $cookievalue,
            $cookieexpires,
            $this->path,
            $this->domain,
            $this->secure,
            $this->httponly
        );
        $r = new \stdClass();
        $r->cookievalue = $cookievalue;
        $r->cookiehash  = $cookiehash;
        return $r;
    }

    /**
     * Checks if there is a hash of the specified cookievalue in the database.
     * @param string $cookievalue Cookie value. If null, will use current cookiename to search in $_COOKIES
     * @return \stdClass|null If valid, an object representing the row in the database. Otherwise null.
     */
    public function verify($cookievalue = null)
    {
        if ($cookievalue == null) {
            $cookievalue = $this->cookieValue();
        }
        if (!$cookievalue) {
            return null;
        }
        $cookiehash = hash($this->algo, $cookievalue);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM $this->table  WHERE cookiehash=:cookiehash ORDER BY createdat DESC LIMIT 1"
        );
        $stmt->execute(['cookiehash' => $cookiehash]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if ($this->isExpired($row)) {
            return null;
        }
        return $row;
    }

    /**
     * Unsets cookie for logout.
     * @param string $cookiename Name of the cookie
     */
    public static function unsetCookie($cookiename)
    {
        if (isset($_COOKIE[$cookiename])) {
            // https://stackoverflow.com/questions/686155/remove-a-cookie
            unset($_COOKIE[$cookiename]);
            setcookie($cookiename, '', time() - 3600, "/");
        }
    }

    /**
     * Deletes the cookie in the database and removes the cookie from user's browser.
     */
    public function logout()
    {
        $row = $this->verify($this->cookieValue());
        if ($row && $row->cookiehash) {
            $this->beforeDelete($row);
            $stmt = $this->pdo->prepare("DELETE FROM $this->table WHERE cookiehash=:cookiehash");
            $stmt->execute(['cookiehash' => $row->cookiehash]);
            $this->afterDelete($row);
        }
        RememberMeCookie::unsetCookie($this->cookiename);
    }

    /**
     * Searches $_COOKIE for cookie matching cookiename.
     * @return string|null Cookie value or null
     */
    public function cookieValue()
    {
        if (isset($_COOKIE[$this->cookiename])) {
            return $_COOKIE[$this->cookiename];
        }
        return null;
    }

    /**
     * Checks current time is later than expiredat
     * @param \stdClass $row stdClass holding row information
     * @return boolean true or false
     */
    protected function isExpired($row)
    {
        if ($row->expiresat == null) {
            return false;
        }
        $expiresat = new \DateTime($row->expiresat);
        $now = new \DateTime();
        return ($now >= $expiresat);
    }

    /**
     * Method called before the remember me row is deleted from the database.
     * @param \stdClass $row stdClass holding row information
     */
    protected function beforeDelete($row)
    {
    }

    /**
     * Method called after the remember me row is deleted from the database.
     * @param \stdClass $row stdClass holding row information
     */
    protected function afterDelete($row)
    {
    }
}
