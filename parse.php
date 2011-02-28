<?php // readers
function char($c){switch(strtolower($c)){
  case 'newline':return"\n";
  case 'return':return"\r";
  case 'tab':return"\t";
  case 'rubout':return"\b";
  case 'linefeed':return"\n";
  case 'page':return"\f";
  case 'space':return" ";
  default:return $c;
}}
abstract class _AbstractCharStream {
  abstract public function get();
  abstract public function unget();
  public function __toString(){return '#<stream>';}
};
class _StringCharStream extends _AbstractCharStream {
  public $i,$s,$n;
  public function get() {
    if($this->i >= $this->n){return null;}
    $c=$this->s{$this->i};$this->i++;return $c; }
  public function unget() { $this->i--; }
  public function __construct($s){ $this->s=$s;$this->n=strlen($s);$this->i=0; } }
class _FileCharStream extends _AbstractCharStream {
  public $fh,$c,$d;
  public function get() {
    if(!is_null($this->c)){$this->d=$this->c;$this->c=null;}else{$this->d=fgetc($this->fh);}
    if($this->d===false)return null;//eof
    return $this->d;}
  public function unget(){$this->c=$this->d;}
  public function __construct($fh){$this->fh=$fh;$this->c=null;}}
class _Token{public $c;public function __construct($c){$this->c=$c;}public function __toString(){return $this->c;}}
$TOKEN_EOF=new _Token('#<eof>'); $TOKEN_DOT=new _Token('#<dot>');
$TOKEN_CPAREN=new _Token('#<close-paren>');$TOKEN_CBRACKET=new _Token('#<close-bracket>');
$PARSE_SAFE=0;
function parse($s,$safe_p=1) {
  global $PARSE_SAFE;
  $old_safe = $PARSE_SAFE;
  $PARSE_SAFE=max($PARSE_SAFE,$safe_p);
  if(is_resource($s))$r=_parse1(new _FileCharStream($s));
  elseif(is_string($s))$r=_parse1(new _StringCharStream($s));
  elseif(is_object($s))$r=_parse1($s);
  else error('cant parse');
  $PARSE_SAFE=$old_safe;return $r;}
function _getc($h){
  while(true){
    do{$c=$h->get();}while(ctype_space($c));
    if(!is_string($c)||$c!=';')return $c;
    do{$c=$h->get();}while(is_string($c) && $c !="\n");}}//comments
function _parseA($h) {
  global $TOKEN_CBRACKET,$TOKEN_EOF;
  $a=array();
  for(;;){
    $c=_parse1($h);
    if($c===$TOKEN_CBRACKET)return $a;
    if($c===$TOKEN_EOF)error('unterminated array');
    $a[]=$c; }}
function _parseL($h,$t) {
  global $TOKEN_DOT,$TOKEN_EOF;
  $c=_parse1($h);
  if($c===$t)return null;
  if($c===$TOKEN_EOF)error('unbalanced paren');
  $g=$a=cons($c,null);
  while(true){
    $c=_parse1($h);
    if($c===$TOKEN_EOF)error('unbalanced paren');
    if($c===$TOKEN_DOT){
      $d=_parse1($h);
      $c=_parse1($h);
      if($d===$TOKEN_EOF)error('unbalanced paren');
      if($c===$TOKEN_EOF)error('unbalanced paren');
      if($c!==$t)error('syntax error');
      $a->cdr=$d;break;}
    if($c===$t){break;}
    $d=cons($c,null);
    $a->cdr=$d;
    $a=$d;} return $g;}
function _parseC($c,$h){
  if($c=='n')return "\n";
  if($c=='r')return "\r";
  if($c=='t')return "\t";
  return $c;}
function _parseS($h,$s){
  while(true){
    $c=$h->get();
    if(is_null($c))error('unterminated string');
    if($c=='"'){return $s;}
    if($c=="\\"){$c=_parseC($h);}
    $s.=$c;}}
function _parseW($h,$s){
  while(true){
    $c=$h->get();
    if(is_null($c))return $s;
    if($c=="\\"){$c=_parseC($h);}
    elseif(strchr("#[]().,'\"",$c)!==false){$h->unget();return $s;}
    elseif(ctype_space($c))return $s;
    $s.=$c;}}
function _parse1($h) {
  global $TOKEN_EOF,$TOKEN_DOT,$TOKEN_CPAREN,$TOKEN_CBRACKET;
  global $QUOTE,$BACKQUOTE,$UNQUOTE,$UNQUOTE_SPLICING,$FUNCTION;
  $c=_getc($h);
  if(is_null($c))return $TOKEN_EOF;
  if($c=='.')return $TOKEN_DOT;
  if($c==')')return $TOKEN_CPAREN;
  if($c==']')return $TOKEN_CBRACKET;
  if($c=='(')return _parseL($h,$TOKEN_CPAREN);
  if($c=='[')return _parseA($h);
  if($c=='"')return _parseS($h,'');
  if($c=="'")return cons($QUOTE,cons(_parse1($h),null));
  if($c=='`')return cons($BACKQUOTE,cons(_parse1($h),null));
  if($c=='#'){
    $c=$h->get();
    if($c=="'"){return cons($FUNCTION,cons(_parse1($h),null));}
    if($c=='<'){error('unreadable');}
    if($c==':'){return gensym(_parseW($h,''));}
    if($c=="\\"){return char(_parseW($h,''));}
    if($c=='!'){
      $c=_parseW($h,$c);
      foreach(_Symbol::$k as $k => $v){if($v->id == $c)return $v;}
      error('inaccessible');}
    if($c=='.'){return lisp_eval(_parse1($h));}
    if($c=='+'){$c=_parse1($h);$d=_parse1($h);if(_parseX($c))return $d;return _parse1($h);}
    if($c=='-'){$c=_parse1($h);$d=_parse1($h);if(!_parseX($c))return $d;return _parse1($h);}
    error("syntax error");}
  if($c==","){
    $c=$h->get();if($c=='@'){$t=$UNQUOTE_SPLICING;}else{$t=$UNQUOTE;$h->unget();}
    return cons($t,cons(_parse1($h),null));}
  $c=_parseW($h,$c);
  if(is_numeric($c)){return $c;}
  return intern($c);}

function lisp_load($s,$tracep=false) {
  global $TOKEN_EOF;
  global $UNINTERNED_CACHE_LOADER;
  if(($fh=fopen($s,"r"))===false)return null;
  $r=null;
  $tmp=$UNINTERNED_CACHE_LOADER;
  $UNINTERNED_CACHE_LOADER=array();
  for(;;) {
    $c=parse($fh);
    if($c===$TOKEN_EOF) break;
    $r=lisp_eval($c);
    if($tracep)echo tostring($r),"\n";
  }
  fclose($fh);
  $UNINTERNED_CACHE_LOADER=$tmp;
  return $r;
}
