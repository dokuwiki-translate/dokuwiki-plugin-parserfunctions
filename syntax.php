<?php
/**
 * DokuWiki Plugin parserfunctions (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Daniel "Nerun" Rodrigues <danieldiasr@gmail.com>
 * 
 * This is my first plugin, and I don't even know PHP well, that's why it's full
 * of comments, but I'll leave it that way so I can consult it in the future.
 * 
 * Sat, 09 Dec 2023 14:59 -0300
 */

use dokuwiki\Extension\SyntaxPlugin;

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
         * Should go after WST plugin, to be able to render {{{1}}}.
         */
        return 320;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        /* READ: https://www.dokuwiki.org/devel:syntax_plugins#patterns
         * {{#if: test string | value if test string is not empty | value if test string is empty (or only white space) }}
         * {{#if: first parameter | second parameter | third parameter }}
         */
        $this->Lexer->addSpecialPattern('\{\{#[Ii][Ff]:[^(#\}\})(\|)]*\|[^(#\}\})(\|)]*\|[^(#\}\})(\|)]*#\}\}', $mode, 'plugin_parserfunctions');
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
        
        // Delete delimiters "{{#if:"" and "#}}".
        $parts = substr($match, 6, -3);
        
        // Create list of size 3: each parameter
        // Could use "preg_split('/\|/', $parts)" too
        $params = explode('|', $parts);
        
        // Stripping whitespace from the beginning and end of strings
        foreach ($params as &$value) {
            $value = trim($value);
        }
        
        /* FINALLY: do the work!
         * {{#if: test string | value if test string is not empty | value if test string is empty (or only white space) }}
         * {{#if: first parameter | second parameter | third parameter }}
         */
        if ( !empty($params[0]) ) {
            $func_result = $params[1];
        } else {
            $func_result = $params[2];
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
}
// vim:ts=4:sw=4:et:enc=utf-8: