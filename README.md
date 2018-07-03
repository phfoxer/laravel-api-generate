# phfoxer/apigenerate
Easy api generate for the Laravel Framework.
## Installation

```bash
composer require phfoxer/apigenerate
```

Install the service provider:

```php
// config/app.php
'providers' => [
    ...
    Phfoxer\ApiGenerate\ApiGenerateServiceProvider::class,
    ...
];
```

## Usage

### Creating a new resource

To create a new api resource run this command:

```php
artisan generate:api --table=table_name
```
You can see result in http://localhost:8000/api/table_table
You can find your new resource in app/Modules/General.
General is the default 
### Params

To define route:

```php
artisan generate:api --table=table_name --route=my-custom-route
```

To define module name:

```php
artisan generate:api --table=table_name --route=my-custom-route --module=Exemple
```
You can find your new resource in app/Modules/Exemple.

## About Phfoxer

Phfoxer is a brazilian developer, Salvador, Bahia :).

## Postcardware

You are free to use this package as it's [MIT-licensed](LICENSE.md)
