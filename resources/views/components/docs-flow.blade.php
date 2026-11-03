@props(['title', 'steps', 'outcomes' => []])
<figure class="docs-flow">
    <figcaption>{{ $title }}</figcaption>
    <ol class="docs-flow-steps" style="--docs-flow-count: {{ count($steps) }}">
        @foreach ($steps as $step)
            <li>
                <span class="docs-flow-actor">{{ $step['actor'] }}</span>
                <strong>{{ $step['label'] }}</strong>
                @if (!$loop->last)
                    <svg class="docs-flow-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 12h15m-5-5 5 5-5 5" /></svg>
                @endif
            </li>
        @endforeach
    </ol>
    @if ($outcomes)
        <p class="docs-flow-outcome-label">What happens after the review?</p>
        <ul class="docs-flow-outcomes">
            @foreach ($outcomes as $outcome)
                <li><strong>{{ $outcome['label'] }}</strong><span>{{ $outcome['detail'] }}</span></li>
            @endforeach
        </ul>
    @endif
</figure>
