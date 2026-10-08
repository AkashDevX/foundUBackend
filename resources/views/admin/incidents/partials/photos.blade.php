@php
    $photos = $report->filesFor($group);
@endphp
@if ($photos !== [])
    <div class="border-t border-brand-border px-5 pt-4">
        <p class="text-sm font-medium text-brand-label">{{ $group === 'property' ? 'Property photos' : 'Supporting photos' }}</p>
    </div>
    <div class="grid gap-4 p-5 sm:grid-cols-2">
        @foreach ($photos as $photo)
            <a href="{{ route('admin.incidents.attachment', ['incident' => $report->id, 'file' => $photo['index']]) }}" target="_blank" rel="noopener" class="block">
                <img
                    src="{{ route('admin.incidents.attachment', ['incident' => $report->id, 'file' => $photo['index']]) }}"
                    alt="{{ $photo['file']['name'] ?? 'Incident photo' }}"
                    class="max-h-80 w-full rounded-lg border border-brand-border object-contain bg-brand-surface"
                >
            </a>
        @endforeach
    </div>
@endif
