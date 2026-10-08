<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Pulso Fiscal · Demo</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/draft.css') }}">
    <link rel="stylesheet" href="{{ asset('css/navigation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/payment-scenario.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-trace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/invoice-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/invoice-import.css') }}">
    @livewireStyles
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
