<?php

return [
    'queue_connection' => 'database',
    'smtp_timeout' => 15,
    'defaults' => [
        'identity' => ['companyName' => 'PT AST INDONESIA', 'contactEmail' => '', 'logo' => ''],
        'smtp' => [
            'host' => '', 'port' => 587, 'encryption' => 'starttls', 'username' => '',
            'fromEmail' => '', 'fromName' => 'HRD PT AST INDONESIA', 'replyTo' => '', 'sendIntervalSeconds' => 0,
        ],
        'landing' => [
            'baseUrl' => rtrim(env('APP_URL', 'http://localhost'), '/'),
            'type' => 'education',
            'title' => 'Ini adalah simulasi keamanan email',
            'message' => 'Anda baru saja membuka tautan dalam simulasi phishing perusahaan. Gunakan kesempatan ini untuk mengenali ciri email yang mencurigakan.',
            'tips' => "Periksa alamat pengirim sebelum memercayai isi email.\nWaspadai permintaan mendesak dan iming-iming hadiah atau kenaikan gaji.\nKonfirmasikan informasi melalui kanal resmi perusahaan.",
        ],
        'reports' => ['timezone' => 'Asia/Jakarta', 'pageLength' => 10, 'period' => 'all'],
        'audit' => ['retentionMonths' => 6],
    ],
];
