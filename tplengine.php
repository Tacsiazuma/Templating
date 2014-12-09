<?php
if (! defined ( 'ENVIRONMENT' ))
	exit ( 'Direct script access is forbidden.' );


// Constants
DEFINE ( 'LEFT_DELIMITER', '{' );
DEFINE ( 'RIGHT_DELIMITER', '}' );
DEFINE ( 'TEMPLATE_EXTENSION', '.tpl' );
DEFINE ( 'HTML_EXTENSION', '.html' );
DEFINE ( 'TEMPLATE_DIR', VIEWPATH . "template/" );
DEFINE ( 'HANDLE_AS_UTF8', false );
DEFINE ( 'INCLUDE_SYNTAX', 'include' );
DEFINE ( 'TPL_EOF', 0 );
DEFINE ( 'TPL_STARTTAG', 1 );
DEFINE ( 'TPL_ENDTAG', 2 );
DEFINE ( 'TPL_SYMBOL', 3 );
DEFINE ( 'TPL_HTML', 4 );
DEFINE ( 'TPL_FILTER', 5 );
DEFINE ( 'TPL_COMMENT', 6 );
DEFINE ( 'TPL_IDENTIFIER', 7 );
DEFINE ( "TPL_FOREACH", 8 );
DEFINE ( "TPL_EACH", 9 );
DEFINE ( "TPL_AUTOESCAPEON", 10 );
DEFINE ( "TPL_AUTOESCAPEOFF", 11 );
DEFINE ( "TPL_FILTERVAR", 12 );
DEFINE ( "TPL_FILTERVARSYMBOL", 13 );
DEFINE ( "TPL_EXPRESSION", 14 );
DEFINE ( "TPL_INCLUDEFILE", 15 );

/**
 * Template parser engine
 * 
 * Usage: simply hand in a file name present in the template directory WITHOUT the .tpl extension
 * then call the display function to generate the HTML output file and display it.
 * $t = new Templating({filename without path and extension}, {the html output filename});
 * $t->display({ttl});
 * 
 * 
 * @todo add inline documentation at every key part
 * @todo add generate method to the API
 * @todo make it independent from our framework
 * @todo add some preloading solutions
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 * 
 * 
 */

function getConstantName($category, $constantNumber) {
	foreach ( get_defined_constants () as $key => $value )
		if (strlen ( $key ) > strlen ( $category ))
			if (substr ( $key, 0, strlen ( $category ) ) == $category)
				if ($value == $constantNumber)
					return $key;
	return "No constant found.";
}

/**
 * The AST class makes the abstract syntax tree from the node stream
 *
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *        
 */
class AST {
	private $root;
	var $nodeStream;
	var $currentNode;
	public function __construct($nodestream) {
		$this->nodeStream = $nodestream; // assign the nodestream
		$this->root = new Node ();
		$this->Order (); // we automatically call our order method to create an AST from the nodestream
	}
	public function getRoot() {
		return $this->root; // and return the ast it created
	}
	public function Order() {
		$this->currentNode = array_shift ( $this->nodeStream );
		$this->currentNode = array_shift ( $this->nodeStream );
		// get the first element
		while ( $this->currentNode !== null ) { // Go as long as it has elements
		                                        // assign the current node from the nodestream beginning
			if ($this->currentNode->getType () == TPL_FOREACH)
				$this->foreachNode ( $this->currentNode );
			elseif ($this->currentNode->getType () == TPL_AUTOESCAPEON)
				$this->autoescapeNode ( $this->currentNode );
			$this->root->addChildren ( $this->currentNode ); // hozzĂĄadjuk a gyĂśkĂŠrhez
			$this->currentNode = array_shift ( $this->nodeStream );
		}
		// Comment this out if you wanna see your tree hierarchy
		// $this->show();
	}
	public function show() {
		print "ROOT<BR/>";
		foreach ( $this->root->getChildren () as $node ) {
			$depth = 1;
			for($x = 0; $x < $depth; $x ++) {
				print "|...";
			}
			print getConstantName ( "TPL_", $node->getType () ) . " -- Tokens:";
			$x = 0;
			foreach ( $node->token as $token ) {
				$x ++;
				print " (" . $x . "): " . getConstantName ( "TPL_", $token->getType () ) . " - " . htmlentities ( $token->cargo );
			}
			print "<br/>";
			if (! is_null ( $node->getChildren () ))
				$this->getChildrenTypes ( $node, $depth + 1 );
		}
		exit ();
	}
	public function getChildrenTypes($node, $depth) {
		if (is_array ( $node->getChildren () )) {
			foreach ( $node->getChildren () as $child ) {
				for($x = 0; $x < $depth; $x ++) {
					print "|...";
				}
				print getConstantName ( "TPL_", $child->getType () ) . " -- Tokens:";
				$x = 0;
				foreach ( $child->token as $token ) {
					$x ++;
					print " (" . $x . "): " . getConstantName ( "TPL_", $token->type ) . " - " . htmlentities ( $token->cargo );
				}
				print "<br/>";
				if (! empty ( $child->getChildren () ))
					$this->getChildrenTypes ( $child, $depth + 1 );
			}
		}
	}
	public function foreachNode($parentNode) {
		$currentNode = array_shift ( $this->nodeStream );
		while ( $currentNode !== null ) {
			if ($currentNode->getType () == TPL_FOREACH)
				$this->foreachNode ( $currentNode );
			if ($currentNode->getType () == TPL_AUTOESCAPEON)
				$this->autoescapeNode ( $currentNode ); // If we found a foreach node go 1 level deeper
			if ($currentNode->getType () == TPL_EACH)
				return;
			$parentNode->addChildren ( $currentNode ); // hozzĂĄadjuk a kĂślykeihez
			$currentNode = array_shift ( $this->nodeStream );
		}
		throw new TemplateParserException ( "We expected an each token!" ); // We didn't found an ending tag in the stream!
	}
	public function autoescapeNode($parentNode) {
		$currentNode = array_shift ( $this->nodeStream );
		while ( $currentNode !== null ) {
			if ($currentNode->getType () == TPL_FOREACH)
				$this->foreachNode ( $currentNode ); // If we found a foreach node go 1 level deeper
			if ($currentNode->getType () == TPL_AUTOESCAPEON)
				$this->autoescapeNode ( $currentNode ); // If we found a foreach node go 1 level deeper
			if ($currentNode->getType () == TPL_AUTOESCAPEOFF)
				return; // Found the ending tag
			$parentNode->children [] = $currentNode; // hozzĂĄadjuk a kĂślykeihez
			$currentNode = array_shift ( $this->nodeStream );
		}
		throw new TemplateParserException ( "We expected an autoescape off token!" ); // We didn't found an ending tag in the stream!
	}
}
/**
 *
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *        
 */
