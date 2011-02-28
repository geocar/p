<?php
include_once('rt.php');
include('parse.php');
include('compiler.php');

lisp_eval(parse('(funcall (php compile_file) "base.lisp" nil t)'));

