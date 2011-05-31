<?php
/**
 * DokuWiki Plugin scrape (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_scrape extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler &$controller) {

#       $controller->register_hook('INDEXER_PAGE_ADD', 'FIXME', $this, 'handle_indexer_page_add');

    }

    public function handle_indexer_page_add(Doku_Event &$event, $param) {
    }

}

// vim:ts=4:sw=4:et:
