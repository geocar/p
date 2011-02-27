<?php
include_once('base.fasl');
include('parse.php');
include('compiler.php');

global $LISP_T;lisp_load("test.lisp",$LISP_T);