interface ICodeGen {
	public function preprocess(Node $parentNode);
	public function __construct($ast, $map);
	public function generate();
	public function setVar(string $variableName);
}
/**
 *
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *        
 */
abstract class CodeGen implements ICodeGen {
	/**
	 *
	 * @param Node $parentNode        	
	 */
	public function preprocess(Node $parentNode) {
	}
	public function __construct($ast, $map) {
	}
	public function generate() {
	}
	public function setVar(string $variableName) {
	}
}
/**
 *
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *        
 */
class HTMLCodeGen extends CodeGen {
	private $root;
	private $map;
	private $HTMLcode;
	public function __construct($ast, $map) {
		$this->root = $ast;
		$this->map = $map;
		$this->htmlcode = "";
	}
	public function show() {
		print "ROOT<BR/>";
		foreach ( $this->root->getChildren () as $node ) {
			$depth = 1;
			for($x = 0; $x < $depth; $x ++) {
				print "|...";
			}
			print getConstantName ( "TPL_", $node->getType () );
			if ($node->getType () == TPL_EXPRESSION) {
				print " -- Var: \"" . $node->getVar () . "\" -- Filters: ";
				foreach ( $node->getAllFilters () as $filter ) {
					print " '" . $filter . "'";
				}
				print " Filter variables: ";
				foreach ( $node->getAllFilterVars () as $filtervars ) {
					foreach ( $filtervars as $var ) {
						print " '" . $var . "'";
					}
				}
			}
			print " -- Content: \"" . htmlentities ( $node->getContent () );
			print "\"<br/>";
			if (! is_null ( $node->getChildren () ))
				$this->getChildrenTypes ( $node, $depth + 1 );
		}
		exit ();
	}
	public function getChildrenTypes($node, $depth) {
		if (is_array ( $node->getChildren () )) {
			foreach ( $node->getChildren () as $child ) {
				for($x = 0; $x < $depth; $x ++) {
					print "|...";
				}
				print getConstantName ( "TPL_", $child->getType () );
				if ($child->getType () == TPL_EXPRESSION) {
					print " -- Var: ";
					if (is_array ( $child->getVar () )) {
						foreach ( $child->getVar () as $var ) {
							print "\"" . $var . "\" ";
						}
					} else
						print $child->getVar ();
					print " -- Filters: ";
					foreach ( $child->getAllFilters () as $filter ) {
						print " '" . $filter . "'";
					}
					print " Filter variables: ";
					foreach ( $child->getAllFilterVars () as $filtervars ) {
						foreach ( $filtervars as $var ) {
							print " '" . $var . "'";
						}
					}
				}
				print " -- Content: \"" . htmlentities ( $child->getContent () );
				print "\"<br/>";
				if (! empty ( $child->getChildren () ))
					$this->getChildrenTypes ( $child, $depth + 1 );
			}
		}
	}
	public function generate() {
		$this->preprocess ( $this->root );
		$this->postprocess ( $this->root );
		$this->compileRoot ();
		return $this->HTMLcode;
	}
	private function compileRoot() {
		foreach ( $this->root->getChildren () as $children ) {
			$this->HTMLcode .= $children->getContent ();
		}
	}
	/**
	 *
	 * @param Node $parentNode        	
	 */
	private function postprocess(Node $parentNode) {
		// If it has a child then its a branch node
		if ($parentNode->getType () == TPL_FOREACH) // if it is a foreach then handle the process to the method
			$this->processForeach ( $parentNode );
		elseif ($parentNode->getType () == TPL_AUTOESCAPEON)
			$this->processAutoescape ( $parentNode );
		else { // if its not a control structure, then process the child recursively
			foreach ( $parentNode->getChildren () as $child ) { // go and dig deeper
				$this->postprocess ( $child );
			}
			// if it has no child nor a control structure then its a semi-top level expression
			// handle the variable in it
			if ($parentNode->getType () == TPL_EXPRESSION)
				$parentNode->setContent ( $this->postProcessFilters ( $parentNode ) );
		}
	}
	public function getVar($variableName) {
		if (strpos ( $variableName, "." )) { // Check if it is marked as an array key
			$pieces = explode ( ".", $variableName ); // explode it by the delimiter
			$variableName = $pieces [0]; // the first index is the variable name
			$index = $pieces [1]; // the second index is the index name
			if ($this->hasKey ( $variableName ))
				$array = $this->map [$variableName]; // get the key from the map
			
			foreach ( $array as $row ) { // gather the given index for later foreach
				if (is_array ( $row ))
					$returnable [] = $row [$index]; // if its a
				else
					$returnable [] = $row;
			}
			return $returnable;
		}
		if ($this->hasKey ( $variableName )) // if its not an array, simply assign the value from the map
			return $this->map [$variableName];
		// else throw new TemplateCompilerException("The variable <b>'".$variableName."'</b> hasn't been assigned to the template engine.");
	}
	private function hasKey($variableName) {
		return array_key_exists ( $variableName, $this->map );
	}
	
