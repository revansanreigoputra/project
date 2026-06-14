<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name'))</title>

  {{-- Apply theme BEFORE CSS to avoid flash --}}
  <script>
    (function () {
      try {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') document.documentElement.classList.add('dark');
      } catch (e) {}
    })();
  </script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-app text-app antialiased">
  <div x-data="ui" x-init="init()" class="min-h-screen">
    @yield('content')
    <x-confirmation-dialog />
  </div>
</body>
</html>
