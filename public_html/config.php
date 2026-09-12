<?php
declare(strict_types=1);

return [
    'app_name' => 'Gestão Financeira',
    'company_name' => 'Minha Empresa',
    'company_document' => '00.000.000/0001-00',
    'company_address' => 'Cidade/UF',
    'timezone' => 'America/Sao_Paulo',
    'base_url' => '', // Ex.: /financeiro (sem barra no final). Deixe vazio na raiz.
    'db' => [
        'host' => 'mysql-database-dhhsa5ntiphvhzqav2bkkant',
        'port' => '3306',
        'name' => 'financeiro',
        'user' => 'mysql',
        'pass' => 'kVltQX7XzY987YwsxJt7Wf0TU7be9aBcDHU5uVV6xhQeJp5bbtuA1kpNx7UXYzsf',
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    ],
];
