;; a lisp in php
<?php
include('base.fasl');

include('parse.php');
include('compiler.php');

$fh=fopen('php://stdin','r');
for(;;) {
  echo "> ";
  flush();
  try {
    lisp_print(lisp_eval(parse($fh)));
  } catch(Exception $e) {
    echo ";; ", $e->getMessage(), "\n";
  }
}

