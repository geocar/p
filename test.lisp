(defvar tst '(1 2 3))
`(a b ,#'length c ,@tst d)
(defmacro cow (x) `(quote ,x))
(cow (woot))
(defun foo (x) (lambda (y) (setf x (+ x y))))
#'foo
(defvar bar (foo 3))
(funcall bar 4)
(funcall bar 2)
(funcall bar 2)
(funcall bar 2)
(if nil 1 2 3 4)
(append '(0) '(1 2 3))
[1 2 3]
(let ((tst 234)) tst)
tst
(setf (aref tst 1) 'q)
tst
(dolist (x '(a b c d e))
  (print x))
(dotimes (x 5)
  (print x))
