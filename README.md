# Quickstart: the modern way to bootstrap projects

## What is Quickstart?

Quickstart generates ready-to-use project skeletons and Docker environments, both local and production-ready, for a
variety of languages and frameworks.

## Why use Quickstart?

Quickstart allows you as a developer to quickly (hence the name) set up a project from established best-practice
solutions, without having to wrestle hours and days getting Docker, Docker Compose and a CI pipeline up to speed.

Additionally, it allows you to benefit from centrally managed improvements to the Dockerfile and other environment
files.

## What frameworks and languages are supported?

Quickstart is primarily focused on the PHP and NodeJS stacks. On the PHP side, Quickstart integrates with:

* Symfony (both CLI and Web projects)
* Drupal
* Wordpress
* Generic PHP Web and CLI projects

On the NodeJS side, Quickstart integrates with:

* Plain ReactJS
* ReactJS in Symfony (via the Webpack Encore bundle)
* Gatsby static server-side rendering
* Generic static server-side rendering (e.g. nuxt, jekyll)
* Generic dynamic NodeJS backend/frontend projects (e.g. react dom-server)

Additionally, Quickstart also supports the use-case of providing a Docker image for hosting plain HTML files.

**Note that support for projects is to large parts still in development. The author is thankful for any pull requests.**

## What OS families / versions does Quickstart offer?

Quickstart can create Docker images based on:

* Ubuntu 18.04 LTS
* Ubuntu 20.04 LTS
* Ubuntu 22.04 LTS
* Debian bullseye (stable)
* Debian bookworm (testing)
* Alpine Linux 3.13
* Alpine Linux 3.14
* Alpine Linux 3.15
* Alpine Linux 3.16

Not all features (especially PHP versions) can be provided on all OS versions. Please see the compatibility matrix for
more details.

## What environments does Quickstart offer?

