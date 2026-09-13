<section data-testid="summary-host">
    <h1>{{ $label }}</h1>
    @foreach ($cards as $card)
        @include('summary-card', ['card' => $card])
    @endforeach
</section>
