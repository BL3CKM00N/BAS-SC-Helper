@foreach ($announcements as $announcement)
    @php
        $style = match ($announcement->severity) {
            'critical' => 'border-red-500/30 bg-red-500/15 text-red-100',
            'warning' => 'border-amber-500/30 bg-amber-500/15 text-amber-100',
            default => 'border-sky-500/30 bg-sky-500/15 text-sky-100',
        };
    @endphp
    <div role="{{ $announcement->severity === 'critical' ? 'alert' : 'status' }}" class="flex items-start gap-3 border-b px-4 py-2.5 text-sm {{ $style }}">
        <div class="min-w-0 flex-1">
            <p class="font-medium">{{ $announcement->title }}</p>
            @if ($announcement->body)
                <p class="mt-0.5 whitespace-pre-line opacity-90">{{ $announcement->body }}</p>
            @endif
        </div>
        @if ($announcement->isDismissable())
            <form method="POST" action="{{ route('announcements.dismiss', $announcement) }}" data-dismiss-announcement>
                @csrf
                <button type="submit" class="rounded px-2 py-1 text-xs font-medium opacity-80 hover:bg-white/10 hover:opacity-100" aria-label="Dismiss">Dismiss</button>
            </form>
        @endif
    </div>
@endforeach
