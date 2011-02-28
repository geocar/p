<?php // compiler
$COMPILER_FH=null;
$COMPILER_VERBOSE=false;
$COMPILER_PRE='global $LISP_T,$LISP_NIL,$DYNAMIC_VARS,$DYNAMIC_FUNS,$GLOBAL_MACROS,$NULL_ITERATOR;';
function compiler_noise($s1) {
  global $COMPILER_VERBOSE;
  if($COMPILER_VERBOSE) {
    echo ";; ",$s1,"\n";
  }
}
function const_data($k) {
  global $LISP_T,$LISP_NIL;

  if(is_null($k))return 'null';
  if($k===$LISP_T)return '$LISP_T';
  if($k===$LISP_NIL)return '$LISP_NIL';
  if(symbolp($k)){return '_Symbol::find_symbol("'.addslashes($k->name).'",'.$k->id.')';}
  if(is_numeric($k)) return $k;
  if(is_string($k))return '"'.addslashes($k).'"';
  if(consp($k)){return 'cons('.const_data($k->car).','.const_data($k->cdr).')';}
  if(is_array($k)){
    $o=array(); foreach($k as $x){$o[]=const_data($x);}
    return 'array('.implode(',',$o).')';}
  return 'unserialize("'.addslashes(serialize($k)).'")';
}
function eval_helper($r){
  global $COMPILER_FH;
  if(!is_null($COMPILER_FH))fputs($COMPILER_FH,$r);
  if(false===($g=eval($r))) {
    echo ";; COMPILER BUG $r\n";
  }
  return $g;
}
function compile_file($in,$out=null,$verbose=false) {
  global $COMPILER_FH,$COMPILER_PRE,$COMPILER_VERBOSE;
  if(is_null($out)){
    $out=substr($in, 0, strrpos($in, '.')).'.fasl'; 
  }
  $oldv=$COMPILER_VERBOSE;
  $tmp=$COMPILER_FH;
  $COMPILER_VERBOSE=$verbose;
  compiler_noise("reading \"$in\"");
  $COMPILER_FH=fopen("$out.tmp",'w');
  fputs($COMPILER_FH,'<');
  fputs($COMPILER_FH,'?');
  fputs($COMPILER_FH,"php include_once(\"rt.php\");\n");
  $r = lisp_load($in);
  fputs($COMPILER_FH,"\nPROGN(");
  fputs($COMPILER_FH,const_data($r));
  fputs($COMPILER_FH,");\n");
  fclose($COMPILER_FH);
  $COMPILER_FH=$tmp;
  compiler_noise("creating \"$out\"");
  $COMPILER_VERBOSE=$oldv;
  rename("$out.tmp","$out");
  return $r;
}
class _CompilationUnit {
  static public $n=1234;
  static public $LAMBDA_OP=array();
  public function lambda_op($name,$f){//wraps php operator as lambda
    if(isset(_CompilationUnit::$LAMBDA_OP[$f])){ return _CompilationUnit::$LAMBDA_OP[$f]; }
    $o=array();

    $s = $this->genid();

    $o[] = "function $s(){ return new $s(); }; class $s { ";
    $o[] = 'public function docstring() { return "';
    $o[] = $name;
    $o[] = ' operator"; }';
    $o[] = 'public function prototype() { global $ARGS;return $ARGS; } ';
    $o[] = 'public function __toString(){return "#<operator ';
    $o[] = $name;
    $o[] = '>"; } public function __construct($ignored=null) { }';
    $o[] = 'public function __invoke($d=0) { $n=func_num_args();';
    $o[] = 'for($i=1;$i<$n;++$i){$c=func_get_arg($i);$d = $d ';
    $o[] = $f;
    $o[] = ' $c;} return $d;} }';
    $o[] = ";\n";
    eval_helper(implode('',$o));
    return _CompilationUnit::$LAMBDA_OP[$f] = "$s()";
  }
  public function lambda_fun($name){//wraps php function as lambda
    if(isset(_CompilationUnit::$LAMBDA_OP[$name])){ return _CompilationUnit::$LAMBDA_OP[$name]; }
    $o=array();

    $s = $this->genid();

    $o[] = "function $s(){return new $s();}; class $s { ";
    $o[] = 'public function docstring() { return "';
    $o[] = $name;
    $o[] = ' primitive"; }';
    $o[] = 'public function prototype() { global $ARGS;return $ARGS; } ';
    $o[] = 'public function __toString(){return "#<primitive ';
    $o[] = $name;
    $o[] = '>"; } public function __construct($ignored=null) { }';
    $o[] = 'public function __invoke() { return call_user_func_array(';
    $o[] = "'$name',func_get_args()); } };\n";
    eval_helper(implode('',$o));
    return _CompilationUnit::$LAMBDA_OP[$name] = "$s()";
  }

