# Contributing

Bug reports, feedback and code contributions are more than welcome!

Development happens in the `develop` branch, and any pull requests should be made against that branch please.  For all but the simplest things, please [open an issue](https://github.com/pbiron/updates-api-inspector) and reference the issue in your PR.

Do you speak a language other than (American) English?  If so, please help [translate this plugin](https://translate.wordpress.org/projects/wp-plugins/updates-api-inspector).

## Local Setup

### Setup

After cloning the `develop` branch of this repo, you'll need to install the development tools:

* [npm](https://www.npmjs.com/get-npm)
* [composer](https://getcomposer.org/)

Then, you'll need to install the dependencies, by running:

* `npm install`
* `composer install`

### Grunt tasks

Grunt is used as a task runner (no npm/composer scripts here :smirk: ).

Some useful grunt tasks for contributing to this plugin include:

* `grunt build` will:
    * generate the composer autoloader
    * generate RTL CSS
    * minify JS & CSS
    * **Note:** This task must be run before this plugin will work on your local site
* `grunt precommit` will:
    * run phpunit
    * run phpcs
    * lint JS
* `grunt release` will:
    * run `grunt build`
    * run `grunt precommit`
    * package the plugin into a ZIP, suitable for installing on a WP site

## Code Contributions

When contributing code, please keep the folowing in mind:

* Write code that is backward-compatible to PHP 5.6.0 and WordPress 4.6
    * Those requirements may be bumped as the need arises.
* Follow the [WordPress coding and documentation standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
* When appropriate and if possible, provide unit tests for your changes

### Coding Style

In addition to adhering to WordPress coding/documentation standards, the following conventions are used:

* A single PHP namespace is used throughout: `SHC\Updates_API_Inspector` (PSR-4 namespace conventions are **not** used!)
* Unless there is good reason to do otherwise, I prefer the use of singletons.  When adding a new singleton class to the codebase, extend `SHC\Updates_API_Inspector\Singleton`
    * When adding a non-singleton class to the codebase, extend `SHC\Updates_API_Inspector\Base` (or a core class, such as [WP_List_Table](https://developer.wordpress.org/reference/classes/wp_list_table/))
* When adding a function/method that does not return a value, please add `return;` statement(s) and include `@returns void` in the DocBlock
    * I know, WPCS doesn't require that (and actively discourages it), but I do require it
* Whenever possible, use single quotes (`'`) and not double quotes (`"`) in HTML/XML markup (including the various build tool config files, such as `phpunit.xml`)
    
### PHPUnit and PHPCS

As I do not use CI, it will be very much appreciated if you run `grunt precommit` locally before submitting PRs.  See [Grunt tasks](#grunt-tasks) above for what that will do.

Before running the unit tests, copy `tests/wp-tests-config-sample.php` to `tests/wp-tests-config.php` and edit `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, etc.

When running the unit tests, you'll notice messages during the bootstrap such as:

> Not running ajax tests. To execute these, use --group ajax.

There are similar messages for `ms-files` and `external-http` tests.

This plugin uses the same test harness that WordPress core does and those messages are output by that harness; you can ignore them.

### Contributing Unit Tests

Unit tests should go into the `tests/phpunit/tests` directory. Each test class should extend the `Updates_API_Inspector_UnitTestCase` class and file names should be prefixed with `test-`, e.g., `tests/phpunit/tests/test-my-new-tests.php`.