	/**
	 * Recursively iterate through the node and its children and preprocess its content
	 *
	 * @param Node $parentNode        	
	 *
	 */
	public function preprocess(Node $parentNode) {
		
		// If it has a child then its a branch node
		foreach ( $parentNode->getChildren () as $child ) { // go and dig deeper
			$this->preprocess ( $child );
		}
		
		// If no child node present then its a leafnode
		if ($parentNode->getType () == TPL_HTML)
			$this->preProcessHTML ( $parentNode );
		elseif ($parentNode->getType () == TPL_EXPRESSION)
			$this->preProcessExpression ( $parentNode );
		elseif ($parentNode->getType () == TPL_EOF)
			$this->preProcessEOF ( $parentNode );
		elseif ($parentNode->getType () == TPL_COMMENT)
			$this->preProcessComment ( $parentNode );
	}
	private function processAutoescape(Node $parentNode) {
	}
	private function processForeach(Node $parentNode) {
		// As it is a branch Node, then we get its children
		// Lets get the counter first
		$counter = 0;
		
		foreach ( $parentNode->getChildren () as $child ) {
			if ($child->getType () == TPL_EXPRESSION) { // If we found an expression, check if it is an array if it is, then count it
				if (is_array ( $child->getVar () ))
					
					// $counter = count($child->getVar());
					$counter = $child->countVar ();
				// print $counter;
			/**
			 * @TODO make a method to count the variable in Node
			 */
			}
		}
		// Ok, now we got a counter then foreach the whole stuff and put it into the parent content
		$parentNode->setContent ( "" );
		for($x = 0; $x < $counter; $x ++) {
			foreach ( $parentNode->getChildren () as $child ) {
				// EXPRESSION
				if ($child->getType () == TPL_EXPRESSION) { // If we found an expression, check if it is an array
					$parentNode->setContent ( $parentNode->getContent () . $this->postProcessFilters ( $child ) );
				}				// HTML
				elseif ($child->getType () == TPL_HTML) { // if its html simply append its content to the parent
					$parentNode->setContent ( $parentNode->getContent () . $child->getContent () );
				}
			}
			
			if ($counter == 0)
				throw new TemplateCompilerException ( "Expected an array to loop through." );
		}
	}
	private function postProcessFilters(Node $node) {
		$filters = $node->getAllFilters ();
		$var = $node->shiftFromVar ();
		foreach ( $filters as $filter ) {
			switch ($filter) {
				case "uc" :
				case "uppercase" :
					$var = $this->upperCaseFilter ( $var );
					break;
				case "ucfirst" :
					$var = $this->ucFirstFilter ( $var );
					break;
				case "td" :
					$var = $this->htmlTableCellFilter ( $var );
					break;
				case "p" :
				case "param" :
					$var = $this->htmlParagraphFilter ( $var );
					break;
				case "brtag" :
					$var = $this->htmlBrTagFilter ( $var );
					break;
				case "h1" :
				case "heading" :
					$var = $this->htmlHeadingFilter ( $var );
					break;
				case "h2" :
				case "subtitle" :
					$var = $this->htmlSubtitleFilter ( $var );
					break;
				case "h3" :
					$var = $this->htmlHeading3Filter ( $var );
					break;
				case "trstart" :
					$var = $this->htmlTableRowStartFilter ( $var );
					break;
				case "trend" :
					$var = $this->htmlTableRowEndFilter ( $var );
					break;
				case "a" :
					$var = $this->htmlHyperlinkFilter ( $var, $node->shiftFromFilterVar ( $filter ) );
					break;
				case "img" :
					$var = $this->htmlImageFilter ( $var );
					break;
				case "mailto" :
					$var = $this->htmlMailToFilter ( $var, $node->shiftFromFilterVar ( $filter ) );
					break;
				case "li" :
					$var = $this->htmlListFilter ( $var );
					break;
				case "code" :
					$var = $this->codeFilter($var);
					break;
				case "highlight": 
					$var = $this->highlightTemplateLangFilter($var);
					break;
				case "note" :
					$var = $this->htmlNoteFilter ( $var );
					break;
				case "warning" :
					$var = $this->htmlWarningFilter ( $var );
					break;
				default :
					throw new TemplateCompilerException ( "Unknown filter: '" . $filter . "'" );
			}
		}
		return $var;
	}
	
	private function highlightTemplateLangFilter($inputstring) {
		$outputstring = $inputstring;
		preg_match_all("/{#[a-zA-Z]*/", $inputstring, $matches);
		foreach ($matches[0] as $match) {
			
			$replacement = substr($match,0,2)."<bold>".substr($match,2,strlen($match))."</bold>";
			
			$outputstring = str_replace($match,$replacement , $inputstring);
		}
		return "<div id=\"code\">".$outputstring."</div>";
	}
	
