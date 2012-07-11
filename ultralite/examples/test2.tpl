# test2.tpl
Local: {{$favoritePet->name}} is the favorite.
This:  {{$this->favoritePet->name}} is the favorite.
@$favoritePet->name = 'Meow Meow';
Local: {{$favoritePet->name}} is the favorite. -- CHANGED
This:  {{$this->favoritePet->name}} is the favorite. -- CHANGED
