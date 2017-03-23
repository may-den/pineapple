# Pineapple

#### An API-compatible replacement, forked from and for PEAR DB.

| `master` | `dev-0.3.x` |
|----------|-------------|
| [![Build Status](https://travis-ci.org/wethersherbs/pineapple.svg?branch=master)](https://travis-ci.org/wethersherbs/pineapple) | [![Build Status](https://travis-ci.org/wethersherbs/pineapple.svg?branch=dev-0.3.x)](https://travis-ci.org/wethersherbs/pineapple) |

## What?

A compatibility layer around [PDO](http://php.net/pdo) or [Doctrine DBAL](https://github.com/doctrine/dbal) to provide backward compatibility to [PEAR DB](https://github.com/pear/DB)-based applications.

It's close to (but _is not_) a drop-in replacement; You'll need to make modifications to your application, but they should be minimal.

## Why?

PEAR DB is very old (the copyright range ends 9 years prior to Pineapple's inception), and was obsoleted by MDB2, which has in turn become obsolete. Projects have been based on these modules, but need an upgrade path that means new code can be developed using modern DB access methods, whilst retaining access for legacy code without opening a second database connection.

Ultimately, it'll provide backward compatibility for everything you've done, and leave you to write something more modern for your future work.

## How?

This package is a fork of PEAR and DB, heavily refactored. The purpose of it is to provide a method-compatible drop-in replacement, reproducing `PEAR::raiseError` and `PEAR::isError`, and all methods under the `DB` class. Connection-specific drivers are dropped and only two connection drivers are included: `DoctrineDbal`, a driver which takes a constructed [doctrine/dbal](https://github.com/doctrine/dbal) object, and `PdoDriver`, a driver which takes a constructed [PDO](http://php.net/PDO) object. It is intended _only_ as a path to PDO or Doctrine DBAL migration, to keep legacy systems working whilst retaining a single database connection per application.

The intention is to strip unused methods, clean (remove all warnings and notices), make PSR-2 clean and provide full test coverage of the DB compatibility layer. Global constants will be replaced with class constants to maintain a clean constant namespace.

It is up to your application to cache the constructed database connection object. Please do not use Pineapple to retrieve your connection object after it has been dependency injected.

## Usage

In Doctrine DBAL mode:

```php
<?php

use Pineapple\DB;
use Pineapple\DB\Driver\DoctrineDbal;

// lengthy dbal connection here...

$db = DB::factory(DoctrineDbal::class);
$db->setConnectionHandle($dbalConn);
$result = $db->query('SELECT USER(), DATABASE()');
```

In PDO mode:

```php
<?php

use Pineapple\DB;
use Pineapple\DB\Driver\PdoDriver;

$db = DB::factory(PdoDriver::class);
$db->setConnectionHandle(new PDO('sqlite::memory:'));
$result = $db->query('SELECT CURRENT_TIMESTAMP');
```

## Integrating into your project

### Changes to class names

Firstly, you should be aware of the following changes to class names. If you typehint, use `instanceOf` or methods which check class name, you will need to be aware of the follow changes to class names:

| Old class        | New class                    |
|------------------|------------------------------|
| `DB`             | `Pineapple\DB`               |
| `DB_Error`       | `Pineapple\DB\Error`         |
| `DB_result`      | `Pineapple\DB\Result`        |
| `DB_row`         | `Pineapple\DB\Row`           |
| `DB_common`      | `Pineapple\DB\Driver\Common` |
| `PEAR`           | `Pineapple\Util`             |
| `PEAR_Error`     | `Pineapple\Error`            |
| `PEAR_Exception` | `Pineapple\Exception`        |

### Changes to constants

Secondly, the `PEAR_` and `DB_` global constants (defines) are now class constants and will need to be scoped accordingly. Anything starting `PEAR_` resides within `Pineapple\Util`, and anything starting `DB_` resides within `Pineapple\DB`. We recommend namespace aliasing, e.g.:

```php
<?php
use Pineapple\DB;

$query = $db->query('SELECT CURRENT_TIMESTAMP');
if (DB::isError($query) && $query->getCode() === DB::DB_ERROR_INVALID) {
    die();
}
```

### Deprecated methods & variable access.

Thirdly, significant chunks of both `PEAR` and `DB` have been unapologetically stripped out and discarded. Anything within the `PEAR` class which is not:

- `isError`
- `raiseError`
- `throwError`

..is now gone. Calling the above methods both as class methods and static methods is retained (it has been determined that both methods are in vigorous use in examined code).

All of the DSN handling code is removed from the `DB` (now `Pineapple\DB`) class. It exists purely as a construction factory for driver classes and an override for error handling. The following methods are retained (everything else, e.g. `connect()`) is now removed:

- `factory`
- `isError`
- `isConnection`
- `errorMessage`

Most significantly, the following methods have been removed from the driver classes:

Replace these with query placeholders (parameterised queries):
- `quoteString`
- `quote`

If DBMS-abstracted inspection is required, replace these with calls to Doctrine DBAL:
- `getTables`
- `getSpecialQuery`
- `getListOf`

Sequence facilities should be implemented in SQL:
- `createSequence`
- `dropSequence`
- `getSequenceName`
- `nextId`

With the removal of the above methods, class variables are now a mixture of private & protected and **will not be available to your application**. Several "getters" (and _some_ setters) have been provided for your convenience. See:

- `getOption` (accompanying DB's setOption)
- `getFeature`
- `getLastQuery`
- `getFetchMode`
- `getFetchModeObjectClass`
- `getLastQuery`
- `getLastParameters`
- `getNativeErrorCode`

Changes to the `_db` property of a constructed driver object in order to switch database on-the-fly have been replaced by calls to method `changeDatabase`.

Lastly, `autoPrepare` and `autoExecute` will no longer perform an `UPDATE` or `DELETE` without a `WHERE` clause. Change this behaviour by calling method `setAcceptConsequencesOfPoorCodingChoices`.

### Code change summary

- All classes are namespaced. See the table in the previous section for the class name mappings.
- All global variables have now been dropped.
- Global constants have been moved to class constants.
- **All connectivity drivers have been removed**. The only drivers provided are `DoctrineDbal` and `PdoDriver` to connect with Doctrine's DBAL and PDO respectively, and it is _intended_ that all connectivity be performed through one of these two layers.
- All methods in PEAR have been dropped, except for `isError`, `raiseError` and `throwError`. This includes PEAR's pseudo-destructors (shutdown functions).
- Compatibility names for legacy constructors and '`_Name`' destructors have been removed.
- PEAR & DB Error suppression has been removed.
- Large swathes of code put in place to aid multi-driver compatibility have been removed.
- Spit, polish & PSR-2. Refactoring to support some more modern aspects of PHP (e.g. method statics replaced with class statics).
- Three new exceptions for unhandled events (which would normally cause a `die`): `DriverException`, `FeatureException`, `StatementException`.
- Don't use `connect()`, use `factory()` and set the connection using `setConnectionHandle()`.

## Test suite

A suite of tests to aid in regression testing whilst refactoring has been built. Every attempt to reach 100% coverage across all methods and classes has been made. Please ensure the test suite is run before submitting patches or pull requests.

A script to run the suite has been put into the project's `composer.json` file. To execute it, run:

```shell
$ composer test
```

Unfortunately, composer squashes the colourised output which helps indicate failures whilst running interactively. If you wish to view the output with colourisation, run:

```shell
$ vendor/bin/phpunit --coverage-html='coverage/' --coverage-text='php://stdout' --colors=auto
```

**We recommend that all changes are tested with PHP versions 5.6.x and 7.x.**

## Credits

The DB authors should take the most credit; the source was forked in its entirety for the refactor.

The following credits were taken from DB:

* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Daniel Convissor [danielc@php.net](mailto:danielc@php.net)

And these from PEAR:

* Sterling Hughes [sterling@php.net](mailto:sterling@php.net)
* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Greg Beaver [cellog@php.net](mailto:cellog@php.net)

## Who is responsible for this?

* Rob Andrews [rob.andrews@mayden.co.uk](mailto:rob.andrews@mayden.co.uk)

Please see the file "CONTRIBUTORS.md" for other attribution.

## License

Upstream PEAR is distributed under the BSD 2-clause license. This license is retained. This applies to classes `Util`, `Error` and `Exception`.

Upstream DB is distributed under the PHP License [http://php.net/license/](http://php.net/license/), which is a BSD-style license. This license is retained. This applies to all other classes and includes `DoctrineDbal`, which is derived from `DB_mysqli` (no longer included in this package).

Any additional code or test suites are provided under the PHP License [http://php.net/license/](http://php.net/license/).
