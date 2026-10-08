<div class="border-t border-brand-border px-5 py-4">
    <p class="text-sm font-medium text-brand-label">Signature</p>
    <svg
        viewBox="0 0 {{ $signature['width'] }} {{ $signature['height'] }}"
        class="mt-2 h-44 w-full max-w-lg rounded-lg border border-brand-border bg-white"
        role="img"
        aria-label="Employee signature"
    >
        @foreach ($signature['strokes'] as $stroke)
            @php
                $points = [];
                foreach ($stroke as $point) {
                    if (! is_array($point)) {
                        continue;
                    }
                    $points[] = ((float) ($point['x'] ?? 0)).','.((float) ($point['y'] ?? 0));
                }
            @endphp
            @if ($points !== [])
                <polyline
                    fill="none"
                    stroke="#111827"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    points="{{ implode(' ', $points) }}"
                />
            @endif
        @endforeach
    </svg>
</div>