	private function codeFilter($var) {
		return "<div id=\"code\"><pre>".$var."</pre></div>";
	}
	private function htmlWarningFilter($var) {
		return "<div id=\"warning\"><b>Warning:&nbsp;</b>" . $var . "</div>";
	}
	private function htmlNoteFilter($var) {
		return "<div id=\"note\"><b>Note:&nbsp;</b>" . $var . "</div>";
	}
	private function htmlListFilter($var) {
		return "<li>" . $var . "</li>";
	}
	private function htmlBrTagFilter($var) {
		return $var . "<br/>";
	}
	private function htmlMailToFilter($var, $filter) {
		return "<a href=\"mailto:" . $filter . "\">" . $var . "</a>";
	}
	private function htmlTableRowStartFilter($var) {
		return "<tr>" . $var;
	}
	private function htmlTableRowEndFilter($var) {
		return $var . "</tr>";
	}
	private function htmlImageFilter($var) {
		return "<img src=\"" . $var . "\"/>";
	}
	private function htmlHyperLinkFilter($var, $filtervar) {
		return "<a href=" . $filtervar . ">" . $var . "</a>";
	}
	private function htmlHeading3Filter($var) {
		return "<h3>" . $var . "</h3>";
	}
	private function htmlHeadingFilter($var) {
		return "<h1>" . $var . "</h1>";
	}
	private function htmlSubtitleFilter($var) {
		return "<h2>" . $var . "</h2>";
	}
	private function htmlParagraphFilter($var) {
		return "<p>" . $var . "</p>";
	}
	private function htmlTableCellFilter($var) {
		return "<td>" . $var . "</td>";
	}
	private function ucFirstFilter($var) {
		return ucfirst ( $var );
	}
	private function upperCaseFilter($var) {
		return strtoupper ( $var );
	}
	
	/**
	 * Preprocess the EOF Node
	 * sets it content empty.
	 */
	private function preProcessEOF(Node $parentNode) {
		$parentNode->setContent ( "" );
	}
	/**
	 * Processes comment nodes
	 */
	private function preProcessComment(Node $parentNode) {
		$parentNode->setContent ( "" ); // We wont display comments
	}
	/**
	 * Processes the HTML nodes
	 */
	private function preProcessHTML(Node $parentNode) {
		while ( $token = array_shift ( $parentNode->token ) ) {
			$parentNode->setContent ( $token->cargo ); // simply assign the html content
		}
	}
	/**
	 * Processes expression nodes
	 * 
	 * @throws TemplateCompilerException
	 */
	private function preProcessExpression(Node $parentNode) {
		// Clear the array token and fill the var and filters properties
		while ( $token = array_shift ( $parentNode->token ) ) {
			if ($token->getType () == TPL_IDENTIFIER) { // we got an identifier
				$parentNode->setVar ( $this->getVar ( $token->cargo ) ); // assign the html content
			} elseif ($token->getType () == TPL_FILTER) {
				$this->preProcessFilters ( $parentNode, $token );
			} else
				throw new TemplateCompilerException ( "Unknown token type <b>'" . getConstantName ( "TPL_", $token->getType () ) . "'</b> found!" );
		}
	}
	private function preProcessFilters(Node $parentNode, $token) {
		if (strpos ( $token->cargo, ":" ) > 0) { // we got an argument for the filter
			$pieces = explode ( ":", $token->cargo );
			$filter = $pieces [0];
			$filterVariable = $pieces [1];
			$parentNode->addFilter ( $filter ); // add the filter
			$parentNode->addFilterVar ( $filter, $this->getVar ( $filterVariable ) ); // we add to the filter variables array
		} else {
			$parentNode->addFilter ( $token->cargo ); // if it has no argument, simply add it
		}
	}
}
class Node {
	private $children = array (); // Filled with Nodes
	private $type;
	var $token;
	var $lineIndex;
	private $autoescape;
	private $foreachtimes;
	private $content;
	private $var;
	private $filters = array ();
	private $filterVariables = array ();
	public function __construct($token = null, $type = null, $lineIndex = null) {
		$this->type = $type;
		if ($token !== null) {
			$this->token [] = $token;
			$this->lineIndex = $token->lineIndex;
		} else {
			$this->lineIndex = $lineIndex;
		}
	}
	public function setVar($var) {
		$this->var = $var;
	}
	public function getVar() {
		return $this->var;
	}
	public function shiftFromVar() {
		if (is_array ( $this->var )) {
			return array_shift ( $this->var ); // if it is an array, shift one element
		} else
			return $this->var; // if not then return the complete string
	}
	public function countVar() {
		return count ( $this->var );
	}
	public function getAllFilters() {
		return $this->filters;
	}
	public function getContent() {
		return $this->content;
	}
	public function getChildren() {
		return $this->children;
	}
	public function addChildren($child) {
		$this->children [] = $child;
	}
	public function setContent($content) {
		$this->content = $content;
	}
	public function getType() {
		return $this->type;
	}
	public function addFilter($filter) {
		$this->filters [] = $filter;
	}
	public function shiftFromFilterVar($filter) {
		if (is_array ( $this->filterVariables [$filter] )) {
			return array_shift ( $this->filterVariables [$filter] );
		} else
			return $this->filterVariables [$filter];
	}
	public function addFilterVar($filter, $var) {
		$this->filterVariables [$filter] = $var;
	}
	public function getAllFilterVars() {
		return $this->filterVariables;
	}
	public function setType($type) {
		if (is_int ( $type ))
			$this->type = $type;
		else
			throw new TemplateCompilerException ( "Invalid node type!" );
	}
}

/**
 * The token class is what our lexer returns
 * it holds the text of the token and the line number
 * and column index of the starting character
 *
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *        
 */
