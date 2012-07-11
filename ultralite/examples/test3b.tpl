# test3b.tpl
@if (isset($pet)):
Pet name: {{$pet->name}}
@else:
No pet object passed.
@endif
