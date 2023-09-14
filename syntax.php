<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\HTTP\DokuHTTPClient;
use DOMWrap\Document;

/**
 * DokuWiki Plugin scrape (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class syntax_plugin_scrape extends SyntaxPlugin
{
    public function __construct()
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

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
        [$url, $title] = sexplode('|', $match, 2);
        [$url, $query] = sexplode(' ', $url, 2);
        //FIXME handle refresh parameter?
        [$url, $hash] = sexplode('#', $url, 2);
        if ($hash) $query = trim('#' . $hash . ' ' . $query);
        if (!$query) $query = 'body ~';

        $inner = false;
        if (substr($query, -1) == '~') {
            $query = rtrim($query, '~ ');
            $inner = true;
        }

        $data = [
            'url' => $url,
            'title' => $title,
            'query' => $query,
            'inner' => $inner
        ];

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
        [$mime, $charset] = sexplode(';', $http->resp_headers['content-type'], 2);
        $mime = trim(strtolower($mime));
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
                $R->doc .= $this->cleanHTML($data, $resp);

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

    private function cleanHTML($data, $resp)
    {
        global $conf;

        // extract the wanted part from the HTML using the given query
        $doc = new Document();
        $doc->html($resp);
        $pq = $doc->find($data['query']);

        // fix lists to match DokuWiki's style
        $pq->find('li')->wrapInner('<div class="li" />');

        // fix tables to match DokuWiki's style
        $pq->find('table')->addClass('inline')->wrap('<div class="table" />');

        // fix links to match DokuWiki's style
        foreach ($pq->find('a') as $link) {
            [$ext, $mime] = mimetype($link->attr('href'), true);
            if ($ext && $mime != 'text/html') {
                // is it a known mediafile?
                $link->addClass('mediafile');
                $link->addClass('mf_' . $ext);
                if ($conf['target']['media']) {
                    $link->attr('target', $conf['target']['media']);
                }
            } elseif ($link->attr('href')) {
                // treat it as external
                if ($conf['target']['extern']) {
                    $link->attr('target', $conf['target']['extern']);
                }
                $link->addClass('urlextern');
            }
            $link->removeAttr('style');
        }

        $html = '';
        if ($data['inner']) {
            $html .= $pq->html();
        } else {
            $pq->each(function ($node) use (&$html) {
                $html .= $node->ownerDocument->saveXML($node) . "\n";
            });
        }

        // clean up HTML
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Attr.EnableID', true);
        $config->set('Attr.IDPrefix', 'scrape___');
        $config->set('URI.Base', $data['url']);
        $config->set('URI.MakeAbsolute', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);
        io_mkdir_p($conf['cachedir'] . '/_HTMLPurifier');
        $config->set('Cache.SerializerPath', $conf['cachedir'] . '/_HTMLPurifier');
        $purifier = new HTMLPurifier($config);
        $html = $purifier->purify($html);

        return trim($html);
    }
}
