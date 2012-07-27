# test3.tpl
-- This won't work
@foreach ($pets as $pet):
{{>test3b.tpl}}
@endforeach
-- This does work
@foreach ($pets as $pet):
{{>test3b.tpl pet=$pet}}
@endforeach
