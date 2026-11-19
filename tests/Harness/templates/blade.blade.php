@php($r = $dipstick->readiness())
<p id="readiness">@if ($r){{ $r['score'] }}/{{ $r['grade'] }}/{{ $r['ready'] ? 'ready' : 'not-ready' }}/{{ $r['critical'] }}@else none @endif</p>