  public function __construct($p) {
    $this->vars = array();
    $this->funs = array();
    $this->parent = $p;
    $this->n=1;
    $this->id=_CompilationUnit::$n;
    _CompilationUnit::$n++;
  }

  public function backquote($c,$d=0) {
    global $BACKQUOTE,$UNQUOTE,$UNQUOTE_SPLICING;
    if($d==-1)return $this->compile_expr($c);
    if(!consp($c))return const_data($c);
    if(($a=car($c))===$BACKQUOTE)return $this->backquote(cadr($c),$d+1);
    if($a===$UNQUOTE)return $this->backquote(cadr($c),$d-1);
    if(consp($a)&&car($a)===$UNQUOTE_SPLICING){
      return 'APPEND(' . $this->backquote(cadr($a),$d-1) . ',' . $this->backquote(cdr($c),$d).')';
    }
    return 'cons('.$this->backquote($a,$d).','.$this->backquote(cdr($c),$d).')';
  }

  public function flet($var) {
    global $DYNAMIC_FUNS;
    $y=id($var);
    if(isset($DYNAMIC_FUNS[$y])) {
      return '$DYNAMIC_FUNS["' . $y . '"]';
    }
    $s = 'F' . count($this->funs);
    $this->funs[] = $var;
    return $s;
  }
  public function fbound($s) {
    global $DYNAMIC_FUNS;
    $y=id($s);
    if(isset($DYNAMIC_FUNS[$y])) { return '$DYNAMIC_FUNS["' . $y . '"]';}
    for($i = count($this->funs)-1; $i >= 0; --$i)
      if($this->funs[$i] === $s) return '$this->F'.$i;
    $z = '$this->p'; $p = $this->parent;
    while($p) {
      for($i = count($p->funs)-1; $i >= 0; --$i)
        if($p->funs[$i] === $s) return $z.'->F'.$i;
      $p = $p->parent;
      $z .= '->p';
    }
    return null;
  }
  public function let($var) {
    global $DYNAMIC_VARS,$LISP_T,$LISP_NIL;
    if($var===$LISP_T||$var===$LISP_NIL)error('no');
    if(!is_null($var)){
      $y=id($var);
      if(isset($DYNAMIC_VARS[$y])) { return '$DYNAMIC_VARS["' . $y . '"]';}
    }
    $s = 'V' . count($this->vars);
    $this->vars[] = $var;
    return $s;
  }
  public function bound($s) {
    global $DYNAMIC_VARS,$LISP_T,$LISP_NIL;
    if($s===$LISP_T){return '$LISP_T';}
    if($s===$LISP_NIL){return 'null';}
    if($s->name{0}==':'){return const_data($s);} // keywordp

    $y=id($s);
    if(isset($DYNAMIC_VARS[$y])) { return '$DYNAMIC_VARS["' . $y . '"]';}
    for($i = count($this->vars)-1; $i >= 0; --$i)
      if($this->vars[$i] === $s) return '$this->V'.$i;
    $z = '$this->p'; $p = $this->parent;
    while($p) {
      for($i = count($p->vars)-1; $i >= 0; --$i)
        if($p->vars[$i] === $s) return $z.'->V'.$i;
      $p = $p->parent;
      $z .= '->p';
    }
    return null;
  }