class Token {
	public $cargo;
	public $lineIndex;
	public $colIndex;
	private $type;
	public function __construct($startingCharacter) {
		$this->cargo = $startingCharacter->cargo;
		$this->lineIndex = $startingCharacter->getLine ();
		$this->colIndex = $startingCharacter->getCol ();
		$this->type = NULL;
	}
	public function setType($type) {
		$this->type = $type;
	}
	public function getType() {
		return $this->type;
	}
	public function getLine() {
		return $this->lineIndex;
	}
	public function getCol() {
		return $this->colIndex;
	}
	/**
	 * Return a displayable string representation of the token
	 */
	public function show() {
		return $this->cargo;
	}
	public function abort($string) {
		throw new TemplateParserException ( $string . " at line " . $this->lineIndex . " column " . $this->colIndex );
	}
}
class Parser {
	var $token;
	var $rootNode;
	var $currentNode;
	public function __construct($filename) {
		$this->rootNode = new Node ( new Token ( new Character ( "ROOT", 0, 0, 0 ) ) );
		$this->filename = $filename;
	}
	/**
	 * Preprocess the file content (includes files)
	 * Then handle it to the lexer, gets the first token then calls the program()
	 */
	public function parse() {
		$preprocessor = new Preprocessor ( $this->filename );
		$content = $preprocessor->get ();
		$this->lexer = new Lexer ( $content );
		$this->getToken ();
		$this->program ();
		return $this->rootNode->getChildren (); // We return our rootnode's children to the AST class
	}
	/**
	 * The whole program
	 * Gets statements or if encounters an TPL_EOF token, then consumes it and end.
	 */
	public function program() {
		// The program consists of one or more statements then an TPL_EOF
		// program = statement {statement} TPL_EOF
		while ( ! $this->found ( TPL_EOF ) ) {
			if ($this->found ( TPL_STARTTAG ))
				$this->code (); // We found a starttag token so go as a code
			elseif ($this->found ( TPL_HTML ))
				$this->html (); // We found html token so its html
			else
				$this->error ( "Unknown error" );
		}
		$this->consume ( TPL_EOF );
	}
	/**
	 * We found a TPL_HTML token
	 */
	private function html() {
		$this->consume ( TPL_HTML );
	}
	private function appendNode($type = null) {
		if ($type !== null)
			$this->currentNode->setType ( $type ); // we set the new type of the current node
		$this->currentNode->token [] = $this->token; // new Node($this->token, $type); // and append the token as a child node
	}
	private function newNode($type = null) {
		$this->rootNode->addChildren ( $this->currentNode ); // drop the previous node to our array
		$this->currentNode = new Node ( $this->token, $type ); // and create a new node
	}
	private function newHTMLNode() {
		$this->rootNode->addChildren ( $this->currentNode ); // drop the previous node to our array
		$this->currentNode = new Node ( $this->token, TPL_HTML ); // and create a new TPL_HTML node with the html token in it
	}
	private function newExpressionNode() {
		$this->rootNode->addChildren ( $this->currentNode ); // drop the previous node to our array
		$this->currentNode = new Node ( null, TPL_EXPRESSION, $this->token->lineIndex ); // and create a new node without the current token (as it is a starttag)
	}
	private function keywords() {
		foreach ( $this->lexer->keyints as $keyword ) {
			if ($this->found ( $keyword )) {
				$this->expect ( TPL_ENDTAG );
				$this->consume ( TPL_ENDTAG );
			}
		}
	}
	/**
	 *
	 * @param Node $node        	
	 */
	public function code() {
		if ($this->foundOneOf ( $this->lexer->keyints ))
			$this->keywords ();
		elseif ($this->found ( TPL_COMMENT )) { // We found a comment so we expect an endtag
			$this->expect ( TPL_ENDTAG );
		} elseif ($this->found ( TPL_IDENTIFIER )) {
			// We found an identifier so we expect endtag or symbol
			while ( ! $this->found ( TPL_ENDTAG ) ) {
				if ($this->found ( TPL_SYMBOL )) {
					$this->expect ( TPL_FILTER );
				}
			}
		}
	}
	/**
	 * Gets a new token from the lexer
	 */
	public function getToken() {
		$this->token = $this->lexer->get ();
	}
	/**
	 *
	 * @param int $needTokenType        	
	 */
	public function consume($needTokenType) {
		// We check if the token is the needed type
		// and if it is then we get another token
		if ($this->token->getType () == $needTokenType) {
			
			$this->getToken ();
		}
	}
	/**
	 * Check whether the current token type matches one of the array it given
	 *
	 * @param array $tokentypes        	
	 * @return boolean
	 */
	private function foundOneOf($tokentypes) {
		foreach ( $tokentypes as $tokentype ) {
			if ($this->token->getType () == $tokentype)
				return true;
		}
		return false;
	}
	/**
	 * Throws an exception with the given message
	 *
	 * @param string $message        	
	 * @throws TemplateParserException
	 */
	public function error($message) {
		throw new TemplateParserException ( $message . " while processing token <b>'" . htmlspecialchars ( $this->token->cargo ) . "'</b> with type of " . getConstantName ( "TPL_", $this->token->getType () ) . " at " . $this->token->getLine () . ":" . $this->token->getCol () );
	}
	/**
	 * Checks whether we found the given type of token AND GETS ANOTHER,
	 * if not then drops an error
	 *
	 * @param string $token        	
	 */
	public function expect($token) {
		if ($this->found ( $token )) {
			return;
		} else {
			$this->error ( "Parse error: Expecting <b>" . $token . "</b> but I found something else" );
		}
	}
	/**
	 * Checks whether we found a given type of token and gets ANOTHER
	 *
	 * @param string $token        	
	 * @return boolean
	 */
	public function found($token) {
		if ($this->token->getType () == $token) {
			// print "Found! ".$token." token with value: ".htmlspecialchars($this->token->cargo);
			if ($token == TPL_HTML) {
				$this->newHTMLNode ( TPL_HTML );
				// print " ...so created a new TPL_HTML Node ";
			} elseif ($token == TPL_STARTTAG) {
				$this->newExpressionNode ( TPL_EXPRESSION );
				// print " ...so created a new TPL_EXPRESSION Node ";
			} elseif ($token == TPL_COMMENT) {
				$this->appendNode ( TPL_COMMENT );
				// print " ...so appended an existing Node as TPL_COMMENT Node ";
			} elseif ($token == TPL_FOREACH) {
				$this->appendNode ( TPL_FOREACH );
				// print " ...so appended an existing Node as FOREACH Node ";
			} elseif ($token == TPL_EACH) {
				$this->appendNode ( TPL_EACH );
				// print " ...so appended an existing Node as TPL_EACH Node ";
			} elseif ($token == TPL_AUTOESCAPEON) {
				$this->appendNode ( TPL_AUTOESCAPEON );
				// print " ...so appended an existing Node as AUTOESCAPE ON Node ";
			} elseif ($token == TPL_AUTOESCAPEOFF) {
				$this->appendNode ( TPL_AUTOESCAPEOFF );
				// print " ...so appended an existing Node as AUTOESCAPE OFF Node ";
			} elseif ($token == TPL_EOF) {
				$this->newNode ( TPL_EOF );
				// print " ...so created a new TPL_EOF Node ";
			} elseif ($token == TPL_ENDTAG) {
				// $this->appendNode(); do nothing, we dont need endtags
			} elseif ($token == TPL_SYMBOL) {
				// $this->appendNode(); do nothing, we dont need endtags
			} else {
				$this->appendNode ();
				// print " ...so appended an existing Node ";
			}
			// print "<br/>";
			$this->getToken ();
			return true;
		}
		return false;
	}
}
class Preprocessor {
	private $filename; // The filename we gonna preprocess
	public function __construct($filename) {
		$this->filename = $filename;
	}
	
