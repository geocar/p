<?php
include_once('rt.php');
include('parse.php');
include('compiler.php');

lisp_eval(parse('((php compile_file) "base.lisp")'));

