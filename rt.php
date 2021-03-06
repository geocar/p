<?php
$DYNAMIC_FUNS=array();
$GLOBAL_MACROS=array();
$DYNAMIC_VARS=array();

$UNINTERNED_CACHE_LOADER=array();
function id($x){
  if(is_object($x)){return '#!'.$x->id;}
  return '#?'.tostring($x);
}
function tostring($s){
  if(is_null($s))return '()';
  if(is_array($s)){return '['.implode(' ',array_map('tostring',$s)).']';}
  if(is_object($s))return $s->__toString();
  return ''.$s;
}
function closurep($x){ return method_exists($x,'k');}
class _Symbol {
  static public $k=array();
  static public $n=1234;
  public $id,$name;
  static public function find_symbol($name,$id) {
    global $UNINTERNED_CACHE_LOADER;
    if(isset($UNINTERNED_CACHE_LOADER[$id]))return $UNINTERNED_CACHE_LOADER[$id];
    if(isset(_Symbol::$k[$name])) return _Symbol::$k[$name];
    return new _Symbol($name,$id);
  }
  function __construct($name,$id=null) {
    global $UNINTERNED_CACHE_LOADER;
    $this->name=$name;
    if(is_null($id)){
      $id=$this->id=_Symbol::$n;
      _Symbol::$n++;
    } else {
      $this->id=$id;
      _Symbol::$n = max(_Symbol::$n+1,$id);
    }
    $UNINTERNED_CACHE_LOADER[$id]=$this;
  }
  public function __toString(){
    $s=$this->name;
    if(_Symbol::$k[$s] === $this) return $s;
    return '#:'.$s;
  }
};
class _LispThrow extends Exception {
  static public $n=1234;
  public function __construct($v){$this->value=$v;}
}
function lisp_exception($k,$bk='Throw'){
  global $PHP, $LISP_NIL;
  if(is_null($k)) $k=$LISP_NIL;
  if(symbolp($k)) {
    $t = '_Lisp' . $bk . '_'.$k->id;
    $can = "can_lisp$bk";
    if(!isset($k->$can)){
      eval("class $t extends _LispThrow {};");
      $k->$can=true;
    }
    return $t;
  }
  if(!consp($k)||car($k)!==$PHP)error("not exception: $k");
  return cadr($k); // PHP Exception
}
function lisp_throw($k,$v){ $f=lisp_exception($k); throw new $f($v); }
function lisp_return_from($k,$v){ $f=lisp_exception($k,'Return'); throw new $f($v); }
function lisp_return($v){ return lisp_return_from(null,$v); }
function lisp_catch($k,$f){
  $t=lisp_exception($k);
  $s='_LispCatch_'._LispThrow::$n;
  _LispThrow::$n++;
  $o=array();
  $o[]='function ';
  $o[]=$s;
  $o[]='($f){try{return $f->k();}catch(';
  $o[]=$t;
  $o[]=' $e){return $e->value;}}';
  eval(implode('',$o));
  return $s($f);
}
class _NullIterator implements Iterator,Countable{
  public function __construct() { }
  public function current(){ return false;}
  public function key(){return false;}
  public function next(){}
  public function rewind(){}
  public function valid(){return false;}
  public function count() {return 0;}
};
$NULL_ITERATOR=new _NullIterator();
function NI($s){global $NULL_ITERATOR;if(is_null($s))return $NULL_ITERATOR;return $s;}
class _ConsIterator implements Iterator{
  public $first;
  public $x;
  public $i;
  public function __construct($first) { $this->first=$this->x=$first;$this->i=0; }
  public function current() { return car($this->x); }
  public function key() { return $this->i; }
  public function next() { $this->i++; $this->x=cdr($this->x); }
  public function rewind() { $this->x=$this->first;$this->i=0; }
  public function valid() { return !is_null($this->x); }
};
class _Cons implements Countable,IteratorAggregate{
  public $car;
  public $cdr;
  public $id;
  function getIterator(){ return new _ConsIterator($this); }
  function __construct($a,$b) {
    $this->car=$a;
    $this->cdr=$b;
    $this->id=_Symbol::$n;
    _Symbol::$n++;
  }
  function __toString() {
    $o=array();
    for($p=$this;$p;){
      $o[] = tostring($p->car);
      $p=$p->cdr;
      if($p!==null&&!consp($p)){ $o[] = '.'; $o[] = tostring($p); break; }}
    return '('.implode(' ',$o).')';
  }
  public function count() {
    $x=$this;
    for($i=0;consp($x)&& !is_null($x);++$i) $x=$x->cdr;
    return $i;
  }
};
function cons($a,$b){return new _Cons($a,$b);}
function consp(&$x){return @get_class($x)=='_Cons';}
function car(&$x){return consp($x)?$x->car:null;}
function cdr(&$x){return consp($x)?$x->cdr:null;}
function caar(&$x){return car(car($x));}
function cadr(&$x){return car(cdr($x));}
function cddr(&$x){return cdr(cdr($x));}
function caddr(&$x){return car(cdr(cdr($x)));}
function cdddr(&$x){return cdr(cdr(cdr($x)));}
function symbolp(&$x){return @get_class($x)=='_Symbol';}
function keywordp(&$x){return symbolp($x)&&$x->name{0}==':';}
function array2cons($a){ $d=null;for($i=count($a)-1;$i>=0;--$i){$d=cons($a[$i],$d);}return $d;}
function length($x) {
  if(is_null($x))return 0;
  if(is_string($x))return strlen($x);
  if(is_object($x)&&method_exists($x,'count'))return $x->count();
  return count($x); }
function gensym($s=null){
  if(is_null($s)){$s="G"._Symbol::$n;}
  return new _Symbol($s); }
