# Development Helper for Shopware 6

Successor plugin of FroshProfiler and FroshMailCatcher

## Installation

## Git Version

* Checkout Plugin in `/custom/plugins/FroshDevelopmentHelper`
* Download [FroshPluginUploader](https://github.com/FriendsOfShopware/FroshPluginUploader) and run `ext:prepare [folder to plugin]`
* Install the Plugin with the Plugin Manager

## Features

* Show Twig Includes / Blocks in Template as HTML Comment
* Disable Annoying Storefront Error Handler
* Disables Twig Cache
* Twig Variables in Twig Tab
* Generate definition from command line

### Generate plugin

```shell
./bin/console frosh:make:plugin <plugin-name>
```

Optional with `--namespace=xxx\\xx` specifying the namespace

### Generating Entities or edit Entities

Start wizard with

```
./bin/console frosh:make:definition My\\Plugin\\Namespace\\SomeDefinition
```

### Generate Migration

```shell
./bin/console frosh:make:migration <plugin-name> <entity-name>
```

Example entity name: product, category, order

Checks the difference of that definition with the database and creates a migration

### Generate Twig Extension file

```shell
./bin/console frosh:extend:template <plugin-name>
```

Asks for the block you want to extend and creates the twig extension file for you

### SQL Logger for Console Debugging

Prints executed SQL to the console, in such a way that they can be easily copied to other SQL tools for further
debugging. This is similar to the symfony debug bar, but useful in CLI commands and tests.

Usage:

     Kernel::getConnection()->getConfiguration()->setSQLLogger(
         new \Frosh\DevelopmentHelper\Doctrine\EchoSQLLogger()
     );
