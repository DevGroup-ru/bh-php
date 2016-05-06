<?php

use BEM\BH;

class matcherTest extends PHPUnit_Framework_TestCase {
    /** @var  BH */
    protected $bh;
    /**
     * @before
     */
    function setupBhInstance () {
        $this->bh = new BH();
        $this->bh->setOptions(['indent' => '  ', 'modsDelimiter' => '--']);
    }

    function test_it_should_add_remove_matcher () {
        $bemjson = [
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
        ];
        $out = $this->bh->apply($bemjson);
        $test = '
<div class="button">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        static::assertSame($test, $out);
        $id = $this->bh->addMatcher('button', function(\BEM\Context $ctx) {
            $ctx->mod('green', true);
        });
        $out = $this->bh->apply($bemjson);
        $testWithGreen = '
<div class="button button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        static::assertSame($testWithGreen, $out);
        $this->bh->removeMatcherById($id);
        $out = $this->bh->apply($bemjson);
        static::assertSame($test, $out);
        static::assertFalse($this->bh->removeMatcherById($id));

    }

    public function test_it_should_use_reference()
    {
        $bemjson = [
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
        ];
        $matcher = new \BEM\Matcher('button', function(\BEM\Context $ctx) {
            $ctx->mod('green', true);
        });
        $this->bh->matcher($matcher);
        $testWithGreen = '
<div class="button button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithGreen, $out);
        // change closure
        $matcher->matcher = function(\BEM\Context $ctx) {
            $ctx->mod('blue', true);
        };
        $testWithBlue = '
<div class="button button--blue">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithBlue, $out);

    }

    public function test_it_should_use_reference2()
    {
        $bemjson = [
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
        ];
        $matcher = new \BEM\Matcher('button', function(\BEM\Context $ctx) {
            $ctx->mod('green', true);
        });
        $id = $this->bh->addMatcher($matcher->expression, $matcher);
        $testWithGreen = '
<div class="button button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithGreen, $out);
        // change closure
        $matcher->matcher = function(\BEM\Context $ctx) {
            $ctx->mod('blue', true);
        };
        $testWithBlue = '
<div class="button button--blue">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithBlue, $out);

    }

    public function test_matcher_list()
    {
        $bemjson = [
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
        ];
        $list = [
            'green' => new \BEM\Matcher('button', function(\BEM\Context $ctx) {
                $ctx->mod('green', true);
            }),
            'blue' => new \BEM\Matcher('button', function(\BEM\Context $ctx) {
                $ctx->mod('blue', true);
            }),
        ];

        $ids = $this->bh->addMatcherList($list);
        static::assertSame([0,1], $ids);
        $testWithBlueGreen = '
<div class="button button--blue button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithBlueGreen, $out);
        // change closure
        $list['blue']->matcher = function(\BEM\Context $ctx) {
            $ctx->mod('red', true);
        };
        $testWithRedGreen = '
<div class="button button--red button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithRedGreen, $out);

        static::assertTrue($this->bh->removeMatcherById($list['blue']->id));

        $testWithGreen = '
<div class="button button--green">
  
  <div class="nested">
    
    <div class="nested__elem nested__elem--red">
      
    </div>

  </div>

</div>
';
        $out = $this->bh->apply($bemjson);
        static::assertSame($testWithGreen, $out);

        static::assertTrue($this->bh->removeMatcherById([$list['green']->id]));
        static::assertFalse($this->bh->removeMatcherById([0,1]));

    }
}
