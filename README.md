# apigility-raml-validator
[![Build Status](https://travis-ci.org/robertboloc/apigility-raml-validator.svg?branch=master)](https://travis-ci.org/robertboloc/apigility-raml-validator)
[![License](https://poser.pugx.org/robertboloc/apigility-raml-validator/license.png)](https://packagist.org/packages/robertboloc/apigility-raml-validator)

This is a tool to help validate your Apigility implementation against a RAML
specification. This way you can make sure the implementation matches the
specification requirements.

In comparison with similar tools this application
does not check the output of the api but the code structure directly.

> Please note that this tool is in early stages of development.

## Table of contents
- [Installation](#installation)
- [Usage](#usage)
- [Validation](#validation)

## Installation
```php
composer require --dev "robertboloc/apigility-raml-validator"
chmod +x vendor/bin/apigility-raml-validator
```

## Usage
```php
vendor/bin/apigility-raml-validator [--help] [-s spec, --spec spec] [-p project, --project project]
```

### Options
##### spec (-s | --spec)
Path to the RAML specification file

##### target (-p | --project)
Path to the Apigility project containing the source code

##### module (-m | --module)
Hint the name of the module in case it's different from the API name

##### help (--help)
Display the usage message

##### debug (--debug)
Enable debug messages when running the application

## Validation

This is the list of fields from the RAML specification currently being validated

| RAML          | Apigility                                                         |
| ------------- | ----------------------------------------------------------------- |
| title         | Check a module matching `title` exists                            |
| version       | Check a version folder matching `version` exists                  |
| resource \*   | Check a route has been defined for the `resource`                 |
| method        | Check a collection route implements the methods of a top resource |

\* only top level resources are detected for now

## Example

Highlighted in green are the lines of the RAML specification currently being
validated against the implementation

```diff
  #%RAML 1.0
+ title: Test
+ version: v1
- protocols: HTTPS
- mediaType: application/json
- baseUri: https://api.test.com/{version}
- description: My Test API

+ /foo:
+   get:
+     description: My test endpoint

+ /bar:
+   post:
+     description: Another test endpoint
```
