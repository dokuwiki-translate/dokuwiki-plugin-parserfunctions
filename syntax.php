<?php
/**
 * DokuWiki Plugin parserfunctions (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Daniel "Nerun" Rodrigues <danieldiasr@gmail.com>
 * @created: Sat, 09 Dec 2023 14:59 -0300
 * 
 * This is my first plugin, and I don't even know PHP well, that's why it's full
 * of comments, but I'll leave it that way so I can consult it in the future.
 * 
 */
use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Utf8\PhpString;

class syntax_plugin_parserfunctions extends SyntaxPlugin
{
    /** @inheritDoc */
    public function getType()
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#syntax_types
         * substition  — modes where the token is simply replaced – they can not
         * contain any other modes
         */
        return 'substition';
    }

    /** @inheritDoc */
    public function getPType()
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins
         * normal — Default value, will be used if the method is not overridden.
         *          The plugin output will be inside a paragraph (or another
         *          block element), no paragraphs will be inside.
         */
        return 'normal';
    }

    /** @inheritDoc */
    public function getSort()
    {
        /* READ: https://www.dokuwiki.org/devel:parser:getsort_list
         * Don't understand exactly what it does, need more study.
         *
         * Must go after Templater (302) and WST (319) plugin, to be able to
         * render @parameter@ and {{{parameter}}}.
         */
        return 320;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#patterns
         * This pattern accepts any alphabetical function name but not nested
         * functions.
         *
         * ChatGPT helped me to fix this pattern! (18 jan 2025)
         */
        $this->Lexer->addSpecialPattern('\{\{#[[:alpha:]]+:(?:(?!\{\{#|#\}\}).)*#\}\}', $mode, 'plugin_parserfunctions');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#handle_method
         * This is the part of your plugin which should do all the work. Before
         * DokuWiki renders the wiki page it creates a list of instructions for
         * the renderer. The plugin's handle() method generates the render
         * instructions for the plugin's own syntax mode. At some later time,
         * these will be interpreted by the plugin's render() method.
         *
         * Parameters:
         *
         *   $match   (string)  — The text matched by the patterns
         *    
         *   $state   (int)     — The lexer state for the match, representing
         *                        the type of pattern which triggered this call
         *                        to handle(): DOKU_LEXER_SPECIAL — a pattern
         *                        set by addSpecialPattern().
         *   
         *   $pos     (int)     — The character position of the matched text.
         *   
         *   $handler           — Object Reference to the Doku_Handler object.
         */
        
        // Exit if no text matched by the patterns.
        if (empty($match)) {
            return false;
        }
        
        /* Function name: "if", "ifeq", "ifexpr" etc.
         * strtolower converts only ASCII; PhpString::strtolower supports UTF-8,
         * added by "use dokuwiki\Utf8\PhpString;" at line 15. The function
         * names will probably only use ASCII characters, but it's a precaution.
         * The 's' at the end of '/pattern/s' adds support to multiline strings.
         */
        $func_name = preg_replace('/\{\{#([[:alpha:](&#)]+):.*#\}\}/s', '\1', $match);
        $func_name = PhpString::strtolower($func_name);

        // Delete delimiters "{{#functionname:" and "#}}".
        // The 's' at the end of '/pattern/s' adds support to multiline strings.
        $parts = preg_replace('/\{\{#[[:alpha:]]+:(.*)#\}\}/s', '\1', $match);
        
        // Create list with all parameters splited by "|" pipe
        // 1st) Replace pipe '|' by a temporary marker
        $parts = str_replace('%%|%%', '%%TEMP_MARKER%%', $parts);
        // 2nd) Create list of parameters splited by pipe "|"
        $params = explode('|', $parts);
        //3rd) Restoring temporary marker to `%%|%%`
        $params = str_replace('%%TEMP_MARKER%%', '%%|%%', $params);
        /* This snippet above was necessary to allow the escape sequence of the
         * pipe character "|" using the standard DokuWiki formatting syntax
         * which is to wrap it in "%%".
         */

        // Stripping whitespace from the beginning and end of strings
        $params = array_map('trim', $params);
        
        // ==================== FINALLY: do the work! ====================
        switch($func_name){
            // To add a new function, first add a "case" below, make it call a
            // function, then write the function.
            case 'if':
                $func_result = $this->_IF($params, $func_name);
                break;
            case 'ifeq':
                $func_result = $this->_IFEQ($params, $func_name);
                break;
            case 'ifexist':
                $func_result = $this->_IFEXIST($params, $func_name);
                break;
            case 'switch':
                $func_result = $this->_SWITCH($params, $func_name);
                break;
            default:
                $func_result = '<wrap important>**' . $this->getLang('error') .
                               ' "' . $func_name . '": ' .
                               $this->getLang('no_such_function') . '**</wrap>';
                break;
        }
        
        // The instructions provided to the render() method:
        return $func_result;
    }
    
    /** @inheritDoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#render_method
         * The part of the plugin that provides the output for the final web
         * page.
         *
         * Parameters:
         *
         *   $mode     — Name for the format mode of the final output produced
         *               by the renderer.
         *
         *   $renderer — Give access to the object Doku_Renderer, which contains
         *               useful functions and values.
         *
         *   $data     — An array containing the instructions previously
         *               prepared and returned by the plugin's own handle()
         *               method. The render() must interpret the instruction and
         *               generate the appropriate output.
         */
        
        if ($mode !== 'xhtml') {
            return false;
        }
        
        if (!$data) {
            return false;
        }
        
        // escape sequences
        $data = $this->_escape($data);

        // Do not use <div></div> because we need inline substitution!
		// Both substr() and preg_replace() do the same thing: remove the
		// first '<p>' and the last '</p>'
		$data = $renderer->render_text($data, 'xhtml');
		//$data = substr( $data, 4, -5 );
		$data = preg_replace( '/<p>((.|\n)*?)<\/p>/', '\1', $data );
		$renderer->doc .= $data;

        return true;
    }
    
    /**
     * ========== #IF
     * {{#if: 1st parameter | 2nd parameter | 3rd parameter #}}
     * {{#if: test string | value if test string is not empty | value if test
     * string is empty (or only white space) #}}
     */
    function _IF($params, $func_name)
    {
        if ( count($params) < 1 ) {
            $result = '<wrap alert>**' . $this->getLang('error') . ' "' .
                      $func_name . '": ' . $this->getLang('not_enough_params') .
                      '**</wrap>';
        } else {
            if ( !empty($params[0]) ) {
                $result = $params[1] ?? null;
            } else {
                $result = $params[2] ?? null;
            }
        }
        
        return $result;
    }
    
    /**
     * ========== #IFEQ
     * {{#ifeq: 1st parameter | 2nd parameter | 3rd parameter | 4th parameter #}}
     * {{#ifeq: string 1 | string 2 | value if identical | value if different #}}
     */
    function _IFEQ($params, $func_name)
    {
        if ( count($params) < 2 ) {
            $result = '<wrap alert>**' . $this->getLang('error') . ' "' .
                      $func_name . '": ' . $this->getLang('not_enough_params') .
                      '**</wrap>';
        } else {
            if ( $params[0] == $params[1] ) {
                $result = $params[2] ?? null;
            } else {
                $result = $params[3] ?? null;
            }
        }
        
        return $result;
    }
    
    /**
     * ========== #IFEXIST
     * {{#ifexist: 1st parameter | 2nd parameter | 3rd parameter #}}
     * {{#ifexist: page title or media, or file/folder path | value if exists
     *  | value if doesn't exist #}}
     */
    function _IFEXIST($params, $func_name)
    {
        if ( count($params) < 1 ) {
            $result = '<wrap alert>**' . $this->getLang('error') . ' "' .
                      $func_name . '": ' . $this->getLang('not_enough_params') .
                      '**</wrap>';
        } else {
            if ( str_contains($params[0], '/') ){
                if ( str_starts_with($params[0], '/') ){
                    $isMedia = substr($params[0], 1);
                } else {
                    $isMedia = $params[0];
                }
            } else {
                $isMedia = 'data/media/' . str_replace(':', '/', $params[0]);
            }
            
            if ( page_exists($params[0]) or file_exists($isMedia) ){
                $result = $params[1] ?? null;
            } else {
                $result = $params[2] ?? null;
            }
        }
        
        return $result;
    }
    
    /**
     * ========== #SWITCH
     * {{#switch: comparison string
     * | case = result
     * | case = result
     * | ...
     * | case = result
     * | default result
     * #}}
     */
    function _SWITCH($params, $func_name)
    {
        if ( count($params) < 2 ) {
            $result = '<wrap alert>**' . $this->getLang('error') . ' "' .
                      $func_name . '": ' . $this->getLang('not_enough_params') .
                      '**</wrap>';
        } else {
            /**
             * Then:
             * 
             * "$params":
             *      (
             *          [0] => test string
             *          [1] => case 1 = value 1
             *          [2] => case 2 = value 2
             *          [3] => case 3 = value 3
             *          [4] => default value
             *      )
             */

            $cases_kv = [];
            $test_and_default_string = [];
            
            foreach ( $params as $value ){
                // 1st) Replace escaped equal sign '%%|%%' by a temporary marker
                $value = str_replace('%%=%%', '%%TEMP_MARKER%%', $value);
                // 2nd) Create list of values splited by equal sign "="
                $value = explode('=', $value);
                //3rd) Restoring temporary marker to `%%=%%`
                $value = str_replace('%%TEMP_MARKER%%', '%%=%%', $value);
                /* This snippet above was necessary to allow the escape sequence of
                 * the equal sign "=" using the standard DokuWiki formatting syntax
                 * which is to wrap it in "%%".
                 * (same as lines 105-115 above)
                 */

            	if ( isset($value[1]) ) {
            		$cases_kv[trim($value[0])] = trim($value[1]);
            	} else {
		            if ( count($cases_kv) == 0 or count($cases_kv) == count($params) ) {
			            $test_and_default_string[] = trim($value[0]);
		            } else {
			            $cases_kv[trim($value[0])] = '%%FALL_THROUGH_TEMP_MARKER%%';
		            }
	            }
            }
            
            $count = 0;

            foreach ( $cases_kv as $key=>$value ){
                $count++;
	            if ( $value == '%%FALL_THROUGH_TEMP_MARKER%%' ){
		            $subDict = array_slice($cases_kv, $count);
		            foreach ( $subDict as $chave=>$valor ){
			            if ( $valor != '%%FALL_THROUGH_TEMP_MARKER%%' ){
				            $cases_kv[$key] = $valor;
				            break;
			            }
		            }
	            }
            }

            /**
             * And now:
             * 
             * "$cases_kv":
             *      (
             *          [case 1] => value 1
             *          [case 2] => value 2
             *          [case 3] => value 3
             *      )
             * 
             * "$test_and_default_string":
             *      (
             *          [0] => test string
             *          [1] => default value
             *      )
             */

            if ( array_key_exists($test_and_default_string[0], $cases_kv) ) {
            	$result = $cases_kv[$test_and_default_string[0]];
            } else {
                /* Default value:
                 * Explicit declaration (#default = default_value) takes precedence
                 * over implicit one (just 'default_value').
                 */
            	$result = $cases_kv['#default'] ?? $test_and_default_string[1] ?? '';
            }
        }
        
        return $result;
    }
    
    /**
     * Escape sequence handling
     */
    function _escape($data){
        /**
         * To add more escapes, please refer to:
         * https://www.freeformatter.com/html-entities.html
         * 
         * Before 2025-01-18, escape sequences had to use "&&num;NUMBER;"
         * instead of "&#;NUMBER;", because "#" was not escaped. But now "#" can
         * be typed directly, and does not need to be escaped. So use the normal
         * spelling for HTML entity codes, i.e., "&#61;" instead of "&&num;61;"
         * when adding NEW escapes.
         *
         * Additionally, after 2025-01-18, '=', '|', '{' and '}' signs can be
         * escaped only by wrapping them in '%%', following the standard
         * DokuWiki syntax. So, the escapes below are DEPRECATED, but kept for
         * backwards compatibility.
         * 
         */
        $escapes = array(
            // DEPRECATED, but kept for backwards compatibility:
            "&&num;61;"  => "=",
            "&&num;123;" => "%%{%%",
            "&&num;124;" => "|",
            "&&num;125;" => "%%}%%",
            "&num;"      => "#"  // Always leave this as the last element!
        );
        
        foreach ( $escapes as $key => $value ) {
            $data = preg_replace("/$key/s", $value, $data);
        }
        
        return $data;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
