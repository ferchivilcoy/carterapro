<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuenta del Administrador General
    |--------------------------------------------------------------------------
    |
    | AdminUserSeeder usa estas variables en vez de credenciales hardcodeadas.
    | Si ADMIN_EMAIL no está definido, el seeder no crea ninguna cuenta.
    |
    */

    'name' => env('ADMIN_NAME', 'Administrador General'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Cuentas demo
    |--------------------------------------------------------------------------
    |
    | DemoRoleUsersSeeder sólo corre en local/testing. La contraseña demo se
    | puede cambiar por entorno; fuera de local nunca se siembran estas cuentas.
    |
    */

    'demo_password' => env('DEMO_PASSWORD', 'password'),

];
