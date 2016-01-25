<?php

use BEM\BH;

class nestedTest extends PHPUnit_Framework_TestCase {

    /**
     * @before
     */
    function setupBhInstance () {
        $this->bh = new BH();
        $this->bh->setOptions(['indent' => ' ']);
    }

    function test_it_should_return_bemjson_html () {
        $out = $this->bh->apply([
            'block' => 'button',
            'content' => [
                'block' => 'nested',
                'content' => [
                    'elem' => 'elem',
                ],
            ],
        ]);
        $test = '
<div class="button">
  <div class="nested">
    <div class="nested__elem">
    </div>
  </div>
</div>';
//        echo "Test:[$test]\nOut:[$out]\n";
        $this->assertSame($test, $out);

    }


}