function intern($s) {
  global $COMPILER_FH;
  if(symbolp($s))$s=$s->name;
  elseif(!is_string($s))$s=tostring($s);
  if(isset(_Symbol::$k[$s]))return _Symbol::$k[$s];
  $j = new _Symbol($s);
  if(!is_null($COMPILER_FH)) {
    fputs($COMPILER_FH,'_Symbol::$k["'.addslashes($s).'"]=new _Symbol("'.addslashes($s).'",');
    fputs($COMPILER_FH,$j->id.');'."\n");
  }
  _Symbol::$k[$s] = $j;
  return $j; }
function unintern(&$s) {
  global $COMPILER_FH;
  $j=intern($s);
  unset(_Symbol::$k[$j->name]);
  if(!is_null($COMPILER_FH)) {
    fputs($COMPILER_FH,'unset(_Symbol::$k["'.addslashes($s).'"]);'."\n");
  }
}
// interns for the parser
$QUOTE=intern('quote');$BACKQUOTE=intern('backquote');
$UNQUOTE=intern('unquote');$UNQUOTE_SPLICING=intern('unquote-splicing');

// stuff for the compiled code
$CAR = intern('car'); $CDR = intern('cdr');
$LAMBDA = intern('lambda'); $FUNCTION = intern('function'); $ARGS = intern('args');
$LET = intern('let'); $FLET = intern('flet'); $FUNCALL = intern('funcall');
$SETF = intern('setf'); $PROGN=intern('progn');$PROG1=intern('prog1');
$MAPCAR=intern('mapcar');$APPLY=intern('apply');$APPEND=intern('append');
$OR=intern('or'); $AND=intern('and');
$PLUS = intern('+'); $MINUS = intern('-');
$TIMES = intern('*'); $DIVIDE = intern('*'); $MOD = intern('mod');
$LT=intern('<');$GT=intern('>');$LTE=intern('<=');$GTE=intern('>=');
$EQL=intern('=');$NE=intern('/=');$EQ=intern('eq');$AREF=intern('aref');
$DEFUN = intern('defun');$DEFVAR=intern('defvar');$DEFMACRO=intern('defmacro');$RETURN=intern('return');
$PHP= intern('php');$LISP_T=intern('t');$LISP_NIL=intern('nil');$IF=intern('if');
$THROW=intern('throw');$CATCH=intern('catch');
function PROGN(){$n=func_num_args();return $n?func_get_arg($n-1):null;}
function PROG1(){$n=func_num_args();return $n?func_get_arg(0):null;}
function _V1($a,&$b,$c,$d) { $b=$a;return $d;}
function _V2($c){ $a=array();foreach($c as $k=>$v){$a[$k]=$v;} return $a;}
function MAPCAR($f){ $n=func_num_args(); $a=array();
  for($i=1;$i<$n;++$i){foreach(NI(func_get_arg($i)) as $c){$a[]=$f->k($c);}}
  return array2cons($a);}
function DOLIST($f,$a){foreach($a as $x){$f->k($x);}}
function DOTIMES($f,$n){for($i=0;$i<$n;++$i){$f->k($i);}}
function APPEND(){ $n=func_num_args(); $a=array();
  for($i=0;$i<$n;++$i){ foreach(NI(func_get_arg($i)) as $c){$a[]=$c;}}
  return array2cons($a);}
 
function APPLY($f){ $n=func_num_args(); $a=array();
  for($i=1;$i<($n-1);++$n)$a[]=func_get_arg($i);
  if($n>1){foreach(NI(func_get_arg($n-1)) as $c){$a[]=$c;}}
  return call_user_func_array(array($f,'k'),$a);}

function error($c){throw new Exception(tostring($c));}
function MACEX($c,&$f,&$q){
  global $GLOBAL_MACROS,$PHP,$QUOTE,$FUNCTION;
  global $BACKQUOTE,$UNQUOTE,$UNQUOTE_SPLICING;
  if(!consp($c))return $c;
  $a=car($c);
  if(symbolp($a)){
    if(!$q&&isset($GLOBAL_MACROS[$n=id($a)])){$f=true;
      return APPLY($GLOBAL_MACROS[$n],cdr($c));}
    if($a===$PHP||$a===$QUOTE||$a===$FUNCTION)return $c;
    if($a===$BACKQUOTE)$q++;
    elseif($a===$UNQUOTE||$a===$UNQUOTE_SPLICING)$q--; }
  return cons(MACEX(car($c),$f,$q),MACEX(cdr($c),$f,$q)); }
function macroexpand1($c){$q=0;$y=false;return MACEX($c,$y,$q);}
function macroexpand($c){$y=true;$q=0;do{$y=false;$c=MACEX($c,$y,$q);}while($y);
  return $c;}

function AREF_PUT($a,$b,$c){
  if(consp($a)){for(;$b>0&&$a;$b--)$a=cdr($a);if($b)error("bounds");return $a->car=$c;}
  if(is_array($a))return $a[$b]=$c;
  error("type");
}
function AREF_GET($a,$b){
  if(consp($a)){for(;$b>0&&$a;$b--)$a=cdr($a);if($b)error("bounds");return $a->car;}
  if(is_array($a))return $a[$b];
  error("type");
}
function quit($x=0){exit($x);}
function lisp_null($x){global $LISP_T;return $x?null:$LISP_T;}
function lisp_consp($x){global $LISP_T;return consp($x)?$LISP_T:null;}
function lisp_arrayp($x){global $LISP_T;return is_array($x)?$LISP_T:null;}
function lisp_stringp($x){global $LISP_T;return is_string($x)?$LISP_T:null;}
function lisp_symbolp($x){global $LISP_T;return symbolp($x)?$LISP_T:null;}