	/**
	 * Get the content from the file, then recursively find include syntax and 
	 * replace the included content with that syntax
	 * 
	 */
	public function get() { 
		// The file exits
		if ($content = @file_get_contents ( $this->filename )) {
			// Look for include syntax
			if (preg_match_all ( "/\{\#include\s*[:\/A-Z.a-z]*\}/", $content, $matches )) {
				// Foreach the resultset 
				foreach ( $matches [0] as $match ) { 
					$pieces = preg_split ( "/\s/", trim ( $match, "{}" ) );
					// Go and get the file contents
					$includedcontent = $this->included ( $pieces [1] );
					// Replace it with the syntax
					$content = str_replace ( $match, $includedcontent, $content );
				}
			}
			//  Return the preprocessed content
			return $content;
		} 
		else // If the file doesn't exists 
			throw new TemplateParserException("There is no such template file : '".$this->filename. "'");
		
	}
	/**
	 * 
	 * @param string $filename
	 * @throws TemplateFileMissingException
	 * @return string
	 */
	public function included($filename) {
		if (@$includedContent = file_get_contents ( $filename )) return $includedContent;
		elseif (@$includedContent = file_get_contents ( $filename.TEMPLATE_EXTENSION )) return $includedContent;// If they forgot to add the extension
		elseif (@$includedContent = file_get_contents (TEMPLATE_DIR.$filename )) return $includedContent;  // If they forgot to add the path
		elseif (@$includedContent = file_get_contents ( TEMPLATE_DIR.$filename. TEMPLATE_EXTENSION )) return $includedContent; // if the forgot both extension and path
		else throw new TemplateFileMissingException ( 'There is no such template file <b>'.$filename . "</b> to be included." );
	  
	}
}
class Lexer extends Character {
	private $character;
	private $content;
	public $keywords = array (
			"include",
			"for",
			"each",
			"autoescapeon",
			"autoescapeoff",
			"as",
			"on",
			"off" 
	);
	public $keyints = array (
			8,
			9,
			10,
			11 
	);
	private $oneCharacterSymbols = array (
			"|",
			"." 
	);
	private $filterVarSymbol = ":";
	private $twoCharacterSymbols = array (
			"{*",
			"{%",
			"{#" 
	);
	private $scanner;
	private $identifierStartChars = "abcdefghijklmnopqrstzyvwxABCDEFGHIJKLMNOPQRSTZYVWX";
	private $variableChars = "abcdefghijklmnopqrstuzyvwxABCDEFGHIJKLMNOPQRSTUZYVWX1234567890_.";
	private $filterChars = "abcdefghijklmnopqrstuzyvwxABCDEFGHIJKLMNOPQRSTUZYVWX1234567890:.";
	private $whitespaceChars = " \t\n";
	private $variableStartChars = "%";
	private $numberstartChars = "1234567890";
	private $numberChars = "1234567890.";
	private $c1, $c2;
	private $html;
	public function __construct($content) {
		$this->content = $content;
		$this->scanner = new TemplateScanner ( $content );
		$this->html = true;
		$this->character;
		$this->c1 = NULL;
		$this->c2 = null;
		$this->getChar ();
	}
	public function getChar() {
		if (is_null ( $this->c2 )) { // If c2 is null then we get another
			$this->character = $this->scanner->get ();
			$this->eof = $this->character->eof;
			$this->c1 = $this->character->cargo; // and place it in c1
			$this->character = $this->scanner->get ();
			$this->c2 = $this->c1 . $this->character->cargo; // And append it to the c1 so we got two chars
		} else { // if c2 is not null, then we need to rotate
			$this->c1 = $this->c2 [1]; // C1 got the c2 second index value
			$this->character = $this->scanner->get (); // Get a new character
			$this->eof = $this->character->eof;
			
			$this->c2 = $this->c1 . $this->character->cargo; // Append its value to the
		}
	}
	public function get() {
		// Find starting and ending delimiters
		while ( strpos ( $this->whitespaceChars, $this->c1 ) > - 1 ) {
			$this->getChar (); // simply skip the whitespaces
		}
		if ($this->c1 == "}") {
			$token = new Token ( $this->character );
			$token->setType ( TPL_ENDTAG );
			$token->cargo = $this->c1;
			$this->html = true;
			$this->getChar ();
			return $token;
		}
		if (in_array ( $this->c2, $this->twoCharacterSymbols )) {
			$token = new Token ( $this->character );
			$token->setType ( TPL_STARTTAG );
			$token->cargo = $this->c1;
			$this->html = false;
			$this->getChar ();
			return $token;
		}
		
		// TPL_HTML stuff
		while ( $this->html == true ) {
			// Whitespaces
			while ( strpos ( $this->whitespaceChars, $this->c1 ) > - 1 ) {
				$this->getChar (); // simply skip the whitespaces
			}
			// end of file
			if ($this->eof == true) { // IF we enocounter an eof signal we return an eof token
				$token = new Token ( $this->character );
				$token->setType ( TPL_EOF );
				RETURN $token;
			}
			$token = new Token ( $this->character );
			$token->setType ( TPL_HTML );
			$token->cargo = $this->c1;
			while ( $this->html == true ) {
				$this->getChar (); // Get a new char
				
				if (in_array ( $this->c2, $this->twoCharacterSymbols )) {
					$this->html = false;
					return $token;
				}
				// if its not terminal, then add it
				$token->cargo .= $this->c1;
				if ($this->eof == true)
					return $token;
			}
		}
		
		while ( $this->html == false ) {
			// end of file
			if ($this->eof == true) { // IF we enocounter an eof signal we return an eof token
				$token = new Token ( $this->character );
				$token->setType ( TPL_EOF );
				RETURN $token;
			}
			$token = new Token ( $this->character ); // Generate a token
			while ( in_array ( $this->c1, $this->oneCharacterSymbols ) ) {
				$token->setType ( TPL_SYMBOL );
				$token->cargo = $this->c1;
				$this->getChar (); // Get a new character ALWAYS BEFORE RETURNING
				return $token;
			}
			
			while ( $this->c1 == "*" ) { // It is a PIE template comment
				
				$token->setType ( TPL_COMMENT );
				$this->getChar ();
				$token->cargo = $this->c1;
				while ( $this->c2 != "*}" ) { // Keep building the token
					$this->getChar ();
					if ($this->c2 == "*}")
						break;
					$token->cargo .= $this->c1;
					if ($this->eof == true) { // if we find the eof signal, we handle back the token
						throw new TemplateParserException ( "Found end of file before the end of comment section." );
						return $token;
					}
				}
				$this->getChar ();
				// $token->cargo .= $this->c1;
				return $token;
			}
			while ( $this->c1 == "%" ) {
				$token->setType ( TPL_IDENTIFIER );
				$this->getChar (); // we skip the '%'
				$token->cargo = $this->c1;
				$this->getChar ();
				while ( strpos ( $this->variableChars, $this->c1 ) !== false ) {
					
					$token->cargo .= $this->c1;
					$this->getChar ();
				}
				return $token;
			}
			
			while ( $this->c1 == "#" ) {
				$this->getChar (); // we skip the '#'
				$token->cargo = $this->c1;
				$this->getChar ();
				while ( strpos ( $this->variableChars, $this->c1 ) !== false ) {
					$token->cargo .= $this->c1;
					$this->getChar ();
				}
				if (! in_array ( $token->cargo, $this->keywords ))
					throw new TemplateParserException ( "Unknown command found " . $token->cargo );
				else {
					switch ($token->cargo) {
						case "include" :
							$token->setType ( TPL_INCLUDEFILE);
							break;
						case "for" :
							$token->setType ( TPL_FOREACH );
							break;
						case "each" :
							$token->setType ( TPL_EACH );
							break;
						case "autoescapeon" :
							$token->setType ( TPL_AUTOESCAPEON );
							break;
						case "autoescapeoff" :
							$token->setType ( TPL_AUTOESCAPEOFF );
							break;
					}
				}
				return $token;
			}
			while ( strpos ( $this->filterChars, $this->c1 ) > - 1 ) {
				$token->setType ( TPL_FILTER );
				$token->cargo = "";
				while ( strpos ( $this->filterChars, $this->c1 ) > - 1 ) {
					$token->cargo .= $this->c1;
					$this->getChar ();
				}
				return $token;
			}
			exit ( "We found a character we didn't recognize: <b>'" . $this->c1 . "'</b> at " . $this->character->getLine () . ":" . $this->character->getCol () );
		}
	}
}