* PHP:
  * For Ubuntu- and Debian-based PHP environments, Quickstart offers PHP 7.4, 8.0 and 8.1 (per the support matrix of [Ondřej Surý](https://deb.sury.org/))
  * For Alpine-based PHP environments, Quickstart is less flexible (per the support matrix of [Codecasts](https://github.com/codecasts/php-alpine))
  * Note that not all PHP extensions are available on all combinations of OS and PHP version!
* NodeJS:
  * independent of the OS family, Quickstart offers v10/dubnium, v12/erbium, v14/fermium and v16/gallium
  * the package manager can either be `npm` or `yarn`, in any case Quickstart will install the most current supported version
* Web server:
  * independent of the OS family, Quickstart offers Apache, NGINX and lighttpd in the respective latest versions
  * The default PHP SAPI is `mod_php` on Apache and `fpm` for NGINX and lighttpd
  * Projects with a NodeJS backend directly expose the NodeJS server!
  * Notes:
    * currently there is no support for HTTPS certificates.
    * currently there is no support for custom server configurations except for `.htaccess` on Apache

## How do I use Quickstart?

1. Install Quickstart. For now, the only way to do so is:
    1. Clone the repository: `git clone https://github.com/quickstart-sh/quickstart.git`
    2. Install dependencies.
       * PHP 7.4 CLI, with extensions:
           * ctype
           * curl
           * iconv
           * json
           * mbstring
           * openssl
           * yaml
           * zip
       * PHP Composer v2
       * Docker with Docker-Compose
    4. Install dependencies: `composer install`
    5. Symlink `quickstart` somewhere in your `PATH`, e.g. by running
       `ln -s /Users/example/Projects/quickstart/bin/console /usr/local/bin/quickstart`
2. If you wish to create a project from a blank slate:
    1. create the directory: `mkdir helloworld`
    2. change into the directory: `cd helloworld`
    3. Initialize quickstart (this creates the `.quickstart.yml` file that contains the project's
       configuration): `quickstart quickstart:init`
    4. Configure the project's requirements: `quickstart quickstart:reconfigure`
    5. (Re-)generate the files (Dockerfile, CI configuration, ...): `quickstart quickstart:regenerate`
       Always re-run this step after running a `reconfigure`!
    6. Create the local environment: `quickstart quickstart:start`
    7. Install the project from the best-practices skeleton: `quickstart quickstart:install`
    8. You are now ready to start developing. Usually, the following will be automatically running:
       * your application's selected webserver at http://localhost:8080
       * if your project uses SMTP-based mailing, a Mailcatcher instance at http://localhost:8081
       * if your project uses NodeJS, inside the application's container, a `watch`-style task that recompiles on changes
       * application logs are available by running `quickstart quickstart:logs` or `quickstart quickstart:logs -f` to follow. You can also use `docker logs` on the primary application container.
    * Stop the Docker containers without losing data (e.g. of the database): `quickstart quickstart:stop`
    * Destroy the Docker containers and remove all data (e.g. of the database): `quickstart quickstart:destroy`
    * Rebuild the Docker containers, e.g. after a `reconfigure`, `regenerate` or a base OS image update: `quickstart quickstart:start -r`

## Under which license is Quickstart available?

Quickstart itself is licensed under the terms of the GNU GPL v3, see the `LICENSE` file in the root of the repository.
There is no "commercial licensing" or support available.

Quickstart dependencies are licensed under various versions of the MIT, Apache and BSD 3-Clause licenses. Check out the
source code, run `composer install` and `composer licenses` to get a current list.

The files that Quickstart generates can be re-used as if they were in the public domain, or in case your legal
department requires a "formal" license, the [CC-0 license](
https://creativecommons.org/publicdomain/zero/1.0/deed.de).

**Explicitly note that the Quickstart project, its authors and contributors do not provide any kind of warranty,
liability assumptions or whatever else. Use at your own risk, review generated files prior to running them.**

## How can I contribute to Quickstart development?

If you wish to report an issue or file a pull request, use the GitHub tracker
at https://github.com/quickstart-sh/quickstart/issues/new.

Please note that the project expects pull requests to be licensed under the same terms as Quickstart itself - including
the CC-0 provision for generated files.

There currently is no way of providing financial support (donations, merchandise, fundraisers) to the project or its
authors (the German tax code is a mess). Corporate users are more than welcome to offer development support or pull
requests.

Since this project is a one-person show, there will be no formal code-of-conduct process. However, the author as an
old-school netizen expects everyone to abide by the classic Netiquette
rules: [Don't be a jerk](https://meta.wikimedia.org/wiki/Don%27t_be_a_jerk)
and [assume good faith](https://en.wikipedia.org/wiki/Wikipedia:Assume_good_faith). The author explicitly reserves the
right to ban people who violate the first rule.

## I found a security issue in Quickstart or generated artifacts!

Please report security issues to info@quickstart.sh. Valid security issues caused by Quickstart itself will be rewarded
with 1 crate of beer or soda to the first reporter, capped at one crate a quarter, to be delivered by a local delivery
service.

**Note this offer is not to be considered legally binding, especially not in countries where there are no delivery
services, they are prohibitively expensive or difficult to order from Germany or illegal.**

**Security issues caused by out-of-date Debian/Ubuntu/Alpine packages, Docker images, NPM/Composer packages etc. are not
considered valid.**

## Is there a style guide?

Personally, I stick with the IntelliJ PHPStorm defaults which are embedded as part of this project. Pull requests will
be formatted prior to merge anyway, so it is not too much of a problem if you use another IDE.

All code should be reasonably well commented or self-understandable in English. Git commit and pull request comments
should be written in English and provide links to references where necessary.

Please try to achieve a reasonable test coverage for PHP code; the project uses PHPUnit for testing.

## GDPR

The Quickstart project itself does not gather any data about its users, neither in the form of telemetry nor by the
website hosting.

Since the website uses GitHub Pages, please refer
to [the GitHub privacy statement](https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement) for
more details.

Additionally, you can contact info@quickstart.sh with any inquiries about your rights under the GDPR.

## How to package a release PHAR?

1. Install [box](https://github.com/box-project/box/blob/master/doc/installation.md#composer): `composer bin box require --dev humbug/box -W`
2. Create a local Symfony environment file: `composer dump-env prod` (if you forget this, `box` will complain that it could not find `.env.local.php`)
3. Compile the PHAR: `vendor/bin/box compile`
4. The resulting PHAR can be found (and run) in `target/quickstart.phar`
