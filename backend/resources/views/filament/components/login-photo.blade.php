{{-- The centre of Bulbulaa beside the sign-in form, the same photo that opens the public website. --}}
<aside class="rt-login-photo" aria-hidden="true">
    <img src="{{ asset('images/bulbulaa-centre.jpg') }}" alt="" class="rt-login-photo__img" />
    <div class="rt-login-photo__caption">
        <span class="rt-login-photo__town">{{ config('app.town_name') }}</span>
        <span class="rt-login-photo__line">{{ __(':town administration', ['town' => config('app.town_name')]) }}</span>
    </div>
</aside>
