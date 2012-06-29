# test3.tpl
-- This won't work
@foreach ($pets as $pet):
@$this->inc('test3b.tpl')
@endforeach
-- This does work
@foreach ($pets as $pet):
@$this->inc('test3b.tpl', array('pet', $pet))
@endforeach
