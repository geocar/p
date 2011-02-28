# ;; a lisp in php

this is a lisp compiler that targets php.

it is designed to support *programming in lisp*, and not simply *calling out to lisp* from php;
lisp is the first class citizen, semantics are chosen to be familiar to lisp programmers, and
effort is taken to prevent php from leaking into lisp.

## features

* a real compiler
* closures. even in php 5.2
* lisp data structures
* array syntax `[using square brackets]`

## quickstart

    make repl

