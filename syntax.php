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
         * normal — (default value, will be used if the method is not overridden)
         *          The plugin output will be inside a paragraph (or another block
         *           element), no paragraphs will be inside.
         */
        return 'normal';
    }

    /** @inheritDoc */
    public function getSort()
    {
        /* READ: https://www.dokuwiki.org/devel:parser:getsort_list
         * Don't understand exactly what it does, need more study.
         *
         * Should go after Templater and WST plugin, to be able to render @1@ and {{{1}}}.
         */
        return 320;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#patterns
         * Regex accepts any alphabetical function name
         * but not nested functions
         */
        $this->Lexer->addSpecialPattern('\{\{#[[:alpha:]]+:[^(\{\{#)(#\}\})]+#\}\}', $mode, 'plugin_parserfunctions');
//        $this->Lexer->addEntryPattern('<FIXME>', $mode, 'plugin_parserfunctions');
    }

//    /** @inheritDoc */
//    public function postConnect()
//    {
//        $this->Lexer->addExitPattern('</FIXME>', 'plugin_parserfunctions');
//    }

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
         *   $state   (int)     — The lexer state for the match, representing the type
         *                        of pattern which triggered this call to handle():
         *                        DOKU_LEXER_SPECIAL — a pattern set by addSpecialPattern()
         *   
         *   $pos     (int)     — The character position of the matched text.
         *   
         *   $handler (Doku_Handler) — Object Reference to the Doku_Handler object.
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
        $func_name = preg_replace('/\{\{#([[:alpha:]]+):.*#\}\}/s', '\1', $match);
        $func_name = PhpString::strtolower($func_name);

        // Delete delimiters "{{#functionname:" and "#}}".
        // The 's' at the end of '/pattern/s' adds support to multiline strings.
        $parts = preg_replace('/\{\{#[[:alpha:]]+:(.*)#\}\}/s', '\1', $match);
        
        // Create list with all parameters splited by "|" pipe
        // Could use "preg_split('/\|/', $parts)" instead
        $params = explode('|', $parts);

        // Stripping whitespace from the beginning and end of strings
        foreach ($params as &$value) {
            $value = trim($value);
        }
        
        // ==================== FINALLY: do the work! ====================
        switch($func_name){
            // To add a new function, first add a "case" below, make it call a
            // funtion, then write the funtion.
            case 'if':
                $func_result = $this->_IF($params, $func_name);
                break;
            case 'ifeq':
                $func_result = $this->_IFEQ($params, $func_name);
                break;
            case 'switch':
                $func_result = $this->_SWITCH($params, $func_name);
                break;
            default:
                $func_result = ' <span style="color: red;">' . $this->getLang('error') .
                               ' <code>'. $func_name . '</code>: ' . 
                               $this->getLang('no_such_function') . ' </span>';
                break;
        }
        
        // The instructions provided to the render() method:
        return $func_result;
    }

    /** @inheritDoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#render_method
         * The part of the plugin that provides the output for the final web page.
         *
         * Parameters:
         *
         *   $mode     — Name for the format mode of the final output produced by the
         *               renderer.
         *
         *   $renderer — Give access to the object Doku_Renderer, which contains useful
         *               functions and values.
         *
         *   $data     — An array containing the instructions previously prepared
         *               and returned by the plugin's own handle() method. The render()
         *               must interpret the instruction and generate the appropriate
         *               output.
         */
        
        if ($mode !== 'xhtml') {
            return false;
        }
        
        if (!$data) {
            return false;
        }
        
        // Do not use <div></div> because we need inline substitution!
		$renderer->doc .= $data;

        return true;
    }
    
    /**
     * ========== #IF
     * {{#if: 1st parameter | 2nd parameter | 3rd optional parameter #}}
     * {{#if: test string | value if test string is not empty | value if test string is empty (or only white space) #}}
     */
    function _IF($params, $func_name)
    {
        if ( count($params) < 2 ) {
            $result = ' <span style="color: red;">' . $this->getLang('error') . 
                      ' <code>'. $func_name . '</code>: ' . $this->getLang('not_enough_params') .
                      ' </span>';
        } else {
            if ( !empty($params[0]) ) {
                $result = $params[1];
            } else {
                if ( !empty($params[2]) ) {
                    $result = $params[2];
                } else {
                    /**
                     * The last parameter (false) must have been intentionally omitted:
                     * user wants the result to be null if the test string is empty.
                     */
                    $result = null;
                }
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
        if ( count($params) < 4 ) {
            $result = ' <span style="color: red;">' . $this->getLang('error') . 
                      ' <code>'. $func_name . '</code>: ' . $this->getLang('not_enough_params') .
                      ' </span>';
        } else {
            if ( $params[0] == $params[1] ) {
                $result = $params[2];
            } else {
                $result = $params[3];
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
	        $value = preg_split('/=/', $value);
        	if ( isset($value[1]) ) {
        		$cases_kv[trim($value[0])] = trim($value[1]);
        	} else {
        		$test_and_default_string[] = trim($value[0]);
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
        	$result = $test_and_default_string[1] ?? '';
        }
        
        return $result;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8: