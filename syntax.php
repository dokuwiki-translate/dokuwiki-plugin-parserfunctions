<?php
/**
 * DokuWiki Plugin parserfunctions (Syntax Component)
 *
 * @license  GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author   Daniel "Nerun" Rodrigues <danieldiasr@gmail.com>
 * @created  Sat, 09 Dec 2023 14:59 -0300
 * 
 * This is my first plugin, and I don't even know PHP well, that's why it's full
 * of comments, but I'll leave it that way so I can consult it in the future.
 * 
 */
use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Utf8\PhpString;

class syntax_plugin_parserfunctions extends SyntaxPlugin
{
    /** @var helper_plugin_parserfunctions $helper */
    private $helper;

    public function __construct() {
        $this->helper = plugin_load('helper', 'parserfunctions');
    }

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
         * This pattern accepts any alphanumeric function name but not nested
         * functions.
         *
         * ChatGPT helped me to fix this pattern! (18 jan 2025)
         */
        $this->Lexer->addSpecialPattern('\{\{#[[:alnum:]]+:(?:(?!\{\{#|#\}\}).)*#\}\}', $mode, 'plugin_parserfunctions');
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
        
        /* The 's' at the end of '/pattern/s' adds support to multiline strings.
         * preg_match creates array $m by using groups \1 and \2
         */
        preg_match('/\{\{#([[:alnum:]]+):(.*)#\}\}/s', $match, $m);
        /* strtolower converts only ASCII; PhpString::strtolower supports UTF-8,
         * added by "use dokuwiki\Utf8\PhpString;" at line 15. The function
         * names will probably only use ASCII characters, but it's a precaution.
         */
        $func_name = PhpString::strtolower($m[1]); // function name: "if", "ifeq", "ifexpr" etc.
        $parts = $m[2]; // function parameters with pipes etc

        /* parseParameters below was necessary to allow the escape sequence of
         * the pipe character "|" using the standard DokuWiki formatting syntax
         * which is to wrap it in "%%". The $params is trimmed.
         */
        $params = $this->helper->parseParameters($parts);
        
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
                $func_result = $this->helper->formatError('important', $func_name, 'no_such_function');
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
        $data = $this->helper->processEscapes($data);

        // Do not use <div></div> because we need inline substitution!
		$data = $renderer->render_text($data, 'xhtml');
		// Remove the first '<p>' and the last '</p>'
		if (substr($data, 0, 3) === '<p>' && substr($data, -4) === '</p>') {
            $data = substr($data, 3, -4);
        }
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
            $result = $this->helper->formatError('alert', $func_name, 'not_enough_params');
        } else {
            if ( !empty($params[0]) ) {
                $result = $params[1] ?? '';
            } else {
                $result = $params[2] ?? '';
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
            $result = $this->helper->formatError('alert', $func_name, 'not_enough_params');
        } else {
            if ( $params[0] == $params[1] ) {
                $result = $params[2] ?? '';
            } else {
                $result = $params[3] ?? '';
            }
        }
        
        return $result;
    }
    
    /**
     * ======= #IFEXIST
     * Syntax: {{#ifexist: target | if-true | if-false #}}
     *
     * Accepts:
     * - DokuWiki page/media IDs (e.g. "wiki:start", "wiki:image.png")
     * - Namespaces (must end with ":", e.g. "wiki:")
     * - Absolute or relative filesystem paths
     *
     * @param array $params [
     *     0 => string $target   Path or ID to check (required)
     *     1 => string $ifTrue   Value to return if target exists (optional)
     *     2 => string $ifFalse  Value to return if target doesn't exist (optional)
     * ]
     * @param string $func_name Name of the parser function (for error messages)
     * @return string Rendered output based on existence check
     */
    function _IFEXIST($params, $func_name)
    {
        if (count($params) < 1) {
            return $this->helper->formatError('alert', $func_name, 'not_enough_params');
        }
        
        $target = trim($params[0]);
        if ($target === '') {
            return $this->helper->formatError('alert', $func_name, 'empty_test_parameter');
        }
        
        $exists = $this->helper->checkExistence($target);
        
        return $exists
            ? ($params[1] ?? '')
            : ($params[2] ?? '');
    }
    
    /**
     * ========== #SWITCH
     * {{#switch: comparison string
     * | case1 = result1
     * | ...
     * | caseN = resultN
     * | default result
     * #}}
     */
    function _SWITCH($params, $func_name) {
        if (count($params) < 2) {
            return $this->helper->formatError('alert', $func_name, 'not_enough_params');
        }

        $parsed = $this->helper->parseSwitchCases($params);
        
        // Checks if the test string exists as a key in the switch cases array
        if (array_key_exists($parsed['test'], $parsed['cases'])) {
            return $parsed['cases'][$parsed['test']]; // ← May return empty string
        }
        
        // Returns the default (explicit or implicit) only if the case does not exist
        return $parsed['default'] ?? '';
    }
}

