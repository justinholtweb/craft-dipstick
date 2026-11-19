<h1>{{ $title }}</h1>
{{-- @php static $x = 1; @endphp --}}
@php
    $page = $_GET['page'] ?? 1;
@endphp
<p>{{ $page }}</p> @php(\App\Counter::$n++)
<?php setcookie('a', 'b'); ?>
