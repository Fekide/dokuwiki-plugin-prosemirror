<?php
/**
 * DokuWiki Plugin prosemirror (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki

use dokuwiki\plugin\prosemirror\parser\LinkNode;

if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_prosemirror_ajax extends DokuWiki_Action_Plugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleAjax(Doku_Event $event, $param)
    {
        if ($event->data !== 'plugin_prosemirror') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT, $ID;
        $ID = $INPUT->str('id');
        $responseData = [];
        foreach ($INPUT->arr('actions') as $action) {
            switch ($action) {
                case 'resolveInternalLink':
                    {
                        $inner = $INPUT->str('inner');
                        $responseData[$action] = $this->resolveInternalLink($inner, $ID);
                        break;
                    }
                case 'resolveInterWikiLink':
                    {
                        $inner = $INPUT->str('inner');
                        list($shortcut, $reference) = explode('>', $inner);
                        $responseData[$action] = $this->resolveInterWikiLink($shortcut, $reference);
                        break;
                    }
                case 'resolveMedia':
                    {
                        $attrs = $INPUT->arr('attrs');
                        $responseData[$action] = [
                            'data-resolvedHtml' => \dokuwiki\plugin\prosemirror\parser\ImageNode::resolveMedia(
                                $attrs['id'],
                                $attrs['title'],
                                $attrs['align'],
                                $attrs['width'],
                                $attrs['height'],
                                $attrs['cache'],
                                $attrs['linking']
                            )
                        ];
                        break;
                    }
                case 'resolveImageTitle':
                    {
                        $image = $INPUT->arr('image');
                        $responseData[$action] = [];
                        $responseData[$action]['data-resolvedImage'] = LinkNode::resolveImageTitle(
                            $ID,
                            $image['id'],
                            $image['title'],
                            $image['align'],
                            $image['width'],
                            $image['height'],
                            $image['cache']
                        );
                        break;
                    }
                default:
                    {
                        dbglog('Unknown action: ' . $INPUT->str('action'), __FILE__ . ': ' . __LINE__);
                        http_status(400, 'unknown action');
                        return;
                    }
            }
        }

        echo json_encode($responseData);
    }

    protected function resolveInterWikiLink($shortcut, $reference)
    {
        $xhtml_renderer = p_get_renderer('xhtml');
        $xhtml_renderer->interwiki = getInterwiki();
        $url = $xhtml_renderer->_resolveInterWiki($shortcut, $reference, $exits);
        return [
            'url' => $url,
            'resolvedClass' => 'interwikilink interwiki iw_' . $shortcut,
        ];
    }

    protected function resolveInternalLink($inner, $curId)
    {
        if ($inner[0] === '#') {
            return dokuwiki\plugin\prosemirror\parser\LocalLinkNode::resolveLocalLink($inner, $curId);
        }

        // FIXME: move this to parser/InternalLinkNode ?
        $params = '';
        $parts  = explode('?', $inner, 2);
        $resolvedPageId = $parts[0];
        if(count($parts) === 2) {
            $params = $parts[1];
        }
        $ns = getNS($curId);
        $xhtml_renderer = p_get_renderer('xhtml');
        $default = $xhtml_renderer->_simpleTitle($inner);
        resolve_pageid($ns, $resolvedPageId, $exists);

        if(useHeading('content')) {
            $heading = p_get_first_heading($resolvedPageId);
        }
        if (empty($heading)) {
            $heading = $default;
        }

        $url = wl($resolvedPageId, $params);

        return [
            'id' => $resolvedPageId,
            'exists' => $exists,
            'heading' => $heading,
            'url' => $url,
        ];
    }
}
