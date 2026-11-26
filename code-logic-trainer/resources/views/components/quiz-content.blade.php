@props(['content' => ''])

@php
    $content = (string) $content;
    preg_match_all('/```(?:[A-Za-z0-9_+#.-]+)?[ \t]*\r?\n([\s\S]*?)```/u', $content, $matches, PREG_OFFSET_CAPTURE);
    $segments = [];
    $cursor = 0;

    foreach ($matches[0] as $index => $match) {
        if ($match[1] > $cursor) {
            $segments[] = ['type' => 'text', 'content' => substr($content, $cursor, $match[1] - $cursor)];
        }

        $segments[] = ['type' => 'code', 'content' => $matches[1][$index][0]];
        $cursor = $match[1] + strlen($match[0]);
    }

    if ($cursor < strlen($content) || $segments === []) {
        $segments[] = ['type' => 'text', 'content' => substr($content, $cursor)];
    }
@endphp

<div {{ $attributes->class(['space-y-2 break-words']) }}>
    @foreach ($segments as $segment)
        @if ($segment['type'] === 'code')
            <pre class="overflow-x-auto rounded-[var(--radius-field)] border border-accent-300/15 bg-ink-950/70 p-3 font-mono text-sm leading-6 text-accent-100"><code>{{ $segment['content'] }}</code></pre>
        @elseif ($segment['content'] !== '')
            <p class="whitespace-pre-wrap leading-7 text-inherit">{{ $segment['content'] }}</p>
        @endif
    @endforeach
</div>
