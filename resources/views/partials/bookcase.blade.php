<section class="case-wrap">
  @isset($title)
  <div class="shelf-head">
    <h2 dir="auto">{{ $title }}</h2>
    <div class="shelf-tools">@isset($link)<a class="seeall" href="{{ $link }}">See all{{ isset($count) ? ' (' . $count . ')' : '' }}</a>@endisset</div>
  </div>
  @endisset
  <div class="case"><div class="case-inner">
    @foreach($books as $b)@include('partials.bk', ['book' => $b])@endforeach
  </div></div>
</section>
