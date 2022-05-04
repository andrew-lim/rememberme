# andrewlim/rememberme

Easy to use RememberMeCookie class with no dependencies except PDO.

Features:
- PSR-12 compliant
- Works with PHP 5.4 and above
- The hash of the cookie value is stored in the database

## Setup

By default, the class looks for and stores cookie data in a table named "rememberme".
It needs these 4 columns to be present: cookiehash, userid, createdat, expiredat

```sql
-- MySQL / SQLite
CREATE TABLE rememberme (
    cookiehash VARCHAR(128) PRIMARY KEY,
    userid     VARCHAR(128) NOT NULL,
    createdat  DATETIME     NOT NULL,
    expiresat  DATETIME     NULL
);
```

```sql
-- postgresql
CREATE TABLE rememberme (
    cookiehash VARCHAR(128) PRIMARY KEY,
    userid     VARCHAR(128) NOT NULL,
    createdat  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    expiresat  TIMESTAMP(0) WITHOUT TIME ZONE NULL
);
```

## Usage

New Login - call create()

```php
// Create a RememberMeCookie and pass a PDO instance
$rememberMeCookie = new \AndrewLim\RememberMe\RememberMeCookie($pdo);

// Create a cookie and send it to browser, and store its hash in the database
// The userid variable is a foreign key id to identify the user
$row = $rememberMeCookie->create($userid);

```

Subsequent Login - call verify()

```php
// If rememberme cookie is valid exists and is valid
$row = $rememberMeCookie->verify();
if ($row) {
    header('Location: dashboard.php');
    return;
}
// Invalid
else {
    header('Location: login.php');
    return;
}

```

Logout - call logout()
```php
$rememberMeCookie->logout();
header('Location: login.php');
```