/**
 * Holds information about a read character
 * 
 * @author Papp Krisztián
 * 
 */
class Character {
	public $cargo;
	private $lineIndex;
	private $columnIndex;
	private $sourceIndex;
	public $eof;
	public function __construct($cargo, $lineIndex, $columnIndex, $sourceIndex, $eof = FALSE) {
		$this->cargo = $cargo;
		$this->lineIndex = $lineIndex;
		$this->columnIndex = $columnIndex;
		$this->sourceIndex = $sourceIndex;
		$this->eof = $eof;
	}
	public function getCargo() {
		return ($this->cargo == TPL_EOF) ? "ENDMARK" : $this->cargo;
	}
	public function getLine() {
		return $this->lineIndex;
	}
	public function getCol() {
		return $this->columnIndex;
	}
	public function toString() {
		$cargo = $this->cargo;
		if ($cargo == "\n")
			$cargo = "       NEWLINE";
		elseif ($cargo == "\t")
			$cargo = "       TAB";
		elseif ($cargo == TPL_EOF)
			$cargo = TPL_EOF;
		elseif ($cargo == " ")
			$cargo = "     SPACE";
		
		return ($this->lineIndex . "  " . $this->columnIndex . "  " . $cargo);
	}
}
class TemplateScanner {
	private $sourceText; // The string contains the whole text
	private $lastIndex; // The last index of the file
	private $lineIndex; // The current line index
	private $sourceIndex; // The current offset
	private $colIndex; // The current column index
	public function __construct($sourceText) {
		$this->sourceText = $sourceText;
		$this->lastIndex = strlen ( $this->sourceText ) - 1;
		
		$this->lineIndex = 1;
		$this->sourceIndex = - 1;
		$this->colIndex = - 1;
	}
	/**
	 * Handles back character objects from the source text
	 * @return Character
	 */
	public function get() {
		// Getting a character from the string
		$this->sourceIndex ++;
		
		if ($this->sourceIndex > 0) { // Maintain line count
			
			if ($this->sourceText [$this->sourceIndex - 1] == "\n") { // The last element was a new line
				$this->lineIndex ++; // Line increment
				$this->colIndex = - 1; // Column positioned at -1
			}
		}
		$this->colIndex ++;
		// We reached the end of file
		
		if ($this->sourceIndex > $this->lastIndex) {
			$char = new Character ( TPL_EOF, $this->lineIndex, $this->colIndex, $this->sourceIndex, true );
		} else {
			$cargo = $this->sourceText [$this->sourceIndex];
			$char = new Character ( $cargo, $this->lineIndex, $this->colIndex, $this->sourceIndex );
		}
		
		// Return a character object
		return $char;
	}
}

