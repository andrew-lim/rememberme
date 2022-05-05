# andrewlim/rememberme

Easy to use RememberMeCookie class with no dependencies except PDO.

Features:
- PSR-12 compliant
- Works with PHP 5.4 and above
- The hash of the cookie value (by default SHA256) is stored in the database

## Setup

You can include the RememberMeCookie.php file directly or install via composer:

    composer require andrewlim/rememberme

By default, the class looks for and stores cookie data in a table named "rememberme".
It needs these 4 columns to be present: cookiehash, userid, createdat, expiresat

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

Call create() after successful login to create the rememberme cookie

```php
// Create a RememberMeCookie and pass a PDO instance
$rememberMeCookie = new \AndrewLim\RememberMe\RememberMeCookie($pdo);

// Create a cookie, store its hash and and send it to browser
// The userid variable is a foreign key id to identify the user
$row = $rememberMeCookie->create($userid);

// Redirect to secure page
if ($row) {
    header('Location: dashboard.php');
}

```

Call verify() to check for rememberme cookie

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

Call logout() to remove the remembermecookie from browser and delete the database hash
```php
$rememberMeCookie->logout();
header('Location: login.php');
```

## Configuration

You can configure the cookie creation and storage before calling create()

```php
$rememberMeCookie = new \AndrewLim\RememberMe\RememberMeCookie($pdo);

// Table name
$rememberMeCookie->table = 'customtable';

// Cookie name
$rememberMeCookie->cookiename = 'dashboard_cookie';

// hashing algorithm
$rememberMeCookie->algo = 'sha512';

// 2 years expiry
$rememberMeCookie->expires = time() + (2 * 365 * 24 * 60 * 60);
```