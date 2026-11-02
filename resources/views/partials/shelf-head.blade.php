<div class="shelf-head">
  <h2 dir="auto">{{ $title }}</h2>
  <div class="shelf-tools">
    @isset($link)<a class="seeall" href="{{ $link }}">See all{{ isset($count) ? ' ('.$count.')' : '' }}</a>@endisset
    <button type="button" class="shelf-btn" data-target="{{ $id }}" data-dir="-1" aria-label="Scroll left">@include('partials.icon', ['n' => 'left'])</button>
    <button type="button" class="shelf-btn" data-target="{{ $id }}" data-dir="1" aria-label="Scroll right">@include('partials.icon', ['n' => 'right'])</button>
  </div>
</div>
