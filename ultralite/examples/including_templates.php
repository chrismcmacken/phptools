<?php // including_templates.php
require_once('../Ultralite.php');
$ul = new Ultralite(__DIR__);
$ul->name = "Jane Doe";
$ul->age = 42;
$cat = new stdClass();
$cat->name = "Whiskers";
$ul->pets = array($cat);
$ul->favoritePet = $cat;
echo $ul->render('test1.tpl');
echo $ul->render('test2.tpl');
echo $ul->render('test3.tpl');
