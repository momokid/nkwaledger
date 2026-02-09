<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div id="react-login">Form must appear here</div>
    
@viteReactRefresh
@vite('resources/js/app.jsx')
</x-guest-layout>
