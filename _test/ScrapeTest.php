<?php

namespace dokuwiki\plugin\scrape\test;

use DokuWikiTest;

/**
 * Tests for the scrape plugin
 *
 * @group plugin_scrape
 * @group plugins
 */
class ScrapeTest extends DokuWikiTest
{

    /**
     * We don't care about whitespace in the HTML
     *
     * @param $string
     * @return string
     */
    protected function stripWhitespace($string)
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    public function testFullBody()
    {
        $html = file_get_contents(__DIR__ . '/test.html');
        $data = [
            'url' => 'http://example.com',
            'title' => 'Example',
            'query' => 'body',
            'inner' => true
        ];

        $expect = <<<EOT
<h1>This is a Test Document</h1>
<p id="scrape___hello">It's just here to check if the all the manipulation works</p>
<ul>
<li><div class="li">This is a list item</div></li>
<li><div class="li">This is <a href="http://example.com/relative" class="urlextern">relative link</a></div></li>
<li><div class="li">This is <a href="http://example.com/relative" class="urlextern">root absolute link</a></div></li>
<li><div class="li">This is <a href="https://example.com" class="urlextern">fully absolute link</a></div></li>
</ul>
<div class="table"><table class="inline"><tr><td>one cell</td></tr></table></div>
<p>Another paragraph</p>
EOT;

        $plugin = new \syntax_plugin_scrape();
        $output = $this->callInaccessibleMethod($plugin, 'cleanHTML', [$data, $html]);
        $this->assertEquals($this->stripWhitespace($expect), $this->stripWhitespace($output));
    }

    public function testParagraphs()
    {
        $html = file_get_contents(__DIR__ . '/test.html');
        $data = [
            'url' => 'http://example.com',
            'title' => 'Example',
            'query' => 'p',
            'inner' => false
        ];

        $expect = <<<EOT
<p id="scrape___hello">It's just here to check if the all the manipulation works</p>
<p>Another paragraph</p>
EOT;

        $plugin = new \syntax_plugin_scrape();
        $output = $this->callInaccessibleMethod($plugin, 'cleanHTML', [$data, $html]);
        $this->assertEquals($this->stripWhitespace($expect), $this->stripWhitespace($output));
    }
}
