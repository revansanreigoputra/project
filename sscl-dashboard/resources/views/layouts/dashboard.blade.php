@extends('layouts.base')

@section('content')
  <div class="min-h-screen overflow-x-hidden bg-app">
    @include('partials.sidebar')

    <main class="min-w-0 2xl:pl-72">
      <div class="min-w-0 p-4 md:p-6">
        @include('partials.topbar')
        @include('partials.toast')

        <div class="mt-4 min-w-0 md:mt-6">
          @yield('page')
        </div>
      </div>
    </main>
  </div>
@endsection
