<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\HTTP\DokuHTTPClient;

/**
 * DokuWiki Plugin scrape (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class syntax_plugin_scrape extends SyntaxPlugin
{
    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 301;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{scrape>.+?}}', $mode, 'plugin_scrape');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = substr($match, 9, -2);
        [$url, $title] = explode('|', $match, 2);
        [$url, $query] = explode(' ', $url, 2);
        //FIXME handle refresh parameter?
        [$url, $hash]  = explode('#', $url, 2);
        if ($hash)   $query = trim('#' . $hash . ' ' . $query);
        if (!$query) $query = 'body ~';

        $inner = false;
        if (substr($query, -1) == '~') {
            $query = rtrim($query, '~ ');
            $inner = true;
        }

        $data = ['url'   => $url, 'title' => $title, 'query' => $query, 'inner' => $inner];

        return $data;
    }

    /** @inheritdoc */
    public function render($mode, Doku_Renderer $R, $data)
    {
        if ($mode != 'xhtml') return false;

        // support interwiki shortcuts
        if (strpos($data['url'], '>') !== false) {
            [$iw, $ref] = explode('>', $data['url'], 2);
            $data['url'] = $R->_resolveInterWiki($iw, $ref);
        }

        // check if URL is allowed
        $re = $this->getConf('allowedre');
        if (!$re || !preg_match('/' . $re . '/i', $data['url'])) {
            $R->doc .= 'This URL is not allowed for scraping';
            return true;
        }


        // fetch remote data
        $http = new DokuHTTPClient();
        $resp = $http->get($data['url']);

        if (!$resp) {
            $R->doc .= 'Failed to load remote ressource';
            return true;
        }

        // determine mime type
        [$mime, $charset] = explode(';', $http->resp_headers['content-type']);
        $mime    = trim(strtolower($mime));
        $charset = trim(strtolower($charset));
        $charset = preg_replace('/charset *= */', '', $charset);


        if (preg_match('/image\/(gif|png|jpe?g)/', $mime)) {
            // image embed
            $R->externalmedia($data['url'], $data['title']);
        } elseif (preg_match('/text\//', $mime)) {
            if ($charset != 'utf-8') {
                $resp = utf8_encode($resp); // we just assume it to be latin1
            }

            if (preg_match('/text\/html/', $mime)) {
                // display HTML
                $this->displayHTML($data, $resp, $R);

                //FIXME support directory listings?
            } else {
                // display as code
                $R->preformatted($resp);
            }
        } else {
            $R->doc .= 'Failed to handle mime type ' . hsc($mime);
            return true;
        }

        return true;
    }

    private function displayHTML($data, $resp, &$R)
    {
        global $conf;

        // extract the wanted part from the HTML using the given query
        phpQuery::newDocument($resp);
        $pq = pq($data['query']);

        // fix lists to match DokuWiki's style
        $pq->find('li')->wrapInner('<div class="li" />');

        // fix tables to match DokuWiki's style
        $pq->find('table')->addClass('inline');

        // fix links to match DokuWiki's style
        foreach ($pq->find('a') as $link) {
            $plink = pq($link);
            [$ext, $mime] = mimetype($plink->attr('href'), true);
            if ($ext && $mime != 'text/html') {
                // is it a known mediafile?
                $plink->addClass('mediafile');
                $plink->addClass('mf_' . $ext);
                if ($conf['target']['media']) {
                    $plink->attr('target', $conf['target']['media']);
                }
            } elseif ($plink->attr('href')) {
                // treat it as external
                if ($conf['target']['extern']) {
                    $plink->attr('target', $conf['target']['extern']);
                }
                $plink->addClass('urlextern');
            }
            $plink->removeAttr('style');
        }

        // get all wanted HTML by converting the DOMElements back to HTML
        $html = '';
        if ($data['inner']) {
            $html .= $pq->html();
        } else {
            foreach ($pq->elements as $elem) {
                $html .= $elem->ownerDocument->saveXML($elem);
            }
        }

        // clean up HTML
        $purifier = new HTMLPurifier();
        $purifier->config->set('Attr.EnableID', true);
        $purifier->config->set('Attr.IDPrefix', 'scrape___');
        $purifier->config->set('URI.Base', $data['url']);
        $purifier->config->set('URI.MakeAbsolute', true);
        $purifier->config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);
        io_mkdir_p($conf['cachedir'] . '/_HTMLPurifier');
        $purifier->config->set('Cache.SerializerPath', $conf['cachedir'] . '/_HTMLPurifier');
        $html = $purifier->purify($html);

        $R->doc .= $html;
    }
}
