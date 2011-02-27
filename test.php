<?php
include_once('base.fasl');
include('parse.php');
include('compiler.php');

lisp_print(lisp_eval(parse("(defvar tst '(1 2 3))")));
lisp_print(lisp_eval(parse("`(a b ,#'length c ,@tst d)")));
lisp_print(lisp_eval(parse("(defmacro cow (x) `(quote ,x))")));
lisp_print(lisp_eval(parse("(cow (woot))")));
lisp_print(lisp_eval(parse("(defun foo (x) (lambda (y) (setf x (+ x y))))")));
lisp_print(lisp_eval(parse("#'foo")));
lisp_print(lisp_eval(parse("(defvar bar (foo 3))")));
lisp_print(lisp_eval(parse("(funcall bar 4)")));
lisp_print(lisp_eval(parse("(funcall bar 2)")));
lisp_print(lisp_eval(parse("(funcall bar 2)")));
lisp_print(lisp_eval(parse("(funcall bar 2)")));
lisp_print(lisp_eval(parse("(if nil 1 2 3 4)")));
lisp_print(lisp_eval(parse("(append '(0) '(1 2 3))")));
lisp_print(lisp_eval(parse("[1 2 3]")));
lisp_print(lisp_eval(parse("(let ((tst 234)) tst)")));
lisp_print(lisp_eval(parse("tst")));
