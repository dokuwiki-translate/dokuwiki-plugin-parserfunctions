<?php
/**
 * DokuWiki Plugin parserfunctions (Action Component)
 *
 * Captures nested functions {{#func: ... #}} before the Lexer and processes
 * them recursively.
 *
 * @author  ChatGPT -- Wed, 02 jul 2025 12:04:42 -0300
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_parserfunctions extends ActionPlugin
{
    /** @var helper_plugin_parserfunctions */
    private $helper;

    public function __construct()
    {
        $this->helper = plugin_load('helper', 'parserfunctions');
    }

    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook(
            'PARSER_WIKITEXT_PREPROCESS',
            'BEFORE',
            $this,
            'handlePreprocess'
        );
    }

    public function handlePreprocess(Event $event)
    {
        $text = $event->data;
        $text = $this->processParserFunctions($text);
        $event->data = $text;
    }

    private function processParserFunctions($text)
    {
        $index = 0;
        while (($match = $this->extractBalancedFunction($text)) !== false) {
            $placeholder = "@@PF" . str_pad($index, 3, '0', STR_PAD_LEFT) . "@@";
            $resolved = plugin_load('syntax', 'parserfunctions')->resolveFunction($match);

            $text = str_replace($match, $resolved, $text);
            $index++;
        }

        return $text;
    }

    private function extractBalancedFunction($text)
    {
        $start = strpos($text, '{{#');
        if ($start === false) return false;

        $level = 0;
        $length = strlen($text);
        for ($i = $start; $i < $length - 2; $i++) {
            if (substr($text, $i, 3) === '{{#') {
                $level++;
                $i += 2;
            } elseif (substr($text, $i, 3) === '#}}') {
                $level--;
                $i += 2;

                if ($level === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return false; // Malformed
    }
}

