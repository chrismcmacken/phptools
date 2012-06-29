# test1.tpl
Local: {{name}} is {{age}} years old.
This:  {{this->name}} is {{this->age}} years old.
@$age /= 2
Local: {{name}} is {{age}} years old. -- CHANGED
This:  {{this->name}} is {{this->age}} years old. -- SAME
