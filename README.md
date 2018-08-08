# phfoxer/apigenerate
API Rest generator for Laravel Framework.
Create your api resource in seconds using only your database table name or connection name.

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

To create all API Rest resources run this command (Only postgres and mysql):
```php
php artisan generate:api --con=conection_name
```

To create a new api resource run this command:
```php
php artisan generate:api --table=table_name --relation=true
```
You can see result in http://localhost:8000/api/table_name
You can find your new resource in app/Modules/General.
General is the default 
### Params

To define route:

```php
php artisan generate:api --table=table_name --route=my-custom-route --relation=true
```

To define module name:

```php
php artisan generate:api --table=table_name --route=my-custom-route --module=Exemple --relation=true
```
You can find your new resource in app/Modules/Exemple.

## Postcardware

You are free to use this package as it's [MIT-licensed](LICENSE.md)
