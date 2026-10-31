<nav class="rt-language-links" aria-label="{{ __('Language') }}">
    @foreach (\App\Support\Locales::NAMES as $code => $name)
        @if (app()->getLocale() === $code)
            <span aria-current="true">{{ $name }}</span>
        @else
            <a href="{{ route('admin.language', $code) }}" lang="{{ $code }}">{{ $name }}</a>
        @endif
    @endforeach
</nav>
