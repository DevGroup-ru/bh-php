<?php

use BEM\BH;

class nestedTest extends PHPUnit_Framework_TestCase {

    /**
     * @before
     */
    function setupBhInstance () {
        $this->bh = new BH();
        $this->bh->setOptions(['indent' => 'xx', 'modsDelimiter' => '--']);
    }

    function test_it_should_return_bemjson_html () {
        $out = $this->bh->apply([
            'block' => 'button',
            'content' => [
                'block' => 'nested',
                'content' => [
                    'elem' => 'elem',
                    'mods' => [
                        'red'
                    ]
                ],
            ],
        ]);
        $test = '
<div class="button">
xx
xx<div class="nested">
xxxx
xxxx<div class="nested__elem nested__elem--red">
xxxxxx
xxxx</div>

xx</div>

</div>
';
//        echo "Test:[$test]\nOut:[$out]\n";
        $this->assertSame($test, $out);

    }


}
