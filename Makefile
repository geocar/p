base.fasl:base.lisp rt.php compiler.php parse.php boot.php
	php boot.php
clean:
	rm -f base.fasl
test: test.lisp base.fasl test.php compiler.php parse.php rt.php
	php test.php
repl: base.fasl repl.php compiler.php parse.php rt.php
	php repl.php
backup:
	scp * sdf:p/.
