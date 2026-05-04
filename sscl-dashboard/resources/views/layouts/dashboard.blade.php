@extends('layouts.base')

@section('content')
  <div class="min-h-screen bg-app">
    @include('partials.sidebar')

    <main class="md:pl-72">
      <div class="p-4 md:p-6">
        @include('partials.topbar')
        @include('partials.toast')

        <div class="mt-4 md:mt-6">
          @yield('page')
        </div>
      </div>
    </main>
  </div>
@endsection