/**
 * The real template class It calls the parser get the nodes, handles them to the AST class
 * then get the generated AST, calls the HTMLCodeGen and puts it output to a compiled HTML file. 
 * @author Papp Krisztián <krisztianpapp1986@gmail.com>
 *
 */
class Templating {
	private $template_file; // the template file name w/o extension and/or path
	private $output_file; // the output file name w/o extension and/or path
	private $map = array (); // the map where the variables are assigned to
	private $nodes = array(); // the nodestream
	private $ast; // the abstract syntax tree generated from the nodestream
	private $output; // the generated HTML 
	
	/**
	 * Ctor
	 * @param string $template_file
	 * @param string $output_file
	 */
	public function __construct($template_file, $output_file = null) {
		if ($output_file == NULL ) // if no output file given, the filename gonna be the same as the template
			$this->output_file = $template_file;
		else $this->output_file = $output_file;
		$this->template_file = TEMPLATE_DIR . $template_file . TEMPLATE_EXTENSION;
	}
	
	/**
	 * Get the nodes from the files by calling our parser, then generate an AST from the Nodestream
	 * then calls our compiler to generate the HTML output and puts in a file.
	 * Last, simply prints the generated output
	 * 
	 * @param $ttl int The cache lifetime 
	 */
	public function Display($ttl = 0) {
		if (!$this->checkIfCached($ttl)) { // check whether our file is cached or not
			$starttime = microtime ( true );		
			$this->getNodes(); // get the nodes from the parser
			$this->getAST();
			$this->generateOutput();
			$this->generateFile();
			$compiletime = round ( microtime ( true ) - $starttime, 3 );
			$this->addSignature($compiletime);
		}
		else $this->getFromCache();
		print $this->output;
	}
	/**
	 * Checks whether a file is already cached or not
	 * If the ttl param equals to zero then it ALWAYS return false.
	 * @param int $ttl
	 * @return boolean
	 */
	private function checkIfCached($ttl) {
		if ($ttl == 0) return false;
		if (file_exists(TEMPLATE_DIR.$this->output_file. HTML_EXTENSION)) // Output file already present
			if ((filemtime(TEMPLATE_DIR.$this->output_file. HTML_EXTENSION) + $ttl) > time()) // it is recent
				return true;
		return false;
	}
	/**
	 * Gets the cached content from the file
	 */
	private function getFromCache() {
		$this->output = file_get_contents(TEMPLATE_DIR.$this->output_file. HTML_EXTENSION).
		"<!-- Created with PIE Template Engine. Cached at: ".date("Y.m.d - H:m:s ",filemtime(TEMPLATE_DIR.$this->output_file. HTML_EXTENSION))." -->";
	}
	/**
	 * Adds a HTML comment template signature to the end of the HTML output
	 * @param unknown $compiletime
	 */
	private function addSignature($compiletime) {
		$templatesignature = "<!-- Created with PIE Template Engine. Compiled in: " . $compiletime . " sec -->";
		$this->output .= $templatesignature;
	}
	/**
	 * Generates an AST from the nodestream
	 */
	private function getAST() {
		$this->ast = new AST ( $this->nodes ); // we got the AST from the AST class
	}
	/**
	 * Generates the compiled HTML file and puts the HTML output in it
	 */
	private function generateFile() {
		$compiledfile = TEMPLATE_DIR . $this->output_file . HTML_EXTENSION; // the file to put HTML output in to later caching
		file_put_contents ( $compiledfile, $this->output );
	}
	
	/**
	 * Generates the HTML output
	 */
	private function generateOutput() {
		$codegen = new HTMLCodeGen ( $this->ast->getRoot (), $this->map );
		$this->output = $codegen->generate (); // generate the output		
	}
	/**
	 * Instantiate the parser class and get the nodestream from it
	 */
	private function getNodes(){
		$parser = new Parser ( $this->template_file ); // Instantiate our parser class and handle it the template file
		$this->nodes = $parser->parse (); // Got the nodestream, handle it to the AST
	}
	/**
	 * Assign a key-value to the map
	 * @param string $key
	 * @param mixed $value
	 */
	public function assign($key, $value) {
		$this->map [$key] = $value;
	}
}
class TemplateException extends Exception {
}
class TemplateParserException extends TemplateException {
	var $message = "Error parsing the template file.";
}
class TemplateFileMissingException extends TemplateException {
	var $message = "The template file is missing.";
}
class TemplateCompilerException extends TemplateException {
}
