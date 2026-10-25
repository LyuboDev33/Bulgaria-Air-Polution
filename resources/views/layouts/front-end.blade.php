<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    @yield('SEO')

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/4.0.0/jquery.js" integrity="sha512-jx7pKV1OCCwD8nJVKETycdimr+xPg3VCP0Wc5tX0RdpusPKEC72eB5cyqgT48K5HxbXvbxIc/oDVb70QMhSeLw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</head>

<body>

    <main>

        @include('layouts.frontend.header')

        <div class="app-shell"> {{ $slot }}</div>

        @include('layouts.frontend.footer')

    </main>



    <script src="{{ asset('assets/js/custom.js') }}"></script>

</body>

</html>