  public function compile_args($y) {
    $o=array();
    for(;$y;$y=cdr($y)){$o[]=$this->compile_expr(car($y));}
    return $o;
  }
  public function _compile_vlet($f,$v,$e,$y,$b) {
    $o=array();
    for(;$y;$y=cdr($y)){
      if(symbolp($a=car($y)))$this->$f($a);
      elseif(!consp($a))error('no '.$f);//no let, no flet
      else{$j=$this->$f(car($a));$k=$this->$e(cadr($a),caddr($a));$o[]="(\$$j=($k))";}}
    for(;$b;$b=cdr($b)){
      $o[]=$this->compile_expr(car($b));
    }
    return 'PROGN(_V1($DYNAMIC_'.$v.',$DYNAMIC_'.$v.',$DYNAMIC_'.$v.'=_V2($DYNAMIC_'.$v.'),PROGN('.implode(',',$o).')))';
  }
  public function compile_let($y,$b){return $this->_compile_vlet('let','VARS','compile_expr',$y,$b);}
  public function compile_flet($y,$b){return $this->_compile_vlet('flet','FUNS','compile_fun',$y,$b);}
  public function compile_fun($c,$d) { return $this->compile_lambda(null, $c,$d).'($this)'; }


  function getf($y){
    global $LISP_T,$LISP_NIL;
    global $AREF, $CAR, $CDR, $FUNCTION;

    if($y===$LISP_T||$y===$LISP_NIL)error('cant getf: '.tostring($y));
    if(symbolp($y)){if(is_null($g=$this->bound($y)))error('not bound: '.tostring($y));return $g;}
    if(!consp($y))error('cant getf: '.tostring($y));
    $d=car($y); $a=cadr($y);
    if($d===$CAR||$d===$CDR)return $this->compile_expr($a).'->'.$d->name;
    if($d===$FUNCTION)return '$DYNAMIC_FUNS["'.addslashes(id($a)).'"]';
    error('cant getf: '.tostring($y));}
  public function compile_setf($y,$val) {
    global $AREF;
    if(consp($y)){
      $a=car($y);
      if($a===$AREF){return 'AREF_PUT('.$this->getf(cadr($y)).','.$this->compile_expr(caddr($y)).','.$val.')';}
    }
    return $this->getf($y).'='.$val;}
  public function compile_expr($c) {
    global $LAMBDA, $FUNCTION, $CAR, $CDR;
    global $LET, $FLET, $FUNCALL, $SETF, $PROGN,$PROG1,$MAPCAR,$APPLY,$APPEND;
    global $PLUS,$MINUS,$TIMES,$DIVIDE,$MOD;
    global $LT,$GT,$LTE,$GTE,$EQL,$NE,$EQ;
    global $DEFUN,$DEFVAR,$DEFMACRO;
    global $DYNAMIC_FUNS,$DYNAMIC_VARS,$GLOBAL_MACROS;
    global $QUOTE,$PHP,$IF,$BACKQUOTE,$AREF;
    global $LISP_T,$LISP_NIL;
    global $COMPILER_FH,$COMPILER_PRE;
    if(symbolp($c)){
      if(is_null($d=$this->bound($c)))error('not bound: '.tostring($c));
      return $d;}
    if(!consp($c)) return const_data($c);
    $g='->__invoke';
    if(!symbolp($a=car($c))){
      if(!consp($a)||!symbolp($d=car($a)))error('not fun: '.tostring($a));
      if($LAMBDA===$d||$FUNCTION===$d){$f=$this->compile_expr($a);}//fall thru
      else error('not fun: '.tostring($a));}
    elseif($a===$LAMBDA){$a=cdr($c);return $this->compile_fun(car($a),cdr($a));}
    elseif($a===$QUOTE){return const_data(cadr($c));}
    elseif($a===$PHP){$f=cadr($c);if(!symbolp($f)){error('not php');}
      return $this->lambda_fun($f->name);}
    elseif($a===$BACKQUOTE){return $this->backquote(cadr($c));}
    elseif($a===$FUNCTION){
      if(!is_null($y=$this->fbound($a=cadr($c))))return $y;
      if(consp($a) && car($a)===$PHP)return $this->compile_expr($a);
      if($a===$CAR)return $this->lambda_fun('car');
      if($a===$CDR)return $this->lambda_fun('cdr');
      if($a===$APPLY)return $this->lambda_fun('APPLY');
      if($a===$MAPCAR)return $this->lambda_fun('MAPCAR');
      if($a===$APPEND)return $this->lambda_fun('APPEND');
      if($a===$PLUS)return $this->lambda_op('plus','+');
      if($a===$MINUS)return $this->lambda_op('minus','-');
      if($a===$TIMES)return $this->lambda_op('times','*');
      if($a===$DIVIDE)return $this->lambda_op('divide','/');
      if($a===$MOD)return $this->lambda_op('mod','%');
      if($a===$LT)return $this->lambda_op('lt','<');
      if($a===$LTE)return $this->lambda_op('lte','<=');
      if($a===$GT)return $this->lambda_op('gt','>');
      if($a===$GTE)return $this->lambda_op('gte','>=');
      if($a===$EQL)return $this->lambda_op('eql','==');
      if($a===$NE)return $this->lambda_op('ne','!=');
      if($a===$EQ)return $this->lambda_op('eq','===');
      error('not fbound: '.tostring($a));}
    elseif($a===$LET){ return $this->compile_let(cadr($c),cddr($c)); }
    elseif($a===$FLET){ return $this->compile_flet(cadr($c),cddr($c)); }
    elseif($a===$AREF){ return 'AREF_GET('.$this->compile_expr(cadr($c)).','.$this->compile_expr(caddr($c)).')';}
    elseif($a===$IF){
      $o=array();
      $d=true;
      $k=array();
      for($c=cdr($c);$c;$c=cdr($c)){
        $o[] = '(' . $this->compile_expr(car($c));
	if($d)$k[] = ')'; $o[] = $d ? '?' : '):'; $d=!$d; }
      if(count($o)==0)return 'null';
      array_pop($o); if($d)$o[] = "):null"; return implode('',$o).implode('',$k);}
    elseif($a===$PROGN){$f='PROGN';$g='';}
    elseif($a===$PROG1){$f='PROG1';$g='';}
    elseif($a===$MAPCAR){$f='MAPCAR';$g='';}
    elseif($a===$APPEND){$f='APPEND';$g='';}
    elseif($a===$APPLY){$f='APPLY';$g='';}
    elseif($a===$CAR){$f='car';$g='';}
    elseif($a===$CDR){$f='cdr';$g='';}
    elseif($a===$SETF){return $this->compile_setf(cadr($c),$this->compile_expr(caddr($c)));}
    elseif($a===$PLUS){return implode('+',$this->compile_args(cdr($c)));}
    elseif($a===$MINUS){return implode('-',$this->compile_args(cdr($c)));}
    elseif($a===$TIMES){return implode('*',$this->compile_args(cdr($c)));}
    elseif($a===$DIVIDE){return implode('/',$this->compile_args(cdr($c)));}
    elseif($a===$MOD){return implode('%',$this->compile_args(cdr($c)));}
    elseif($a===$LT){return implode('<',$this->compile_args(cdr($c)));}
    elseif($a===$LTE){return implode('<=',$this->compile_args(cdr($c)));}
    elseif($a===$GT){return implode('>',$this->compile_args(cdr($c)));}
    elseif($a===$GTE){return implode('>=',$this->compile_args(cdr($c)));}
    elseif($a===$EQL){return implode('==',$this->compile_args(cdr($c)));}
    elseif($a===$NE){return implode('!=',$this->compile_args(cdr($c)));}
    elseif($a===$EQ){return implode('===',$this->compile_args(cdr($c)));}
    elseif($a===$FUNCALL){
      $a=cadr($c);
      if(consp($a) && car($a)===$PHP) {
        $f=cadr($a);$g='';if(!symbolp($f)){error('not php');}
	$f=$f->name; }
      else { $f=$this->compile_expr($a);}
      $c=cdr($c);}
    elseif($a===$DEFMACRO){$y=cadr($c);
      compiler_noise("compiling (macro ".tostring($y).")");
      $f = $this->compile_lambda($y, caddr($c),cdddr($c));
      $d=id($y); unset($DYNAMIC_FUNS[$d]);
      $GLOBAL_MACROS[$d] = new $f(null);
      if(!is_null($COMPILER_FH)) {
        $y=addslashes($d);
        fputs($COMPILER_FH,'unset($DYNAMIC_FUNS["'.$y.'"]);'."\n");
        fputs($COMPILER_FH,'$GLOBAL_MACROS["'.$y.'"]=new '.$f."(null);\n");
      }
      return const_data($y);}
    elseif($a===$DEFUN){$y=cadr($c);
      compiler_noise("compiling (function ".tostring($y).")");
      $d=id($y); unset($GLOBAL_MACROS[$d]);
      $DYNAMIC_FUNS[$d] = true;
      $f = $this->compile_lambda($y, caddr($c),cdddr($c));
      $DYNAMIC_FUNS[$d] = new $f(null);

      if(!is_null($COMPILER_FH)) {
        $y=addslashes($d);
        fputs($COMPILER_FH,'unset($GLOBAL_MACROS["'.$y.'"]);'."\n");
        fputs($COMPILER_FH,'$DYNAMIC_FUNS["'.$y.'"]=new '.$f."(null);\n");
      }
      return const_data($y);}
    elseif($a===$DEFVAR){$y=cadr($c);
      compiler_noise("compiling (var ".tostring($y).")");
      $r = cddr($c) ? $this->compile_expr(caddr($c)) : 'null';
      $x='$DYNAMIC_VARS["'.addslashes(id($y)).'"]='.$r.";\n";
      eval_helper($COMPILER_PRE.$x);return const_data($y);}
    elseif(is_null($f=$this->fbound($a))){error('not fbound: '.tostring($a));}
    return $f.$g.'('.implode(',',$this->compile_args(cdr($c))).')';
  }
  public function genid() {
    $s = '_CompiledLambda_'.$this->id.'_'.$this->n.'_'.mt_rand().'_'.time();
    $this->n++;
    return $s;
  }
  public function compile_lambda($name,$a, $c) {
    $o = array();
    $s = $this->genid();

    $o[] = "function $s(\$p){return new $s(\$p);}; class $s { public function docstring() { return ";
    if(is_string(car($c)) && cdr($c)){
      $o[] = '"' . addslashes(car($c)) . '"';
      $c = cdr($c);
    } else {
      $o[] = 'null';
    }

    $o[] = '; } function prototype() { return ' . const_data($a) . '; }';

    $o[] = 'function __toString(){return "#<';
    if(is_null($name)){
      $o[] = 'closure';
    }else{
      $o[] = 'function ';
      $o[] = symbolp($name)?$name->name:$name;
    }
    $o[] = '>";} function __construct($p){$this->p=$p;} function __invoke(';
    $comma='';

    $g=new _CompilationUnit($this);
    for($x = $a; $x; $x = cdr($x)) {
      $y=consp($x)?car($x):$x;
      $o[] = $comma . '$' . $g->let($y);
      if(!consp($x)) $o[] = '=null';
      $comma=',';
    }
    $o[] = ') { global $DYNAMIC_FUNS,$DYNAMIC_VARS,$LISP_T,$LISP_NIL;';
    for($i = 0, $x = $a; $x; ++$i, $x = cdr($x)) {
      if(consp($x)){
        $o[] = '$this->'.$g->let(car($x)).'=func_get_arg('.$i.');';
      }else{
        $y='$this->'.$g->let($x);
        $o[] = $y;
	$o[] = '=null;';
        $o[] = 'for($i=func_num_args()-1;$i>=';
        $o[] = $i;
        $o[] = ';--$i){';
        $o[] = $y;
	$o[] = '=cons(func_get_arg($i),';
        $o[] = $y;
	$o[] = '); }';
        break;
      }
    }
    for($d = null; $c; $c = $d) {
      $d = cdr($c);
      if(!$d) {
        $o[] = 'return ';
      }
      $o[] = $g->compile_expr(car($c));
      $o[] = ';';
    }
    $o[] = "} };\n";
    eval_helper(implode('', $o));
    return $s;
  }
}
function lisp_eval($c){
  if(is_null($c)||is_string($c)||is_numeric($c)||closurep($c))return $c;
  global $PARSE_SAFE; if($PARSE_SAFE > 0)error('unsafe');
  global $COMPILER_FH,$COMPILER_PRE;
  $x=new _CompilationUnit(null);
  $r=$x->compile_expr(macroexpand($c));
  if(!is_null($COMPILER_FH)) {
    fputs($COMPILER_FH,"$r;\n");
  }
  return eval($COMPILER_PRE.'$r='.$r.';return $r;');
}
function lisp_print($s) {
  if(is_null($s)){echo "nil\n";}
  elseif(is_array($s)){echo'[';$c='';foreach($s as $x){echo $c,$x;$c=' ';}echo "]\n";}
  else{echo $s;echo "\n";}
}

